<?php

namespace App\Repositories\Contracts;

use App\Models\CashSession;
use App\Models\Withdrawal;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface WithdrawalRepositoryInterface
{
    public function create(CashSession $session, string $amount, string $reason, ?CarbonInterface $at = null): Withdrawal;

    public function forSession(CashSession $session, bool $newestFirst = false): Collection;
}
