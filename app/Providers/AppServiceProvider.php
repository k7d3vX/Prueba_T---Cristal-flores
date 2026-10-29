<?php

namespace App\Providers;

use App\Repositories\Contracts\CashRegisterRepositoryInterface;
use App\Repositories\Contracts\CashSessionRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WithdrawalRepositoryInterface;
use App\Repositories\Eloquent\EloquentCashRegisterRepository;
use App\Repositories\Eloquent\EloquentCashSessionRepository;
use App\Repositories\Eloquent\EloquentSaleRepository;
use App\Repositories\Eloquent\EloquentUserRepository;
use App\Repositories\Eloquent\EloquentWithdrawalRepository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** Interfaz → implementación. Cambiar de Eloquent a otra fuente solo toca esta lista. */
    public array $bindings = [
        CashRegisterRepositoryInterface::class => EloquentCashRegisterRepository::class,
        CashSessionRepositoryInterface::class => EloquentCashSessionRepository::class,
        SaleRepositoryInterface::class => EloquentSaleRepository::class,
        WithdrawalRepositoryInterface::class => EloquentWithdrawalRepository::class,
        UserRepositoryInterface::class => EloquentUserRepository::class,
    ];

    public function boot(): void
    {
        // {cashSession} en las rutas se resuelve por el repositorio, no con Eloquent directo.
        Route::bind('cashSession', fn (string $id) => app(CashSessionRepositoryInterface::class)->findOrFail($id));
    }
}
