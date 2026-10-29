<?php

namespace App\Repositories\Eloquent;

use App\Enums\ReviewStatus;
use App\Enums\SessionStatus;
use App\Models\CashSession;
use App\Models\User;
use App\Repositories\Contracts\CashSessionRepositoryInterface;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EloquentCashSessionRepository implements CashSessionRepositoryInterface
{
    /** Turno activo = por confirmar o abierto (coincide con los índices únicos parciales). */
    private const ACTIVE = [SessionStatus::PendingConfirmation, SessionStatus::Open];

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

    public function lastClosedForCashier(int $cashierId): ?CashSession
    {
        return CashSession::query()
            ->where('cashier_id', $cashierId)
            ->where('status', SessionStatus::Closed)
            ->orderByDesc('closed_at')
            ->orderByDesc('id')
            ->first();
    }

    public function findActiveForCashier(int $cashierId): ?CashSession
    {
        return CashSession::query()
            ->with(['cashRegister'])
            ->where('cashier_id', $cashierId)
            ->whereIn('status', self::ACTIVE)
            ->first();
    }

    public function existsActiveForRegister(int $registerId): bool
    {
        return CashSession::query()
            ->where('cash_register_id', $registerId)
            ->whereIn('status', self::ACTIVE)
            ->exists();
    }

    public function activeSessions(): Collection
    {
        return CashSession::query()
            ->with(['cashRegister', 'cashier'])
            ->whereIn('status', self::ACTIVE)
            ->get();
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

    public function pendingReview(): Collection
    {
        return CashSession::query()
            ->with(['cashRegister', 'cashier'])
            ->where('status', SessionStatus::Closed)
            ->whereIn('review_status', [ReviewStatus::Pending, ReviewStatus::Flagged])
            ->orderBy('closed_at')
            ->get();
    }

    public function closedSince(CarbonInterface $since): Collection
    {
        return CashSession::query()
            ->with(['cashRegister', 'cashier'])
            ->where('status', SessionStatus::Closed)
            ->where('closed_at', '>=', $since)
            ->orderByDesc('closed_at')
            ->get();
    }

    public function history(array $filters): Collection
    {
        return CashSession::query()
            ->with(['cashRegister', 'cashier'])
            ->where('status', SessionStatus::Closed)
            ->when($filters['cashier_id'] ?? null, fn ($q, $v) => $q->where('cashier_id', $v))
            ->when($filters['cash_register_id'] ?? null, fn ($q, $v) => $q->where('cash_register_id', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('closed_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('closed_at', '<=', Carbon::parse($v)->endOfDay()))
            ->when($filters['review'] ?? null, fn ($q, $v) => $q->where('review_status', $v))
            ->orderByDesc('closed_at')
            ->orderByDesc('id')
            ->get();
    }

    public function update(CashSession $session, array $attributes): CashSession
    {
        $session->update($attributes);

        return $session;
    }

    public function markClosed(CashSession $session, array $snapshot): CashSession
    {
        $session->update($snapshot);

        return $session;
    }
}
