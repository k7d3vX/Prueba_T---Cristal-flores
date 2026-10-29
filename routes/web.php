<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Cashier;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Owner;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', HomeController::class)->name('home');

    // Zona del CAJERO: opera siempre SU turno (no hay ids de turno en la URL).
    Route::prefix('mi-turno')->name('cashier.')->middleware('role:cashier')->group(function () {
        Route::get('/', [Cashier\ShiftController::class, 'home'])->name('home');
        Route::post('/abrir', [Cashier\ShiftController::class, 'open'])->name('open');
        Route::post('/fondo/confirmar', [Cashier\ShiftController::class, 'confirmFloat'])->name('float.confirm');
        Route::post('/fondo/no-coincide', [Cashier\ShiftController::class, 'disputeFloat'])->name('float.dispute');

        Route::post('/ventas', [Cashier\SaleController::class, 'store'])->name('sales.store');
        Route::post('/salidas', [Cashier\WithdrawalController::class, 'store'])->name('withdrawals.store');

        Route::post('/ventas/{sale}/anulacion', [Cashier\VoidRequestController::class, 'sale'])->name('sales.void-request');
        Route::post('/salidas/{withdrawal}/anulacion', [Cashier\VoidRequestController::class, 'withdrawal'])->name('withdrawals.void-request');

        Route::get('/cierre', [Cashier\ClosingController::class, 'show'])->name('close');
        Route::post('/cierre/revisar', [Cashier\ClosingController::class, 'review'])->name('close.review');
        Route::post('/cierre', [Cashier\ClosingController::class, 'store'])->name('close.store');
    });

    // Zona de la DUEÑA: todo lo que lleva cifras vive aquí. Un test recorre estas rutas con un cajero (403).
    Route::prefix('panel')->name('owner.')->middleware('role:owner')->group(function () {
        Route::get('/', Owner\DashboardController::class)->name('dashboard');

        Route::post('/turnos', [Owner\SessionController::class, 'store'])->name('sessions.store');
        Route::get('/turnos/{cashSession}', [Owner\SessionController::class, 'show'])->name('sessions.show');
        Route::patch('/turnos/{cashSession}/fondo', [Owner\SessionController::class, 'adjustFloat'])->name('sessions.float');
        Route::get('/turnos/{cashSession}/cerrar', [Owner\ClosingController::class, 'show'])->name('close');
        Route::post('/turnos/{cashSession}/cerrar', [Owner\ClosingController::class, 'store'])->name('close.store');

        Route::post('/turnos/{cashSession}/revision', [Owner\ReviewController::class, 'store'])->name('sessions.review');
        Route::post('/ventas/{sale}/anular', [Owner\VoidController::class, 'sale'])->name('sales.void');
        Route::post('/salidas/{withdrawal}/anular', [Owner\VoidController::class, 'withdrawal'])->name('withdrawals.void');
        Route::get('/historial', Owner\HistoryController::class)->name('history');
        Route::get('/bitacora', Owner\AuditLogController::class)->name('audit-log');

        Route::patch('/cajas/{cashRegister}/fondo', [Owner\SessionController::class, 'registerFloat'])->name('registers.float');
    });
});
