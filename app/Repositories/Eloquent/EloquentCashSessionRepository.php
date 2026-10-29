<?php

namespace App\Repositories\Eloquent;

use App\Enums\SessionStatus;
use App\Models\CashSession;
use App\Models\User;
use App\Repositories\Contracts\CashSessionRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentCashSessionRepository implements CashSessionRepositoryInterface
{
    public function findOrFail(int|string $id): CashSession
    {
        return CashSession::query()->with(['cashRegister', 'cashier', 'closedBy'])->findOrFail($id);
    }

    public function lockForUpdate(int $id): CashSession
    {
        return CashSession::query()->lockForUpdate()->findOrFail($id);
    }

    public function lastClosedFor(int $registerId): ?CashSession
    {
        return CashSession::query()
            ->where('cash_register_id', $registerId)
            ->where('status', SessionStatus::Closed)
            ->orderByDesc('closed_at')
            ->orderByDesc('id')
            ->first();
    }

    public function existsOpenForRegister(int $registerId): bool
    {
        return CashSession::query()
            ->where('cash_register_id', $registerId)
            ->where('status', SessionStatus::Open)
            ->exists();
    }

    public function openRegisterIds(): array
    {
        return CashSession::query()
            ->where('status', SessionStatus::Open)
            ->pluck('cash_register_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function create(array $attributes): CashSession
    {
        return CashSession::create($attributes);
    }

    public function listFor(User $user): Collection
    {
        return CashSession::query()
            ->with(['cashRegister', 'cashier'])
            ->when($user->isCashier(), fn ($q) => $q->where('cashier_id', $user->id))
            ->latest('id')
            ->get();
    }

    public function markClosed(CashSession $session, array $snapshot): CashSession
    {
        $session->update($snapshot);

        return $session;
    }
}
