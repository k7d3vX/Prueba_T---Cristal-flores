<?php

namespace App\Repositories\Eloquent;

use App\Models\CashSession;
use App\Models\Withdrawal;
use App\Repositories\Contracts\WithdrawalRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EloquentWithdrawalRepository implements WithdrawalRepositoryInterface
{
    public function create(CashSession $session, string $amount, string $reason, ?CarbonInterface $at = null): Withdrawal
    {
        $attributes = ['amount' => $amount, 'reason' => $reason];

        if ($at !== null) {
            $attributes['created_at'] = $at;
        }

        return $session->withdrawals()->create($attributes);
    }

    public function forSession(CashSession $session, bool $newestFirst = false): Collection
    {
        return $session->withdrawals()->orderBy('id', $newestFirst ? 'desc' : 'asc')->get();
    }
}
