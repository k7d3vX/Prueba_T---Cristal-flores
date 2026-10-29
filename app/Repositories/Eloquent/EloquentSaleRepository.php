<?php

namespace App\Repositories\Eloquent;

use App\Enums\PaymentMethod;
use App\Models\CashSession;
use App\Models\Sale;
use App\Repositories\Contracts\SaleRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EloquentSaleRepository implements SaleRepositoryInterface
{
    public function create(CashSession $session, string $amount, PaymentMethod $method, ?CarbonInterface $at = null): Sale
    {
        $attributes = ['amount' => $amount, 'method' => $method];

        if ($at !== null) { // solo el seeder fija la hora; si no, Eloquent usa now()
            $attributes['created_at'] = $at;
        }

        return $session->sales()->create($attributes);
    }

    public function forSession(CashSession $session, bool $newestFirst = false): Collection
    {
        return $session->sales()->orderBy('id', $newestFirst ? 'desc' : 'asc')->get();
    }
}
