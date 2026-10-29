<?php

namespace App\Repositories\Contracts;

use App\Enums\PaymentMethod;
use App\Models\CashSession;
use App\Models\Sale;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface SaleRepositoryInterface
{
    public function create(CashSession $session, string $amount, PaymentMethod $method, ?CarbonInterface $at = null): Sale;

    public function forSession(CashSession $session, bool $newestFirst = false): Collection;
}
