<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CashSession;
use App\Repositories\Contracts\CashSessionRepositoryInterface;
use App\Support\Alert;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Alertas para detectar robo, no solo medir (RN-30). Umbrales en config/caja.php.
 * Solo se calculan para la dueña; el cajero nunca las recibe (AC-29).
 */
class AlertService
{
    public function __construct(
        private CashSessionRepositoryInterface $sessions,
        private CashClosingService $closing,
    ) {}

    /** @return Collection<int, Alert> */
    public function forOwner(): Collection
    {
        $tolerance = $this->closing->toleranceCents();
        $pending = $this->sessions->pendingReview();
        $open = $this->sessions->activeSessions()->filter(fn ($s) => $s->isOpen());

        $repeated = config('caja.repeated_shortages');
        $accumulated = config('caja.accumulated_shortage');
        $window = max($repeated['window_days'], $accumulated['days']);
        $closed = $this->sessions->closedSince(now()->subDays($window));

        return collect()
            ->concat($this->overTolerance($pending, $tolerance))
            ->concat($this->repeatedShortages($closed, $tolerance, $repeated))
            ->concat($this->accumulatedShortages($closed, $tolerance, $accumulated))
            ->concat($this->highWithdrawals($open->concat($pending)))
            ->concat($this->unreviewed($pending))
            ->concat($this->longTurns($open))
            ->concat($this->manyVoids($open->concat($pending)))
            ->values();
    }

    /** (1) Cierre sin revisar con |diferencia| mayor que la tolerancia. */
    private function overTolerance(Collection $pending, int $tolerance): Collection
    {
        return $pending
            ->filter(fn ($s) => abs(Money::toCents($s->difference)) > $tolerance)
            ->map(function ($s) {
                $diff = Money::toCents($s->difference);

                return new Alert(
                    $diff < 0 ? 'high' : 'warn',
                    ($diff < 0 ? 'Faltante' : 'Sobrante').' de '.Money::format(abs($diff)).' sobre la tolerancia en '.$s->cashRegister->name.' · '.$s->cashier->name.' (turno #'.$s->id.').',
                    $s->id,
                );
            });
    }

    /** (2) ≥ N faltantes sobre la tolerancia en los últimos M turnos del mismo cajero. */
    private function repeatedShortages(Collection $closed, int $tolerance, array $rule): Collection
    {
        $since = now()->subDays($rule['window_days']);

        return $closed
            ->filter(fn ($s) => $s->closed_at >= $since)
            ->groupBy('cashier_id')
            ->map(function (Collection $turns) use ($tolerance, $rule) {
                $shortages = $turns->take($rule['turns'])
                    ->filter(fn ($s) => Money::toCents($s->difference) < -$tolerance)
                    ->count();

                return $shortages >= $rule['count']
                    ? new Alert('high', "Faltantes repetidos: {$turns->first()->cashier->name} tiene {$shortages} faltantes sobre la tolerancia en sus últimos {$rule['turns']} turnos.")
                    : null;
            })
            ->filter()
            ->values();
    }

    /** (3) Faltantes pequeños (dentro de la tolerancia) que juntos superan el límite (AR-29). */
    private function accumulatedShortages(Collection $closed, int $tolerance, array $rule): Collection
    {
        $since = now()->subDays($rule['days']);
        $limit = Money::toCents($rule['limit']);

        return $closed
            ->filter(fn ($s) => $s->closed_at >= $since)
            ->filter(function ($s) use ($tolerance) {
                $diff = Money::toCents($s->difference);

                return $diff < 0 && abs($diff) <= $tolerance;
            })
            ->groupBy('cashier_id')
            ->map(function (Collection $turns) use ($limit, $rule) {
                $total = $turns->sum(fn ($s) => abs(Money::toCents($s->difference)));

                return $total > $limit
                    ? new Alert('warn', "Faltantes pequeños acumulados: {$turns->first()->cashier->name} suma ".Money::format($total)." en {$turns->count()} turnos de los últimos {$rule['days']} días.")
                    : null;
            })
            ->filter()
            ->values();
    }

    /** (4) Salidas > X % de las ventas en efectivo del turno. */
    private function highWithdrawals(Collection $sessions): Collection
    {
        $percent = (int) config('caja.high_withdrawals_percent');

        return $sessions->map(function (CashSession $s) use ($percent) {
            $summary = $this->closing->summary($s);

            return $summary->cashSales > 0 && $summary->withdrawals * 100 > $summary->cashSales * $percent
                ? new Alert('warn', 'Salidas altas: '.Money::format($summary->withdrawals).' es más del '.$percent.' % de las ventas en efectivo ('.Money::format($summary->cashSales).') en '.$s->cashRegister->name.' · '.$s->cashier->name.' (turno #'.$s->id.').', $s->id)
                : null;
        })->filter()->values();
    }

    /** (5) Cierre pendiente de revisión más de N horas. */
    private function unreviewed(Collection $pending): Collection
    {
        $limit = now()->subHours((int) config('caja.review_hours'));

        return $pending
            ->filter(fn ($s) => $s->closed_at < $limit)
            ->map(fn ($s) => new Alert('warn', 'Cierre sin revisar hace más de '.config('caja.review_hours').' hr: '.$s->cashRegister->name.' · '.$s->cashier->name.' (turno #'.$s->id.').', $s->id))
            ->values();
    }

    /** (6) Turno abierto más de N horas. */
    private function longTurns(Collection $open): Collection
    {
        $limit = now()->subHours((int) config('caja.long_turn_hours'));

        return $open
            ->filter(fn ($s) => $s->opened_at < $limit)
            ->map(fn ($s) => new Alert('warn', 'Turno largo: '.$s->cashRegister->name.' · '.$s->cashier->name.' lleva abierto más de '.config('caja.long_turn_hours').' hr (turno #'.$s->id.').', $s->id))
            ->values();
    }

    /** (7) Más de N anulaciones en un turno. */
    private function manyVoids(Collection $sessions): Collection
    {
        $max = (int) config('caja.max_voids');

        return $sessions->map(function (CashSession $s) use ($max) {
            $report = $this->closing->report($s);
            $voids = $report->sales->filter->isVoided()->count() + $report->withdrawals->filter->isVoided()->count();

            return $voids > $max
                ? new Alert('warn', "Anulaciones: {$voids} en el turno #{$s->id} de {$s->cashier->name} (más de {$max}).", $s->id)
                : null;
        })->filter()->values();
    }
}
