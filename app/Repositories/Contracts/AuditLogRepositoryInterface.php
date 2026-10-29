<?php

namespace App\Repositories\Contracts;

use App\Models\AuditLog;
use Illuminate\Support\Collection;

interface AuditLogRepositoryInterface
{
    public function create(?int $userId, string $action, string $detail, ?int $sessionId): AuditLog;

    /** Las últimas acciones, la más nueva primero (con el usuario). */
    public function latest(int $limit): Collection;
}
