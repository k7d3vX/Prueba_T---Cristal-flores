<?php

namespace Database\Seeders;

use App\Enums\ClosingMode;
use App\Enums\PaymentMethod;
use App\Repositories\Contracts\CashRegisterRepositoryInterface;
use App\Repositories\Contracts\CashSessionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\CashClosingService;
use App\Services\CashSessionService;
use App\Services\SaleService;
use App\Services\WithdrawalService;
use Illuminate\Database\Seeder;

/**
 * Día de prueba real de la tienda (spec §7):
 * fondo 300 · efectivo 450, 1.200, 500 · QR 600, 300 · salida 200 (proveedor de aceite)
 * · caja ciega · el cajero contó 2.180  →  esperado 2.250, diferencia −70 (faltante).
 *
 * Se carga por los mismos servicios que usa la app (no escribe tablas a mano).
 */
class TestDaySeeder extends Seeder
{
    public function run(
        CashSessionRepositoryInterface $sessions,
        CashRegisterRepositoryInterface $registers,
        UserRepositoryInterface $users,
        CashSessionService $cash,
        SaleService $sales,
        WithdrawalService $withdrawals,
        CashClosingService $closing,
    ): void {
        if ($sessions->listFor($users->findByEmailOrFail('owner@tienda.test'))->isNotEmpty()) {
            return; // idempotente: no duplica al reiniciar
        }

        $cashier = $users->findByEmailOrFail('cajero@tienda.test');
        $day = now()->startOfDay();

        $session = $cash->open($cashier, $cashier->id, $registers->all()->firstOrFail()->id, '300.00', ClosingMode::Blind, $day->copy()->setTime(8, 0));

        $movements = [
            ['450.00', PaymentMethod::Cash, 9, 30],
            ['600.00', PaymentMethod::Qr, 10, 45],
            ['1200.00', PaymentMethod::Cash, 12, 10],
            ['300.00', PaymentMethod::Qr, 14, 20],
            ['500.00', PaymentMethod::Cash, 16, 5],
        ];

        foreach ($movements as [$amount, $method, $hour, $minute]) {
            $sales->register($session, $amount, $method, $day->copy()->setTime($hour, $minute));
        }

        $withdrawals->register($session, '200.00', 'Pago al proveedor de aceite', $day->copy()->setTime(15, 0));

        $closing->close($session, '2180.00', $cashier, $day->copy()->setTime(20, 0));
    }
}
