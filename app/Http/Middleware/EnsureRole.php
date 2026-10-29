<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** `role:owner` o `role:cashier`: cada zona (/panel, /mi-turno) solo admite su rol; el otro recibe 403 (RN-17). */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        abort_unless($request->user()?->role?->value === $role, 403);

        return $next($request);
    }
}
