<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashSessionController;
use App\Http\Controllers\ClosingController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::redirect('/', '/cash');

    Route::get('/cash', [CashSessionController::class, 'index'])->name('cash.index');
    Route::post('/cash', [CashSessionController::class, 'store'])->name('cash.store');
    Route::get('/cash/{cashSession}', [CashSessionController::class, 'show'])->name('cash.show');

    Route::post('/cash/{cashSession}/sales', [SaleController::class, 'store'])->name('cash.sales.store');
    Route::post('/cash/{cashSession}/withdrawals', [WithdrawalController::class, 'store'])->name('cash.withdrawals.store');

    Route::get('/cash/{cashSession}/close', [ClosingController::class, 'show'])->name('cash.close');
    Route::post('/cash/{cashSession}/close', [ClosingController::class, 'store'])->name('cash.close.store');
    Route::get('/cash/{cashSession}/summary', [ClosingController::class, 'summary'])->name('cash.summary');
});
