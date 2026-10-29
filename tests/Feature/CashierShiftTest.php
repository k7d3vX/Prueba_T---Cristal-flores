<?php

namespace Tests\Feature;

use App\Enums\ClosingMode;
use App\Enums\PaymentMethod;
use App\Enums\SessionStatus;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Sale;
use App\Models\User;
use App\Services\CashSessionService;
use App\Services\SaleService;
use App\Services\WithdrawalService;
use App\Support\CashierShift;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Fase 1: apertura en dos pasos, el cajero no ve cifras (AC-10, 11, 12, 14, 15; AR-16, 17, 20). */
class CashierShiftTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $cashier;
    private CashRegister $register;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        $this->cashier = User::factory()->cashier()->create();
        $this->register = CashRegister::firstOrCreate(['name' => 'Caja Principal']);
    }

    private function ownerOpens(ClosingMode $mode, string $float = '300'): CashSession
    {
        $this->actingAs($this->owner)->post(route('owner.sessions.store'), [
            'cash_register_id' => $this->register->id,
            'cashier_id' => $this->cashier->id,
            'opening_float' => $float,
            'closing_mode' => $mode->value,
        ])->assertSessionHasNoErrors();

        return CashSession::latest('id')->firstOrFail();
    }

    /** Turno ya confirmado, con fondo 300, efectivo 450 + 1.200, QR 600 y salida 200. */
    private function confirmedTurnWithMovements(ClosingMode $mode): CashSession
    {
        $session = $this->ownerOpens($mode);
        $this->actingAs($this->cashier)->post(route('cashier.float.confirm'))->assertSessionHasNoErrors();
        $session->refresh(); // ahora está abierto

        $sales = app(SaleService::class);
        $sales->register($session, '450.00', PaymentMethod::Cash);
        $sales->register($session, '1200.00', PaymentMethod::Cash);
        $sales->register($session, '600.00', PaymentMethod::Qr);
        app(WithdrawalService::class)->register($session, '200.00', 'Proveedor');

        return $session;
    }

    // ---- Apertura en dos pasos (AC-10) ----

    public function test_owner_opens_a_pending_session_and_the_cashier_confirms_the_float(): void
    {
        $session = $this->ownerOpens(ClosingMode::Normal);

        $this->assertSame(SessionStatus::PendingConfirmation, $session->status);
        $this->assertSame($this->owner->id, $session->opened_by);
        $this->assertFalse($session->isOpen());

        $this->actingAs($this->cashier)->get(route('cashier.home'))
            ->assertOk()
            ->assertSee('¿Los recibiste?')
            ->assertSee('Bs 300,00');

        $this->actingAs($this->cashier)->post(route('cashier.float.confirm'))->assertRedirect(route('cashier.home'));

        $session->refresh();
        $this->assertTrue($session->isOpen());
        $this->assertNotNull($session->float_confirmed_at);
    }

    public function test_pending_session_does_not_accept_sales(): void
    {
        $this->ownerOpens(ClosingMode::Blind);

        $this->actingAs($this->cashier)
            ->post(route('cashier.sales.store'), ['amount' => '10', 'method' => 'cash'])
            ->assertSessionHasErrors('session');
        $this->assertSame(0, Sale::count());

        $this->expectException(DomainException::class);
        app(SaleService::class)->register(CashSession::sole(), '10.00', PaymentMethod::Cash);
    }

    public function test_cashier_can_dispute_the_float_and_the_owner_corrects_it(): void
    {
        $session = $this->ownerOpens(ClosingMode::Blind, '300');

        $this->actingAs($this->cashier)->post(route('cashier.float.dispute'))->assertRedirect(route('cashier.home'));
        $session->refresh();
        $this->assertNotNull($session->float_disputed_at);
        $this->assertFalse($session->isOpen()); // sigue por confirmar

        $this->actingAs($this->owner)->get(route('owner.dashboard'))->assertSee('no coincide');

        $this->actingAs($this->owner)
            ->patch(route('owner.sessions.float', $session), ['opening_float' => '250'])
            ->assertSessionHasNoErrors();
        $session->refresh();
        $this->assertSame('250.00', $session->opening_float);
        $this->assertNull($session->float_disputed_at);

        $this->actingAs($this->cashier)->post(route('cashier.float.confirm'));
        $this->assertTrue($session->fresh()->isOpen());
    }

    public function test_owner_cannot_change_the_float_of_an_open_session(): void
    {
        $session = $this->ownerOpens(ClosingMode::Blind);
        $this->actingAs($this->cashier)->post(route('cashier.float.confirm'));

        $this->actingAs($this->owner)
            ->patch(route('owner.sessions.float', $session), ['opening_float' => '999'])
            ->assertSessionHasErrors('float');
        $this->assertSame('300.00', $session->fresh()->opening_float);
    }

    // ---- El cajero abre una caja libre (AC-11, AR-17) ----

    public function test_cashier_opens_a_free_register_with_the_register_float_in_blind_mode(): void
    {
        $this->register->update(['default_float' => '350.00']);

        $this->actingAs($this->cashier)->get(route('cashier.home'))
            ->assertOk()
            ->assertSee('Caja Principal')
            ->assertSee('Bs 350,00');

        $this->actingAs($this->cashier)
            ->post(route('cashier.open'), ['cash_register_id' => $this->register->id])
            ->assertRedirect(route('cashier.home'));

        $session = CashSession::sole();
        $this->assertTrue($session->isOpen());
        $this->assertTrue($session->isBlind());
        $this->assertSame('350.00', $session->opening_float);
        $this->assertSame($this->cashier->id, $session->cashier_id);
        $this->assertNotNull($session->float_confirmed_at);
    }

    public function test_cashier_cannot_choose_float_or_mode_when_opening(): void
    {
        $this->actingAs($this->cashier)->post(route('cashier.open'), [
            'cash_register_id' => $this->register->id,
            'opening_float' => '1.00',
            'closing_mode' => 'normal',
            'cashier_id' => User::factory()->cashier()->create()->id,
        ]);

        $session = CashSession::sole();
        $this->assertSame('300.00', $session->opening_float); // el habitual, no el enviado
        $this->assertSame(ClosingMode::Blind, $session->closing_mode);
        $this->assertSame($this->cashier->id, $session->cashier_id);
    }

    public function test_owner_float_becomes_the_register_habitual_float(): void
    {
        $this->ownerOpens(ClosingMode::Normal, '250');

        $this->assertSame('250.00', $this->register->fresh()->default_float);
    }

    public function test_owner_can_set_the_habitual_float_of_a_register(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('owner.registers.float', $this->register), ['default_float' => '400'])
            ->assertSessionHasNoErrors();

        $this->assertSame('400.00', $this->register->fresh()->default_float);

        $this->actingAs($this->owner)
            ->patch(route('owner.registers.float', $this->register), ['default_float' => '-1'])
            ->assertSessionHasErrors('default_float');
    }

    // ---- Un turno activo por cajero (AC-14, AR-20) ----

    public function test_a_cashier_cannot_have_two_active_sessions(): void
    {
        $other = CashRegister::create(['name' => 'Caja 2']);
        $this->actingAs($this->cashier)->post(route('cashier.open'), ['cash_register_id' => $this->register->id]);

        $this->actingAs($this->cashier)
            ->post(route('cashier.open'), ['cash_register_id' => $other->id])
            ->assertSessionHasErrors('open');

        $this->actingAs($this->owner)->post(route('owner.sessions.store'), [
            'cash_register_id' => $other->id, 'cashier_id' => $this->cashier->id,
            'opening_float' => '300', 'closing_mode' => 'blind',
        ])->assertSessionHasErrors('open');

        $this->assertSame(1, CashSession::count());
    }

    public function test_database_index_blocks_two_active_sessions_for_the_same_cashier(): void
    {
        app(CashSessionService::class)->openForCashier($this->owner, $this->cashier->id, $this->register->id, '300.00', ClosingMode::Blind);
        $other = CashRegister::create(['name' => 'Caja 2']);

        $this->expectException(UniqueConstraintViolationException::class);
        CashSession::create([
            'cash_register_id' => $other->id, 'cashier_id' => $this->cashier->id, 'opened_by' => $this->owner->id,
            'opening_float' => '300.00', 'closing_mode' => ClosingMode::Blind,
            'status' => SessionStatus::PendingConfirmation, 'opened_at' => now(),
        ]);
    }

    public function test_a_pending_session_blocks_the_register_for_other_cashiers(): void
    {
        $this->ownerOpens(ClosingMode::Blind);
        $beto = User::factory()->cashier()->create();

        $this->actingAs($beto)->get(route('cashier.home'))->assertDontSee('Caja Principal');
        $this->actingAs($beto)
            ->post(route('cashier.open'), ['cash_register_id' => $this->register->id])
            ->assertSessionHasErrors('open');
    }

    // ---- El cajero no ve cifras en ningún modo (AC-12, AR-16) ----

    public function test_cashier_sees_no_figures_on_a_normal_turn(): void
    {
        $this->confirmedTurnWithMovements(ClosingMode::Normal);

        $response = $this->actingAs($this->cashier)->get(route('cashier.home'))->assertOk();
        foreach (['Esperado', 'Total', 'Diferencia', 'Faltante', 'Sobrante', 'Bs 1.650,00', 'Bs 1.750,00', 'Bs 2.250,00', 'Bs 2.250'] as $text) {
            $response->assertDontSee($text);
        }
        $response->assertSee('Bs 450,00'); // sí ve sus propios registros
    }

    public function test_cashier_cannot_close_a_normal_turn(): void
    {
        $this->confirmedTurnWithMovements(ClosingMode::Normal);

        $this->actingAs($this->cashier)->get(route('cashier.close'))->assertForbidden();
        $this->actingAs($this->cashier)->post(route('cashier.close.store'), ['counted_cash' => '1750'])->assertForbidden();
        $this->assertTrue(CashSession::sole()->isOpen());
    }

    public function test_cashier_sees_only_the_last_five_records_without_sums(): void
    {
        $this->ownerOpens(ClosingMode::Blind);
        $this->actingAs($this->cashier)->post(route('cashier.float.confirm'));

        foreach (['11', '12', '13', '14', '15', '16', '17'] as $amount) {
            $this->actingAs($this->cashier)->post(route('cashier.sales.store'), ['amount' => $amount, 'method' => 'cash']);
        }

        $response = $this->actingAs($this->cashier)->get(route('cashier.home'))->assertOk();
        $response->assertSee('Bs 17,00')->assertSee('Bs 13,00');
        $response->assertDontSee('Bs 12,00')->assertDontSee('Bs 11,00'); // fuera de los últimos 5
        $response->assertDontSee('Bs 75,00')->assertDontSee('Bs 98,00')->assertDontSee('Bs 375,00'); // sin sumas ni saldo
    }

    public function test_cashier_view_receives_no_summary_and_no_snapshots(): void
    {
        $this->confirmedTurnWithMovements(ClosingMode::Normal);

        $this->actingAs($this->cashier)->get(route('cashier.home'))
            ->assertOk()
            ->assertViewMissing('summary')
            ->assertViewMissing('session')
            ->assertViewHas('shift', fn (CashierShift $s) => $s->state === 'open'
                && $s->recent->count() === 4
                && ! property_exists($s, 'summary'));
    }

    public function test_cashier_registers_sales_and_withdrawals_and_gets_confirmation_without_totals(): void
    {
        $this->ownerOpens(ClosingMode::Normal);
        $this->actingAs($this->cashier)->post(route('cashier.float.confirm'));

        $this->actingAs($this->cashier)
            ->post(route('cashier.sales.store'), ['amount' => '450', 'method' => 'cash'])
            ->assertSessionHas('status', 'Venta Bs 450,00 registrada.');
        $this->actingAs($this->cashier)
            ->post(route('cashier.withdrawals.store'), ['amount' => '100', 'reason' => 'Proveedor de aceite'])
            ->assertSessionHas('status', 'Salida Bs 100,00 registrada.');

        $this->assertSame('450.00', Sale::sole()->amount);
    }

    public function test_withdrawal_error_never_carries_figures_for_the_cashier(): void
    {
        foreach ([ClosingMode::Normal, ClosingMode::Blind] as $mode) {
            CashSession::query()->delete();
            $session = $this->ownerOpens($mode);
            $this->actingAs($this->cashier)->post(route('cashier.float.confirm'));

            $this->actingAs($this->cashier)
                ->post(route('cashier.withdrawals.store'), ['amount' => '5000', 'reason' => 'Proveedor'])
                ->assertSessionHasErrors(['amount' => 'La salida supera el efectivo disponible en la caja.']);

            $session->update(['status' => SessionStatus::Closed, 'closed_at' => now()]);
        }
    }

    // ---- Cierre del cajero (AC-15) ----

    public function test_cashier_closes_a_blind_turn_once_and_sees_only_a_confirmation(): void
    {
        $session = $this->confirmedTurnWithMovements(ClosingMode::Blind);

        $this->actingAs($this->cashier)->get(route('cashier.close'))
            ->assertOk()
            ->assertDontSee('Esperado')
            ->assertDontSee('Bs 1.750,00');

        $this->actingAs($this->cashier)
            ->post(route('cashier.close.store'), ['counted_cash' => '1700'])
            ->assertRedirect(route('cashier.home'));

        $session->refresh();
        $this->assertSame(SessionStatus::Closed, $session->status);
        $this->assertSame('-50.00', $session->difference);
        $this->assertSame($this->cashier->id, $session->closed_by);

        $this->actingAs($this->cashier)->get(route('cashier.home'))
            ->assertOk()
            ->assertSee('Cierre enviado')
            ->assertDontSee('Faltante')
            ->assertDontSee('Bs -50,00')
            ->assertDontSee('Bs 1.750,00');

        // no puede reenviar ni cambiar el conteo
        $this->actingAs($this->cashier)
            ->post(route('cashier.close.store'), ['counted_cash' => '1750'])
            ->assertSessionHasErrors('session');
        $this->assertSame('1700.00', $session->fresh()->counted_cash);
    }

    public function test_cashier_without_a_turn_gets_an_error_when_registering(): void
    {
        $this->actingAs($this->cashier)
            ->post(route('cashier.sales.store'), ['amount' => '10', 'method' => 'cash'])
            ->assertSessionHasErrors('session');
        $this->actingAs($this->cashier)->get(route('cashier.close'))->assertRedirect(route('cashier.home'));
        $this->assertSame(0, Sale::count());
    }
}
