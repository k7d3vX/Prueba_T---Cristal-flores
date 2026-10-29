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
    public function __construct(private SaleRepositoryInterface $sales) {}

    /** RF-2: registra una venta en una caja abierta (RN-7 / AC-03). */
    public function register(CashSession $session, string $amount, PaymentMethod $method, ?CarbonInterface $at = null): Sale
    {
        if (Money::toCents($amount) <= 0) { // RN-11
            throw new DomainException('El monto debe ser mayor que cero.');
        }

        if (! $session->isOpen()) {
            throw new DomainException('La caja está cerrada: no se pueden registrar ventas.');
        }

        return $this->sales->create($session, $amount, $method, $at);
    }
}
