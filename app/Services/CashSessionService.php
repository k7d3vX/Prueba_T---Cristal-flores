<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ClosingMode;
use App\Enums\SessionStatus;
use App\Models\CashSession;
use App\Models\User;
use App\Repositories\Contracts\CashRegisterRepositoryInterface;
use App\Repositories\Contracts\CashSessionRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WithdrawalRepositoryInterface;
use App\Support\CashSessionDetails;
use App\Support\OpeningSuggestion;
use App\Support\PreviousTurn;
use Carbon\CarbonInterface;
use App\Support\Money;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/** Casos de uso de la caja: abrir, listar y ver el detalle según quién mira. */
class CashSessionService
{
    public function __construct(
        private CashSessionRepositoryInterface $sessions,
        private CashRegisterRepositoryInterface $registers,
        private UserRepositoryInterface $users,
        private SaleRepositoryInterface $sales,
        private WithdrawalRepositoryInterface $withdrawals,
        private CashClosingService $closing,
    ) {}

    public function listFor(User $user): Collection
    {
        return $this->sessions->listFor($user);
    }

    public function cashiers(): Collection
    {
        return $this->users->cashiers();
    }

    /** Cajas libres para abrir un turno (las que ya tienen uno abierto no aparecen). */
    public function availableRegisters(): Collection
    {
        $open = $this->sessions->openRegisterIds();

        return $this->registers->all()->reject(fn ($r) => in_array($r->id, $open, true))->values();
    }

    public const DEFAULT_OPENING_FLOAT = '300.00';

    /**
     * Monto inicial secuencial: cada caja propone el fondo con que empezó su turno anterior.
     * La dueña además ve cómo le fue a ese turno (ganó, inició, total, horario y tiempo trabajado).
     *
     * @return array<int, OpeningSuggestion>  indexado por id de caja
     */
    public function openingSuggestions(Collection $registers, User $viewer): array
    {
        $suggestions = [];

        foreach ($registers as $register) {
            $previous = $this->sessions->lastClosedFor($register->id);

            $suggestions[$register->id] = new OpeningSuggestion(
                openingFloat: $previous?->opening_float ?? self::DEFAULT_OPENING_FLOAT,
                previous: ($previous && $viewer->isOwner()) ? $this->previousTurn($previous) : null,
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

    /** RF-1 / AC-01: abre un turno en una caja; cada caja admite una sola sesión abierta. */
    public function open(
        User $by,
        int $cashierId,
        int $registerId,
        string $openingFloat,
        ClosingMode $mode,
        ?CarbonInterface $openedAt = null,
    ): CashSession {
        if (Money::toCents($openingFloat) < 0) { // RN-11
            throw new DomainException('El fondo inicial no puede ser negativo.');
        }

        $message = 'Esa caja ya tiene un turno abierto. Ciérralo antes de abrir otro.';

        if ($this->sessions->existsOpenForRegister($registerId)) {
            throw new DomainException($message);
        }

        try {
            return $this->sessions->create([
                'cash_register_id' => $registerId,
                'cashier_id' => $cashierId,
                'opened_by' => $by->id,
                'opening_float' => $openingFloat,
                'closing_mode' => $mode,
                'status' => SessionStatus::Open,
                'opened_at' => $openedAt ?? now(),
            ]);
        } catch (UniqueConstraintViolationException) { // carrera: el índice único de PostgreSQL lo impide
            throw new DomainException($message);
        }
    }

    /**
     * Qué puede ver `$viewer` de la caja.
     * RN-5: en caja ciega el cajero no recibe totales ni listados (se decide aquí, en el servidor).
     */
    public function details(CashSession $session, User $viewer): CashSessionDetails
    {
        $showTotals = $viewer->isOwner() || ! $session->isBlind();

        return new CashSessionDetails(
            session: $showTotals ? $session : $this->withoutSnapshots($session),
            showTotals: $showTotals,
            summary: $showTotals ? $this->closing->summary($session) : null,
            sales: $showTotals ? $this->sales->forSession($session, newestFirst: true) : collect(),
            withdrawals: $showTotals ? $this->withdrawals->forSession($session, newestFirst: true) : collect(),
            canClose: $this->closing->canClose($session, $viewer),
        );
    }

    /**
     * Copia de la sesión sin esperado/contado/diferencia: aunque una vista se equivoque,
     * el cajero de una caja ciega no tiene esos datos en memoria (defensa en profundidad).
     */
    private function withoutSnapshots(CashSession $session): CashSession
    {
        $copy = $session->replicate();
        $copy->setRawAttributes(Arr::except($session->getAttributes(), ['counted_cash', 'expected_cash', 'difference']), true);
        $copy->exists = true;
        $copy->setRelations($session->getRelations());

        return $copy;
    }
}
