<?php

namespace Tests\Feature;

use App\Enums\ClosingMode;
use App\Enums\SessionStatus;
use App\Models\CashSession;
use App\Models\Sale;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\CashClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** AC-04, AC-07, AC-09: el día de prueba real de la tienda. */
class ClosingTestDayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(); // php artisan db:seed → usuarios + día de prueba
    }

    public function test_seeder_loads_the_test_day_already_closed(): void
    {
        $session = CashSession::sole();

        $this->assertSame(ClosingMode::Blind, $session->closing_mode);
        $this->assertSame(SessionStatus::Closed, $session->status);
        $this->assertSame('Caja Principal', $session->cashRegister->name);
        $this->assertSame('300.00', $session->opening_float);
        $this->assertSame(5, Sale::count());
        $this->assertSame(1, Withdrawal::count());

        // snapshots guardados al cerrar (RN-8)
        $this->assertSame('2250.00', $session->expected_cash);
        $this->assertSame('2180.00', $session->counted_cash);
        $this->assertSame('-70.00', $session->difference);
    }

    public function test_closing_of_the_test_day_is_a_shortage_of_70_not_1170(): void
    {
        $summary = app(CashClosingService::class)->summary(CashSession::sole());

        $this->assertSame(305000, $summary->totalSales);   // Bs 3.050
        $this->assertSame(215000, $summary->cashSales);    // Bs 2.150
        $this->assertSame(90000, $summary->qrSales);       // Bs 900
        $this->assertSame(20000, $summary->withdrawals);   // Bs 200
        $this->assertSame(225000, $summary->expectedCash); // 300 + 2.150 − 200
        $this->assertSame(218000, $summary->countedCash);
        $this->assertSame(-7000, $summary->difference);    // faltante Bs 70
        $this->assertSame('short', $summary->verdict);

        // La cuenta de la dueña (1.170) se explica: 900 QR + 200 salida + 70 real
        $this->assertSame(117000, $summary->naiveGap);
        $this->assertSame(117000, $summary->qrSales + $summary->withdrawals + $summary->realShortage);
    }

    public function test_owner_sees_the_closing_summary_with_reconciliation(): void
    {
        $owner = User::where('email', 'owner@tienda.test')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('cash.summary', CashSession::sole()))
            ->assertOk()
            ->assertSee('Faltante de Bs 70,00')
            ->assertSee('Bs 3.050,00')   // total vendido
            ->assertSee('Bs 2.250,00')   // esperado en caja
            ->assertSee('Bs 2.180,00')   // contado
            ->assertSee('Bs 1.170,00')   // la cuenta de la dueña
            ->assertSee('Bs -900,00')    // QR no entra al cajón
            ->assertSee('Bs -200,00');   // salida al proveedor
    }

    public function test_summary_shows_the_owner_claim_and_the_earned_started_total_and_worked_time(): void
    {
        $owner = User::where('email', 'owner@tienda.test')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('cash.summary', CashSession::sole()))
            ->assertOk()
            ->assertSee('Vendí Bs 3.050, más los Bs 300 de cambio son Bs 3.350, y el cajero contó Bs 2.180. ¡Me faltan Bs 1.170!')
            ->assertSeeInOrder(['Se ganó', 'Bs 3.050,00', 'Se inició con', 'Bs 300,00', 'En total', 'Bs 3.350,00'])
            ->assertSeeInOrder(['Turno de', '08:00', 'a', '20:00', 'Tiempo trabajado', '12 hr 0 min']);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed();

        $this->assertSame(1, CashSession::count());
        $this->assertSame(5, Sale::count());
        $this->assertSame(2, User::count());
    }
}
