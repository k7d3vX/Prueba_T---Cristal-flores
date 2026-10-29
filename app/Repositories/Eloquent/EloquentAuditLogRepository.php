<?php

namespace App\Repositories\Eloquent;

use App\Models\AuditLog;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentAuditLogRepository implements AuditLogRepositoryInterface
{
    public function create(?int $userId, string $action, string $detail, ?int $sessionId): AuditLog
    {
        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'detail' => $detail,
            'cash_session_id' => $sessionId,
        ]);
    }

    public function latest(int $limit): Collection
    {
        return AuditLog::query()->with('user')->orderByDesc('id')->limit($limit)->get();
    }
}
