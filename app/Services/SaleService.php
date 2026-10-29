<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\CashSession;
use App\Models\Sale;
use App\Repositories\Contracts\SaleRepositoryInterface;
use Carbon\CarbonInterface;
use App\Support\Money;
use DomainException;

class SaleService
{
    public function __construct(private SaleRepositoryInterface $sales, private AuditLogger $audit) {}

    /** RF-2: registra una venta en una caja abierta (RN-7 / AC-03). */
    public function register(CashSession $session, string $amount, PaymentMethod $method, ?CarbonInterface $at = null): Sale
    {
        if (Money::toCents($amount) <= 0) { // RN-11
            throw new DomainException('El monto debe ser mayor que cero.');
        }

        if (! $session->isOpen()) {
            throw new DomainException('El turno no está abierto: no se pueden registrar ventas.');
        }

        $sale = $this->sales->create($session, $amount, $method, $at);
        $this->audit->record('venta', 'Venta '.mb_strtolower($method->label() === 'QR' ? 'QR' : 'en efectivo').' '.Money::format(Money::toCents($amount)), $session);

        return $sale;
    }
}
