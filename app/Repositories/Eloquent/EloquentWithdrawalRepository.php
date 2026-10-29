<?php

namespace App\Repositories\Eloquent;

use App\Enums\WithdrawalCategory;
use App\Models\CashSession;
use App\Models\Withdrawal;
use App\Repositories\Contracts\WithdrawalRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EloquentWithdrawalRepository implements WithdrawalRepositoryInterface
{
    public function create(CashSession $session, string $amount, string $reason, ?CarbonInterface $at = null, WithdrawalCategory $category = WithdrawalCategory::Other): Withdrawal
    {
        $attributes = ['amount' => $amount, 'reason' => $reason, 'category' => $category];

        if ($at !== null) {
            $attributes['created_at'] = $at;
        }

        return $session->withdrawals()->create($attributes);
    }

    public function forSession(CashSession $session, bool $newestFirst = false): Collection
    {
        return $session->withdrawals()->orderBy('id', $newestFirst ? 'desc' : 'asc')->get();
    }

    public function recentForSession(CashSession $session, int $limit): Collection
    {
        return $session->withdrawals()->whereNull('voided_at')->orderByDesc('id')->limit($limit)->get();
    }

    public function findOrFail(int|string $id): Withdrawal
    {
        return Withdrawal::query()->with('cashSession')->findOrFail($id);
    }

    public function update(Withdrawal $movement, array $attributes): Withdrawal
    {
        $movement->update($attributes);

        return $movement;
    }

    public function pendingVoidRequests(): Collection
    {
        return Withdrawal::query()
            ->with(['cashSession.cashRegister', 'cashSession.cashier'])
            ->whereNotNull('void_requested_at')
            ->whereNull('voided_at')
            ->orderBy('void_requested_at')
            ->get();
    }
}
