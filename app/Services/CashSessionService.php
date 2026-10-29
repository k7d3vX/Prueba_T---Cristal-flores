<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ClosingMode;
use App\Enums\PaymentMethod;
use App\Enums\SessionStatus;
use App\Models\CashSession;
use App\Models\User;
use App\Repositories\Contracts\CashRegisterRepositoryInterface;
use App\Repositories\Contracts\CashSessionRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WithdrawalRepositoryInterface;
use App\Support\CashierShift;
use App\Support\Money;
use App\Support\OpeningSuggestion;
use App\Support\OwnerDashboard;
use App\Support\PreviousTurn;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Casos de uso de la apertura de turnos y de lo que ve cada rol.
 * Apertura en dos pasos: la dueña abre → el cajero confirma el fondo recibido (RN-15).
 */
class CashSessionService
{
    /** Cuántos registros recientes puede ver el cajero (RN-16). */
    public const RECENT_LIMIT = 5;

    public function __construct(
        private CashSessionRepositoryInterface $sessions,
        private CashRegisterRepositoryInterface $registers,
        private UserRepositoryInterface $users,
        private SaleRepositoryInterface $sales,
        private WithdrawalRepositoryInterface $withdrawals,
        private CashClosingService $closing,
        private AuditLogger $audit,
        private AlertService $alerts,
    ) {}

    // ---- Panel de la dueña ----

    public function dashboard(User $owner): OwnerDashboard
    {
        $registers = $this->registers->all();
        $active = $this->sessions->activeSessions()->keyBy('cash_register_id');
        $free = $registers->reject(fn ($r) => $active->has($r->id))->values();
        $busyCashiers = $active->pluck('cashier_id')->all();

        return new OwnerDashboard(
            registers: $registers,
            active: $active,
            free: $free,
            freeCashiers: $this->users->cashiers()->reject(fn ($c) => in_array($c->id, $busyCashiers, true))->values(),
            suggestions: $this->openingSuggestions($free),
            sessions: $this->sessions->listFor($owner),
            pendingReview: $this->sessions->pendingReview(),
            voidRequests: $this->sales->pendingVoidRequests()
                ->map(fn ($m) => ['kind' => 'sale', 'movement' => $m])
                ->concat($this->withdrawals->pendingVoidRequests()->map(fn ($m) => ['kind' => 'withdrawal', 'movement' => $m]))
                ->sortBy(fn ($r) => $r['movement']->void_requested_at->getTimestamp())
                ->values(),
            alerts: $this->alerts->forOwner(),
            tolerance: $this->closing->toleranceCents(),
        );
    }

    /**
     * Monto inicial secuencial: cada caja propone su fondo habitual (el último con que la dueña abrió).
     * La dueña además ve cómo le fue al turno anterior (ganó, inició, total, horario y tiempo trabajado).
     *
     * @return array<int, OpeningSuggestion>  indexado por id de caja
     */
    private function openingSuggestions(Collection $registers): array
    {
        $suggestions = [];

        foreach ($registers as $register) {
            $previous = $this->sessions->lastClosedFor($register->id);

            $suggestions[$register->id] = new OpeningSuggestion(
                openingFloat: $register->default_float,
                previous: $previous ? $this->previousTurn($previous) : null,
            );
        }

        return $suggestions;
    }

    private function previousTurn(CashSession $session): PreviousTurn
    {
        $summary = $this->closing->summary($session);

        return new PreviousTurn(
            sessionId: $session->id,
            openingFloat: $summary->openingFloat,
            earned: $summary->totalSales,
            total: $summary->naiveExpected, // inicio + ganado
            openedAt: $session->opened_at,
            closedAt: $session->closed_at,
            workedSeconds: (int) $session->workedSeconds(),
        );
    }

    /** Fondo habitual de una caja, fijado por la dueña (RN-15). */
    public function setRegisterFloat(int $registerId, string $amount): void
    {
        $this->assertValidFloat($amount);
        $this->registers->setDefaultFloat($registerId, $amount);
    }

    // ---- Apertura ----

    /**
     * RF-1 (a): la dueña abre el turno. Queda *por confirmar* hasta que el cajero confirme el fondo.
     * El fondo con que abre pasa a ser el habitual de esa caja.
     */
    public function openForCashier(User $owner, int $cashierId, int $registerId, string $openingFloat, ClosingMode $mode): CashSession
    {
        $this->assertValidFloat($openingFloat);

        return DB::transaction(function () use ($owner, $cashierId, $registerId, $openingFloat, $mode) {
            $session = $this->create($owner->id, $cashierId, $registerId, $openingFloat, $mode, SessionStatus::PendingConfirmation, now());
            $this->registers->setDefaultFloat($registerId, $openingFloat);
            $this->audit->record('turno.abierto', 'La dueña abrió el turno con fondo '.Money::format(Money::toCents($openingFloat)).' ('.$mode->label().')', $session, $owner);

            return $session;
        });
    }

    /**
     * RF-1 (b): el cajero abre una caja libre. Fondo = el habitual de la caja y modo = ciego (AR-17):
     * el cajero no elige ninguno de los dos.
     */
    public function openByCashier(User $cashier, int $registerId, ?CarbonInterface $at = null): CashSession
    {
        if ($this->sessions->findActiveForCashier($cashier->id)) {
            throw new DomainException('Ya tienes un turno activo.');
        }

        $at ??= now();
        $float = $this->registers->findOrFail($registerId)->default_float;

        $session = $this->create($cashier->id, $cashier->id, $registerId, $float, ClosingMode::Blind, SessionStatus::Open, $at, confirmedAt: $at);
        $this->audit->record('turno.abierto', 'El cajero abrió el turno con el fondo habitual '.Money::format(Money::toCents($float)), $session, $cashier);

        return $session;
    }

    /** Un turno activo por caja (RN-19, AC-14) y por cajero; el índice único de PostgreSQL lo respalda. */
    private function create(
        int $openedBy,
        int $cashierId,
        int $registerId,
        string $openingFloat,
        ClosingMode $mode,
        SessionStatus $status,
        CarbonInterface $at,
        ?CarbonInterface $confirmedAt = null,
    ): CashSession {
        if ($this->sessions->existsActiveForRegister($registerId)) {
            throw new DomainException('Esa caja ya tiene un turno activo. Ciérralo antes de abrir otro.');
        }

        if ($this->sessions->findActiveForCashier($cashierId)) {
            throw new DomainException('Ese cajero ya tiene un turno activo.');
        }

        try {
            return $this->sessions->create([
                'cash_register_id' => $registerId,
                'cashier_id' => $cashierId,
                'opened_by' => $openedBy,
                'opening_float' => $openingFloat,
                'closing_mode' => $mode,
                'status' => $status,
                'opened_at' => $at,
                'float_confirmed_at' => $confirmedAt,
            ]);
        } catch (UniqueConstraintViolationException) { // carrera: los índices únicos de PostgreSQL lo impiden
            throw new DomainException('La caja o el cajero ya tienen un turno activo.');
        }
    }

    // ---- Confirmación del fondo ----

    /** El cajero confirma que recibió el fondo: el turno pasa a *abierto* y empieza a contar el tiempo. */
    public function confirmFloat(CashSession $session, User $cashier, ?CarbonInterface $at = null): CashSession
    {
        $at ??= now();

        return DB::transaction(function () use ($session, $cashier, $at) {
            $locked = $this->lockPendingFor($session, $cashier);

            $opened = $this->sessions->update($locked, [
                'status' => SessionStatus::Open,
                'float_confirmed_at' => $at,
                'float_disputed_at' => null,
                'opened_at' => $at,
            ]);
            $this->audit->record('fondo.confirmado', 'Confirmó el fondo de '.Money::format(Money::toCents($opened->opening_float)), $opened, $cashier);

            return $opened;
        });
    }

    /** RN-20: el cajero dice que el monto no coincide. El turno sigue por confirmar, marcado para la dueña. */
    public function disputeFloat(CashSession $session, User $cashier): CashSession
    {
        return DB::transaction(function () use ($session, $cashier) {
            $updated = $this->sessions->update($this->lockPendingFor($session, $cashier), ['float_disputed_at' => now()]);
            $this->audit->record('fondo.discutido', 'Indicó que el fondo no coincide', $updated, $cashier);

            return $updated;
        });
    }

    /** La dueña corrige el fondo de un turno que aún no se abrió; el cajero vuelve a confirmar. */
    public function adjustFloat(CashSession $session, string $openingFloat): CashSession
    {
        $this->assertValidFloat($openingFloat);

        return DB::transaction(function () use ($session, $openingFloat) {
            $locked = $this->sessions->lockForUpdate($session->id);

            if (! $locked->isPending()) {
                throw new DomainException('Solo se puede corregir el fondo de un turno por confirmar.');
            }

            $updated = $this->sessions->update($locked, ['opening_float' => $openingFloat, 'float_disputed_at' => null]);
            $this->audit->record('fondo.corregido', 'Fondo corregido a '.Money::format(Money::toCents($openingFloat)), $updated);

            return $updated;
        });
    }

    private function lockPendingFor(CashSession $session, User $cashier): CashSession
    {
        $locked = $this->sessions->lockForUpdate($session->id);

        if (! $locked->isPending() || $locked->cashier_id !== $cashier->id) {
            throw new DomainException('No tienes un fondo por confirmar.');
        }

        return $locked;
    }

    private function assertValidFloat(string $amount): void
    {
        if (Money::toCents($amount) < 0) { // RN-11
            throw new DomainException('El fondo inicial no puede ser negativo.');
        }
    }

    // ---- Zona del cajero ----

    /** El turno activo (por confirmar o abierto) del cajero. No hay ids de turno en sus URLs (RN-9). */
    public function activeFor(User $cashier): ?CashSession
    {
        return $this->sessions->findActiveForCashier($cashier->id);
    }

    /**
     * Qué ve el cajero (RN-16): fondo recibido y sus últimos 5 registros (hora y monto).
     * Nunca resumen, totales, QR acumulado, esperado ni diferencia, en ningún modo.
     */
    public function shiftFor(User $cashier): CashierShift
    {
        $session = $this->sessions->findActiveForCashier($cashier->id);

        if ($session === null) {
            $last = $this->sessions->lastClosedForCashier($cashier->id);
            $busy = $this->sessions->activeSessions()->pluck('cash_register_id')->all();

            return new CashierShift(
                state: 'none',
                freeRegisters: $this->registers->all()->reject(fn ($r) => in_array($r->id, $busy, true))->values(),
                lastClosedAt: $last?->closed_at,
                lastClosedBySelf: $last !== null && $last->closed_by === $cashier->id,
            );
        }

        return new CashierShift(
            state: $session->isOpen() ? 'open' : 'pending',
            registerName: $session->cashRegister->name,
            openingFloat: $session->opening_float,
            floatDisputed: $session->float_disputed_at !== null,
            canClose: $this->closing->canClose($session, $cashier),
            recent: $session->isOpen() ? $this->recentRecords($session) : new Collection,
        );
    }

    /** Ventas y salidas mezcladas, la más nueva primero. Cada fila: hora, descripción y monto propio. */
    private function recentRecords(CashSession $session): Collection
    {
        $limit = self::RECENT_LIMIT;

        $sales = $this->sales->recentForSession($session, $limit)->map(fn ($s) => [
            'kind' => 'sale',
            'at' => $s->created_at,
            'id' => $s->id,
            'requested' => $s->void_requested_at !== null,
            'label' => $s->method === PaymentMethod::Qr ? 'Venta QR' : 'Venta efectivo',
            'cents' => Money::toCents($s->amount),
        ]);

        $outs = $this->withdrawals->recentForSession($session, $limit)->map(fn ($w) => [
            'kind' => 'withdrawal',
            'at' => $w->created_at,
            'id' => $w->id,
            'requested' => $w->void_requested_at !== null,
            'label' => 'Salida · '.$w->reason,
            'cents' => Money::toCents($w->amount),
        ]);

        return $sales->concat($outs)
            ->sortByDesc(fn ($r) => $r['at']->getTimestamp() * 1_000_000 + $r['id'])
            ->take($limit)
            ->values();
    }
}
