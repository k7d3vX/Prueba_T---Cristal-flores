<?php

namespace Tests\Feature;

use App\Enums\ClosingMode;
use App\Enums\PaymentMethod;
use App\Enums\ReviewStatus;
use App\Enums\SessionStatus;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Sale;
use App\Models\User;
use App\Services\CashSessionService;
use App\Services\SaleService;
use App\Services\WithdrawalService;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Fase 4: tolerancia, alertas e historial (AC-26 a AC-29; AR-28, AR-29). */
class AlertsAndHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $ana;
    private User $beto;
    private CashRegister $register;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        $this->ana = User::factory()->cashier()->create(['name' => 'Ana Cajera']);
        $this->beto = User::factory()->cashier()->create(['name' => 'Beto Cajero']);
        $this->register = CashRegister::create(['name' => 'Caja Principal']);
    }

    /** Turno cerrado: fondo 300 + efectivo 700 = esperado 1.000; el conteo se desvía `$difference`. */
    private function closedTurn(User $cashier, string $difference, ?CarbonInterface $closedAt = null, ReviewStatus $review = ReviewStatus::Pending, ?CashRegister $register = null): CashSession
    {
        $closedAt ??= now()->subHours(2);
        $session = CashSession::create([
            'cash_register_id' => ($register ?? $this->register)->id,
            'cashier_id' => $cashier->id,
            'opened_by' => $this->owner->id,
            'opening_float' => '300.00',
            'closing_mode' => ClosingMode::Blind,
            'status' => SessionStatus::Closed,
            'opened_at' => $closedAt->copy()->subHours(8),
            'closed_at' => $closedAt,
            'closed_by' => $cashier->id,
            'counted_cash' => Money::fromCents(100000 + Money::toCents($difference)),
            'expected_cash' => '1000.00',
            'difference' => $difference,
            'review_status' => $review,
        ]);
        $session->sales()->create(['amount' => '700.00', 'method' => 'cash']);

        return $session;
    }

    private function dashboard(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->owner)->get(route('owner.dashboard'))->assertOk();
    }

    // ---- Tolerancia (AC-26, AR-28) ----

    public function test_tolerance_changes_only_the_label(): void
    {
        $inside = $this->closedTurn($this->ana, '-3.00');
        $over = $this->closedTurn($this->ana, '4.00');
        $short = $this->closedTurn($this->ana, '-7.00');

        $this->actingAs($this->owner)->get(route('owner.sessions.show', $inside))
            ->assertSee('Cuadra (diferencia de Bs 3,00, dentro de la tolerancia)')
            ->assertSee('Bs -3,00'); // la diferencia exacta siempre se ve
        $this->actingAs($this->owner)->get(route('owner.sessions.show', $over))->assertSee('Cuadra (diferencia de Bs 4,00');
        $this->actingAs($this->owner)->get(route('owner.sessions.show', $short))->assertSee('Faltante de Bs 7,00');

        $this->assertSame('-3.00', $inside->fresh()->difference); // se guarda exacta
    }

    // ---- Alertas (AC-27) ----

    public function test_shortage_over_the_tolerance_alerts_only_while_unreviewed(): void
    {
        $this->closedTurn($this->ana, '-7.00');
        $this->closedTurn($this->beto, '-3.00');               // dentro de la tolerancia: sin alerta de faltante
        $this->closedTurn($this->beto, '-9.00', null, ReviewStatus::Reviewed); // ya revisado

        $this->dashboard()
            ->assertSee('Faltante de Bs 7,00 sobre la tolerancia')
            ->assertDontSee('Faltante de Bs 3,00 sobre la tolerancia')
            ->assertDontSee('Faltante de Bs 9,00 sobre la tolerancia');
    }

    public function test_repeated_shortages_alert_from_three_in_the_last_seven_turns(): void
    {
        foreach ([1, 2, 3] as $i) {
            $this->closedTurn($this->beto, '-7.00', now()->subDays($i), ReviewStatus::Reviewed);
        }
        foreach ([1, 2] as $i) {
            $this->closedTurn($this->ana, '-7.00', now()->subDays($i), ReviewStatus::Reviewed);
        }

        $this->dashboard()
            ->assertSee('Faltantes repetidos: Beto Cajero tiene 3 faltantes')
            ->assertDontSee('Faltantes repetidos: Ana Cajera');
    }

    public function test_small_shortages_add_up_into_an_alert(): void
    {
        foreach (range(1, 5) as $i) { // 5 × 4 = Bs 20: justo en el límite, sin alerta
            $this->closedTurn($this->ana, '-4.00', now()->subDays($i), ReviewStatus::Reviewed);
        }
        $this->dashboard()->assertDontSee('Faltantes pequeños acumulados');

        $this->closedTurn($this->ana, '-4.00', now()->subDays(6), ReviewStatus::Reviewed); // Bs 24
        $this->dashboard()->assertSee('Faltantes pequeños acumulados: Ana Cajera suma Bs 24,00 en 6 turnos');
    }

    public function test_old_small_shortages_do_not_count(): void
    {
        foreach (range(40, 47) as $i) { // fuera de los 30 días
            $this->closedTurn($this->ana, '-4.00', now()->subDays($i), ReviewStatus::Reviewed);
        }

        $this->dashboard()->assertDontSee('Faltantes pequeños acumulados');
    }

    public function test_high_withdrawals_alert_above_25_percent_of_cash_sales(): void
    {
        $session = app(CashSessionService::class)->openByCashier($this->ana, $this->register->id);
        app(SaleService::class)->register($session, '1000.00', PaymentMethod::Cash);

        app(WithdrawalService::class)->register($session, '250.00', 'Proveedor'); // exactamente 25 %: sin alerta
        $this->dashboard()->assertDontSee('Salidas altas');

        app(WithdrawalService::class)->register($session, '10.00', 'Otro'); // 26 %
        $this->dashboard()->assertSee('Salidas altas');
    }

    public function test_unreviewed_closing_alerts_after_24_hours(): void
    {
        $this->closedTurn($this->ana, '0.00', now()->subHours(5));
        $this->dashboard()->assertDontSee('Cierre sin revisar hace más de');

        $this->closedTurn($this->beto, '0.00', now()->subHours(30));
        $this->dashboard()->assertSee('Cierre sin revisar hace más de 24 hr: Caja Principal · Beto Cajero');
    }

    public function test_a_long_open_turn_alerts_after_12_hours(): void
    {
        $session = app(CashSessionService::class)->openByCashier($this->ana, $this->register->id, now()->subHours(5));
        $this->dashboard()->assertDontSee('Turno largo');

        $session->update(['opened_at' => now()->subHours(13)]);
        $this->dashboard()->assertSee('Turno largo: Caja Principal · Ana Cajera');
    }

    public function test_more_than_two_voids_in_a_turn_alert(): void
    {
        $session = app(CashSessionService::class)->openByCashier($this->ana, $this->register->id);
        foreach (range(1, 3) as $i) {
            app(SaleService::class)->register($session, "{$i}0.00", PaymentMethod::Cash);
        }

        Sale::orderBy('id')->take(2)->get()->each->update(['voided_at' => now(), 'voided_by' => $this->owner->id, 'void_reason' => 'x']);
        $this->dashboard()->assertDontSee('Anulaciones: ');

        Sale::orderBy('id')->get()->each->update(['voided_at' => now(), 'voided_by' => $this->owner->id, 'void_reason' => 'x']);
        $this->dashboard()->assertSee('Anulaciones: 3 en el turno');
    }

    public function test_alerts_have_a_symbol_and_text_and_the_cashier_never_gets_them(): void
    {
        $this->closedTurn($this->ana, '-7.00');
        $this->dashboard()->assertSee('⚠ Atención');

        $session = app(CashSessionService::class)->openByCashier($this->beto, $this->register->id);
        $this->actingAs($this->beto)->get(route('cashier.home'))
            ->assertOk()
            ->assertDontSee('⚠ Atención')
            ->assertDontSee('Faltante')
            ->assertDontSee('tolerancia');
    }

    // ---- Historial (AC-28) ----

    public function test_history_filters_and_summarises_per_cashier(): void
    {
        $caja2 = CashRegister::create(['name' => 'Caja 2']);
        $this->closedTurn($this->ana, '-7.00', now()->subDays(3));
        $this->closedTurn($this->ana, '-10.00', now()->subDays(2));
        $this->closedTurn($this->ana, '0.00', now()->subDays(1), ReviewStatus::Reviewed);
        $this->closedTurn($this->beto, '5.00', now()->subDays(1), ReviewStatus::Flagged, $caja2);

        $page = $this->actingAs($this->owner)->get(route('owner.history'))->assertOk();
        $page->assertSee('Ana Cajera')->assertSee('Beto Cajero')
            ->assertSee('Bs -17,00')   // diferencia total de Ana
            ->assertSee('Bs -5,66')    // promedio de Ana: −17 / 3 turnos
            ->assertSee('▼ Faltante Bs 7,00')
            ->assertSee('✔ Cuadra (Bs 5,00)');

        $this->actingAs($this->owner)->get(route('owner.history', ['cashier_id' => $this->beto->id]))
            ->assertSee('✔ Cuadra (Bs 5,00)')->assertDontSee('Faltante Bs 7,00');

        $this->actingAs($this->owner)->get(route('owner.history', ['cash_register_id' => $caja2->id]))
            ->assertSee('Caja 2')->assertDontSee('Faltante Bs 7,00');

        $this->actingAs($this->owner)->get(route('owner.history', ['review' => 'flagged']))
            ->assertSee('✔ Cuadra (Bs 5,00)')->assertDontSee('Faltante Bs 7,00');

        $this->actingAs($this->owner)->get(route('owner.history', ['from' => now()->subDays(2)->toDateString(), 'to' => now()->subDays(2)->toDateString()]))
            ->assertSee('▼ Faltante Bs 10,00')->assertDontSee('Faltante Bs 7,00');

        $this->actingAs($this->owner)->get(route('owner.history', ['review' => 'inventado']))->assertSessionHasErrors('review');
    }

    public function test_history_flags_the_cashier_with_repeated_shortages(): void
    {
        foreach ([1, 2, 3] as $i) {
            $this->closedTurn($this->ana, '-9.00', now()->subDays($i), ReviewStatus::Reviewed);
        }

        $this->actingAs($this->owner)->get(route('owner.history'))->assertSee('3 ⚠');
    }

    public function test_the_cashier_cannot_open_the_history(): void
    {
        $this->actingAs($this->ana)->get(route('owner.history'))->assertForbidden();
    }
}
