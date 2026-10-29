<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CashSession;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/** Bitácora (RN-24): quién hizo qué y cuándo. El usuario es el autenticado; sin sesión (seeder) queda «Sistema». */
class AuditLogger
{
    public function __construct(private AuditLogRepositoryInterface $logs) {}

    public function record(string $action, string $detail = '', ?CashSession $session = null, ?User $by = null): void
    {
        $this->logs->create(($by ?? Auth::user())?->id, $action, $detail, $session?->id);
    }

    public function latest(int $limit = 200): Collection
    {
        return $this->logs->latest($limit);
    }
}
