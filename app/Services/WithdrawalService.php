<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WithdrawalCategory;
use App\Exceptions\InsufficientCashException;
use App\Models\CashSession;
use App\Models\Withdrawal;
use App\Repositories\Contracts\CashSessionRepositoryInterface;
use App\Repositories\Contracts\WithdrawalRepositoryInterface;
use App\Support\Money;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class WithdrawalService
{
    public function __construct(
        private WithdrawalRepositoryInterface $withdrawals,
        private CashSessionRepositoryInterface $sessions,
        private CashClosingService $closing,
        private AuditLogger $audit,
    ) {}

    /**
     * RF-3: registra una salida de plata de una caja abierta.
     * - Monto > 0 (RN-11).
     * - No puede superar el efectivo que hay en la caja: fondo + ventas en efectivo − salidas previas (RN-12).
     *   La fila de la sesión se bloquea para que dos salidas simultáneas no se pasen del efectivo.
     */
    public function register(CashSession $session, string $amount, string $reason, ?CarbonInterface $at = null, WithdrawalCategory $category = WithdrawalCategory::Other): Withdrawal
    {
        $cents = Money::toCents($amount);

        if ($cents <= 0) {
            throw new DomainException('El monto debe ser mayor que cero.');
        }

        return DB::transaction(function () use ($session, $amount, $reason, $at, $cents, $category) {
            $locked = $this->sessions->lockForUpdate($session->id);

            if (! $locked->isOpen()) { // RN-7 / AC-03
                throw new DomainException('El turno no está abierto: no se pueden registrar salidas.');
            }

            $available = $this->closing->summary($locked)->expectedCash; // efectivo hoy en la caja

            if ($cents > $available) {
                throw new InsufficientCashException($available);
            }

            $withdrawal = $this->withdrawals->create($locked, $amount, $reason, $at, $category);
            $this->audit->record('salida', 'Salida '.Money::format($cents).' · '.$category->label().' · '.$reason, $locked);

            return $withdrawal;
        });
    }
}
