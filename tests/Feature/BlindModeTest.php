<?php

namespace Tests\Feature;

use App\Enums\ClosingMode;
use App\Enums\SessionStatus;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** AC-05, AC-06, AC-08: caja ciega vs caja normal. */
class BlindModeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        $this->cashier = User::factory()->cashier()->create();
    }

    /** fondo 300 + efectivo 1.650 − salida 200 → esperado 1.750 */
    private function sessionWithMovements(ClosingMode $mode): CashSession
    {
        $session = CashSession::create([
            'cash_register_id' => CashRegister::firstOrCreate(['name' => 'Caja Principal'])->id,
            'cashier_id' => $this->cashier->id,
            'opened_by' => $this->cashier->id,
            'opening_float' => '300.00',
            'closing_mode' => $mode,
            'status' => SessionStatus::Open,
            'opened_at' => now(),
        ]);
        $session->sales()->create(['amount' => '450.00', 'method' => 'cash']);
        $session->sales()->create(['amount' => '1200.00', 'method' => 'cash']);
        $session->sales()->create(['amount' => '600.00', 'method' => 'qr']);
        $session->withdrawals()->create(['amount' => '200.00', 'reason' => 'Proveedor']);

        return $session;
    }

    public function test_blind_cashier_never_sees_expected_cash_or_totals(): void
    {
        $session = $this->sessionWithMovements(ClosingMode::Blind);

        foreach ([route('cash.show', $session), route('cash.close', $session)] as $url) {
            $this->actingAs($this->cashier)->get($url)
                ->assertOk()
                ->assertDontSee('Esperado')
                ->assertDontSee('Bs 1.750,00')  // esperado
                ->assertDontSee('Bs 1.650,00')  // ventas en efectivo
                ->assertDontSee('Bs 600,00')    // QR
                ->assertDontSee('Diferencia');
        }
    }

    public function test_blind_cashier_closes_and_the_confirmation_has_no_figures(): void
    {
        $session = $this->sessionWithMovements(ClosingMode::Blind);

        $this->actingAs($this->cashier)
            ->post(route('cash.close.store', $session), ['counted_cash' => '1700'])
            ->assertRedirect(route('cash.show', $session));

        $this->actingAs($this->cashier)->get(route('cash.show', $session))
            ->assertOk()
            ->assertSee('Cierre registrado')
            ->assertDontSee('Bs 1.750,00')
            ->assertDontSee('Bs -50,00')
            ->assertDontSee('Faltante');

        $session->refresh();
        $this->assertSame(SessionStatus::Closed, $session->status);
        $this->assertSame('1750.00', $session->expected_cash);
        $this->assertSame('-50.00', $session->difference);
    }

    public function test_cashier_cannot_open_the_summary(): void
    {
        $session = $this->sessionWithMovements(ClosingMode::Blind);
        app(\App\Services\CashClosingService::class)->close($session, '1700', $this->cashier);

        $this->actingAs($this->cashier)->get(route('cash.summary', $session))->assertForbidden();
        $this->actingAs($this->owner)->get(route('cash.summary', $session))
            ->assertOk()
            ->assertSee('Faltante de Bs 50,00');
    }

    public function test_owner_cannot_close_a_blind_session(): void
    {
        $session = $this->sessionWithMovements(ClosingMode::Blind);

        $this->actingAs($this->owner)->get(route('cash.close', $session))->assertForbidden();
        $this->actingAs($this->owner)
            ->post(route('cash.close.store', $session), ['counted_cash' => '1750'])
            ->assertForbidden();
        $this->assertTrue($session->fresh()->isOpen());
    }

    public function test_cashier_cannot_close_a_normal_session(): void
    {
        $session = $this->sessionWithMovements(ClosingMode::Normal);

        $this->actingAs($this->cashier)->get(route('cash.close', $session))->assertForbidden();
        $this->actingAs($this->cashier)
            ->post(route('cash.close.store', $session), ['counted_cash' => '1750'])
            ->assertForbidden();
    }

    public function test_owner_closes_a_normal_session_seeing_the_expected_cash(): void
    {
        $session = $this->sessionWithMovements(ClosingMode::Normal);

        $this->actingAs($this->owner)->get(route('cash.close', $session))
            ->assertOk()
            ->assertSee('Esperado en caja')
            ->assertSee('Bs 1.750,00');

        $this->actingAs($this->owner)
            ->post(route('cash.close.store', $session), ['counted_cash' => '1750'])
            ->assertRedirect(route('cash.summary', $session));

        $this->actingAs($this->owner)->get(route('cash.summary', $session))
            ->assertOk()
            ->assertSee('La caja cuadra');
        $this->assertSame('0.00', $session->fresh()->difference);
    }

    /** Riesgo 1: ninguna pantalla del cajero (caja ciega) revela cifras del cierre, ni siquiera ya cerrada. */
    public function test_blind_cashier_gets_no_figures_in_any_page_of_the_closed_test_day(): void
    {
        $this->seed(); // día de prueba: caja ciega cerrada, esperado 2.250, faltante 70
        $cashier = User::where('email', 'cajero@tienda.test')->firstOrFail();
        $session = CashSession::sole();

        $forbidden = ['Bs 2.250,00', 'Bs 3.050,00', 'Bs 2.150,00', 'Bs 900,00', 'Bs -70,00', 'Bs 70,00',
            'Bs 200,00', 'Faltante', 'Esperado', 'Diferencia'];

        foreach ([route('cash.index'), route('cash.show', $session)] as $url) {
            $response = $this->actingAs($cashier)->get($url)->assertOk();
            foreach ($forbidden as $text) {
                $response->assertDontSee($text);
            }
        }

        $this->actingAs($cashier)->get(route('cash.close', $session))->assertRedirect(route('cash.show', $session));
        $this->actingAs($cashier)->get(route('cash.summary', $session))->assertForbidden();
    }

    /** Riesgo 1, defensa en profundidad: ni siquiera llegan los datos a la vista. */
    public function test_blind_cashier_view_receives_no_summary_and_no_snapshots(): void
    {
        $this->seed();
        $cashier = User::where('email', 'cajero@tienda.test')->firstOrFail();

        $this->actingAs($cashier)->get(route('cash.show', CashSession::sole()))
            ->assertOk()
            ->assertViewHas('showTotals', false)
            ->assertViewHas('summary', fn ($v) => $v === null)
            ->assertViewHas('sales', fn ($v) => $v->isEmpty())
            ->assertViewHas('withdrawals', fn ($v) => $v->isEmpty())
            ->assertViewHas('session', fn ($s) => $s->expected_cash === null
                && $s->counted_cash === null && $s->difference === null);
    }
}
