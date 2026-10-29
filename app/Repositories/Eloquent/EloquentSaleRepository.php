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

    public function recentForSession(CashSession $session, int $limit): Collection
    {
        return $session->sales()->whereNull('voided_at')->orderByDesc('id')->limit($limit)->get();
    }

    public function findOrFail(int|string $id): Sale
    {
        return Sale::query()->with('cashSession')->findOrFail($id);
    }

    public function update(Sale $movement, array $attributes): Sale
    {
        $movement->update($attributes);

        return $movement;
    }

    public function pendingVoidRequests(): Collection
    {
        return Sale::query()
            ->with(['cashSession.cashRegister', 'cashSession.cashier'])
            ->whereNotNull('void_requested_at')
            ->whereNull('voided_at')
            ->orderBy('void_requested_at')
            ->get();
    }
}
