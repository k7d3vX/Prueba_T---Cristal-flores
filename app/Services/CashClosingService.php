<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SessionStatus;
use App\Models\CashSession;
use App\Models\User;
use App\Repositories\Contracts\CashSessionRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Contracts\WithdrawalRepositoryInterface;
use App\Support\ClosingCalculator;
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
    ) {}

    /** Resumen de la caja. Si ya está cerrada usa el contado guardado. */
    public function summary(CashSession $session, ?string $counted = null): ClosingSummary
    {
        $sales = $this->sales->forSession($session);

        $cash = $sales->filter(fn ($s) => $s->method === PaymentMethod::Cash)
            ->sum(fn ($s) => Money::toCents($s->amount));
        $qr = $sales->filter(fn ($s) => $s->method === PaymentMethod::Qr)
            ->sum(fn ($s) => Money::toCents($s->amount));
        $withdrawals = $this->withdrawals->forSession($session)
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
    public function close(CashSession $session, string $counted, User $by, ?CarbonInterface $at = null): CashSession
    {
        if (Money::toCents($counted) < 0) { // RN-11
            throw new DomainException('El efectivo contado no puede ser negativo.');
        }

        return DB::transaction(function () use ($session, $counted, $by) {
            $locked = $this->sessions->lockForUpdate($session->id);

            if (! $locked->isOpen()) {
                throw new DomainException('La caja ya está cerrada.');
            }

            $summary = $this->summary($locked, $counted);

            return $this->sessions->markClosed($locked, [
                'status' => SessionStatus::Closed,
                'closed_at' => $at ?? now(),
                'closed_by' => $by->id,
                'counted_cash' => Money::fromCents($summary->countedCash),
                'expected_cash' => Money::fromCents($summary->expectedCash),
                'difference' => Money::fromCents($summary->difference),
            ]);
        });
    }
}
