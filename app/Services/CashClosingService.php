<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\ReviewStatus;
use App\Enums\SessionStatus;
use App\Models\CashSession;
use App\Models\User;
use App\Repositories\Contracts\CashSessionRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Contracts\WithdrawalRepositoryInterface;
use App\Support\ClosingCalculator;
use App\Support\Denominations;
use App\Support\ClosingReport;
use App\Support\ClosingSummary;
use App\Support\Money;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/** ÚNICO lugar donde se calcula y se registra el cierre de caja. */
class CashClosingService
{
    public function __construct(
        private CashSessionRepositoryInterface $sessions,
        private SaleRepositoryInterface $sales,
        private WithdrawalRepositoryInterface $withdrawals,
        private AuditLogger $audit,
    ) {}

    /** Tolerancia del veredicto en centavos (RN-29): solo cambia la etiqueta, nunca la diferencia. */
    public function toleranceCents(): int
    {
        return Money::toCents((string) config('caja.tolerance'));
    }

    /** Resumen de la caja. Si ya está cerrada usa el contado guardado. */
    public function summary(CashSession $session, ?string $counted = null): ClosingSummary
    {
        // Los movimientos anulados siguen visibles en la auditoría pero no cuentan en los totales (RN-22).
        $sales = $this->sales->forSession($session)->reject(fn ($s) => $s->isVoided());

        $cash = $sales->filter(fn ($s) => $s->method === PaymentMethod::Cash)
            ->sum(fn ($s) => Money::toCents($s->amount));
        $qr = $sales->filter(fn ($s) => $s->method === PaymentMethod::Qr)
            ->sum(fn ($s) => Money::toCents($s->amount));
        $withdrawals = $this->withdrawals->forSession($session)
            ->reject(fn ($w) => $w->isVoided())
            ->sum(fn ($w) => Money::toCents($w->amount));

        $counted ??= $session->counted_cash;

        return ClosingCalculator::calculate(
            openingFloat: Money::toCents($session->opening_float),
            cashSales: $cash,
            qrSales: $qr,
            withdrawals: $withdrawals,
            counted: $counted === null ? null : Money::toCents($counted),
        );
    }

    /** Resumen + movimientos para la pantalla de la dueña. */
    public function report(CashSession $session): ClosingReport
    {
        return new ClosingReport(
            summary: $this->summary($session),
            sales: $this->sales->forSession($session),
            withdrawals: $this->withdrawals->forSession($session),
        );
    }

    /** RN-6 / AC-06: normal → cierra la dueña; ciega → cierra el cajero asignado. */
    public function canClose(CashSession $session, User $user): bool
    {
        if (! $session->isOpen()) {
            return false;
        }

        return $session->isBlind()
            ? $user->isCashier() && $session->cashier_id === $user->id
            : $user->isOwner();
    }

    /** Cierra la caja guardando snapshots (RN-8). Una caja cerrada no se reabre (RN-7). */
    /** @param  array<string, int>|null  $breakdown  desglose por denominaciones (RN-25), si el conteo vino por planilla */
    public function close(CashSession $session, string $counted, User $by, ?CarbonInterface $at = null, ?array $breakdown = null): CashSession
    {
        if (Money::toCents($counted) < 0) { // RN-11
            throw new DomainException('El efectivo contado no puede ser negativo.');
        }

        return DB::transaction(function () use ($session, $counted, $by, $at, $breakdown) {
            $locked = $this->sessions->lockForUpdate($session->id);

            if (! $locked->isOpen()) {
                throw new DomainException('El turno ya está cerrado o aún no se abrió.');
            }

            $summary = $this->summary($locked, $counted);

            $closedAt = $at ?? now();

            // RN-21: todo cierre queda pendiente de revisión; si la dueña cierra, ella ya lo vio.
            $closed = $this->sessions->markClosed($locked, [
                'status' => SessionStatus::Closed,
                'closed_at' => $closedAt,
                'closed_by' => $by->id,
                'counted_cash' => Money::fromCents($summary->countedCash),
                'count_breakdown' => $breakdown === null ? null : Denominations::normalize($breakdown),
                'expected_cash' => Money::fromCents($summary->expectedCash),
                'difference' => Money::fromCents($summary->difference),
                'review_status' => $by->isOwner() ? ReviewStatus::Reviewed : ReviewStatus::Pending,
                'reviewed_by' => $by->isOwner() ? $by->id : null,
                'reviewed_at' => $by->isOwner() ? $closedAt : null,
            ]);

            $this->audit->record('cierre', 'Turno cerrado con conteo de '.Money::format($summary->countedCash), $closed, $by);

            return $closed;
        });
    }

    /** RN-22: tras anular en un turno cerrado, esperado y diferencia se recalculan con el mismo conteo. */
    public function recalculate(CashSession $session): CashSession
    {
        $summary = $this->summary($session);

        return $this->sessions->update($session, [
            'expected_cash' => Money::fromCents($summary->expectedCash),
            'difference' => Money::fromCents($summary->difference),
        ]);
    }
}
