<?php

namespace Tests\Feature;

use App\Enums\ClosingMode;
use App\Enums\PaymentMethod;
use App\Enums\SessionStatus;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Sale;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\CashClosingService;
use App\Services\SaleService;
use Illuminate\Database\UniqueConstraintViolationException;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** AC-01, AC-02, AC-03: apertura, validaciones y caja cerrada. */
class CashSessionRulesTest extends TestCase
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

    private function register(string $name = 'Caja Principal'): CashRegister
    {
        return CashRegister::firstOrCreate(['name' => $name]);
    }

    private function open(?CashRegister $register = null, ?User $cashier = null, ClosingMode $mode = ClosingMode::Normal): CashSession
    {
        return CashSession::create([
            'cash_register_id' => ($register ?? $this->register())->id,
            'cashier_id' => ($cashier ?? $this->cashier)->id,
            'opened_by' => $this->owner->id,
            'opening_float' => '300.00',
            'closing_mode' => $mode,
            'status' => SessionStatus::Open,
            'opened_at' => now(),
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('cash.index'))->assertRedirect(route('login'));
    }

    public function test_owner_opens_a_cash_session(): void
    {
        $response = $this->actingAs($this->owner)->post(route('cash.store'), [
            'cash_register_id' => $this->register()->id,
            'cashier_id' => $this->cashier->id,
            'opening_float' => '300',
            'closing_mode' => 'blind',
        ]);

        $session = CashSession::sole();
        $response->assertRedirect(route('cash.show', $session));
        $this->assertSame(ClosingMode::Blind, $session->closing_mode);
        $this->assertSame('300.00', $session->opening_float);
        $this->assertTrue($session->isOpen());
    }

    public function test_a_register_cannot_have_two_open_sessions(): void
    {
        $register = $this->register();
        $this->open($register);

        $this->actingAs($this->owner)
            ->post(route('cash.store'), ['cash_register_id' => $register->id, 'cashier_id' => $this->cashier->id, 'opening_float' => '300', 'closing_mode' => 'blind'])
            ->assertSessionHasErrors('open');

        $this->assertSame(1, CashSession::count());
    }

    /** Riesgo 2: aunque falle la validación del servicio, PostgreSQL impide el duplicado. */
    public function test_database_index_blocks_two_open_sessions_in_the_same_register(): void
    {
        $register = $this->register();
        $this->open($register);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->open($register);
    }

    public function test_different_registers_can_be_open_at_the_same_time(): void
    {
        $other = User::factory()->cashier()->create();

        foreach ([[$this->register('Caja Principal'), $this->cashier], [$this->register('Caja 2'), $other]] as [$register, $cashier]) {
            $this->actingAs($this->owner)
                ->post(route('cash.store'), ['cash_register_id' => $register->id, 'cashier_id' => $cashier->id, 'opening_float' => '300', 'closing_mode' => 'normal'])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(2, CashSession::where('status', SessionStatus::Open)->count());
    }

    public function test_closing_a_session_frees_the_register_for_a_new_one(): void
    {
        $register = $this->register();
        $session = $this->open($register);
        app(CashClosingService::class)->close($session, '300', $this->owner);

        $this->open($register); // no lanza: la anterior ya está cerrada
        $this->assertSame(2, CashSession::count());
    }

    public function test_any_owner_has_full_access_and_can_close_a_normal_session(): void
    {
        $secondOwner = User::factory()->owner()->create();
        $session = $this->open();

        $this->actingAs($secondOwner)->get(route('cash.show', $session))->assertOk();
        $this->actingAs($secondOwner)
            ->post(route('cash.close.store', $session), ['counted_cash' => '300'])
            ->assertRedirect(route('cash.summary', $session));
        $this->actingAs($secondOwner)->get(route('cash.summary', $session))->assertOk();
    }

    public function test_a_cashier_only_sees_his_own_sessions(): void
    {
        $ana = User::factory()->cashier()->create(['name' => 'Ana Cajera']);
        $beto = User::factory()->cashier()->create(['name' => 'Beto Cajero']);
        $this->open($this->register('Caja Principal'), $ana);
        $this->open($this->register('Caja 2'), $beto);

        $this->actingAs($ana)->get(route('cash.index'))
            ->assertSee('Ana Cajera')
            ->assertDontSee('Beto Cajero');
    }

    public function test_sale_and_withdrawal_validation(): void
    {
        $session = $this->open();
        $sales = route('cash.sales.store', $session);
        $outs = route('cash.withdrawals.store', $session);

        $this->actingAs($this->cashier)->post($sales, ['amount' => '0', 'method' => 'cash'])->assertSessionHasErrors('amount');
        $this->actingAs($this->cashier)->post($sales, ['amount' => '-5', 'method' => 'cash'])->assertSessionHasErrors('amount');
        $this->actingAs($this->cashier)->post($sales, ['amount' => '10.555', 'method' => 'cash'])->assertSessionHasErrors('amount');
        $this->actingAs($this->cashier)->post($sales, ['amount' => '100', 'method' => 'cheque'])->assertSessionHasErrors('method');
        $this->actingAs($this->cashier)->post($outs, ['amount' => '50', 'reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->cashier)->post($outs, ['amount' => '0', 'reason' => 'x'])->assertSessionHasErrors('amount');

        $this->assertSame(0, Sale::count());
        $this->assertSame(0, Withdrawal::count());
    }

    public function test_valid_sale_and_withdrawal_are_stored(): void
    {
        $session = $this->open();

        $this->actingAs($this->cashier)
            ->post(route('cash.sales.store', $session), ['amount' => '450', 'method' => 'cash'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->cashier)
            ->post(route('cash.withdrawals.store', $session), ['amount' => '200', 'reason' => 'Proveedor de aceite'])
            ->assertSessionHasNoErrors();

        $this->assertSame('450.00', Sale::sole()->amount);
        $this->assertSame('Proveedor de aceite', Withdrawal::sole()->reason);
    }

    public function test_closed_session_rejects_sales_withdrawals_and_a_second_close(): void
    {
        $session = $this->open();
        app(CashClosingService::class)->close($session, '300', $this->owner);

        $this->actingAs($this->owner)
            ->post(route('cash.sales.store', $session), ['amount' => '10', 'method' => 'cash'])
            ->assertSessionHasErrors('session');
        $this->actingAs($this->owner)
            ->post(route('cash.withdrawals.store', $session), ['amount' => '10', 'reason' => 'x'])
            ->assertSessionHasErrors('session');
        $this->actingAs($this->owner)
            ->post(route('cash.close.store', $session), ['counted_cash' => '999'])
            ->assertSessionHasErrors('session');

        $this->assertSame(0, Sale::count());
        $this->assertSame('300.00', $session->fresh()->counted_cash);
    }

    public function test_cashier_cannot_operate_another_cashiers_session(): void
    {
        $session = $this->open();
        $other = User::factory()->cashier()->create();

        $this->actingAs($other)
            ->post(route('cash.sales.store', $session), ['amount' => '10', 'method' => 'cash'])
            ->assertForbidden();
        $this->actingAs($other)->get(route('cash.show', $session))->assertForbidden();
    }

    // ---- Bug: salidas mayores al efectivo y montos negativos ----

    public function test_withdrawal_cannot_exceed_the_cash_in_the_drawer(): void
    {
        $session = $this->open(); // fondo 300
        $sales = route('cash.sales.store', $session);
        $outs = route('cash.withdrawals.store', $session);

        $this->actingAs($this->owner)->post($sales, ['amount' => '2000', 'method' => 'cash']); // hay 2.300 en caja

        $this->actingAs($this->owner)->post($outs, ['amount' => '5000', 'reason' => 'Proveedor'])
            ->assertSessionHasErrors(['amount' => 'La salida supera el efectivo disponible en la caja (Bs 2.300,00).']);
        $this->assertSame(0, Withdrawal::count());

        $this->actingAs($this->owner)->post($outs, ['amount' => '2300', 'reason' => 'Proveedor'])
            ->assertSessionHasNoErrors(); // justo lo que hay: permitido
        $this->actingAs($this->owner)->post($outs, ['amount' => '0.01', 'reason' => 'Otro'])
            ->assertSessionHasErrors('amount'); // ya no queda nada

        $this->assertSame(1, Withdrawal::count());
        $this->assertSame(0, app(CashClosingService::class)->summary($session)->expectedCash); // nunca negativo
    }

    public function test_qr_sales_do_not_count_as_cash_for_withdrawals(): void
    {
        $session = $this->open(); // fondo 300
        $this->actingAs($this->owner)->post(route('cash.sales.store', $session), ['amount' => '1000', 'method' => 'qr']);

        $this->actingAs($this->owner)
            ->post(route('cash.withdrawals.store', $session), ['amount' => '400', 'reason' => 'Proveedor'])
            ->assertSessionHasErrors('amount');
    }

    public function test_blind_cashier_gets_a_withdrawal_error_without_figures(): void
    {
        $session = $this->open(null, null, ClosingMode::Blind);

        $this->actingAs($this->cashier)
            ->post(route('cash.withdrawals.store', $session), ['amount' => '5000', 'reason' => 'Proveedor'])
            ->assertSessionHasErrors(['amount' => 'La salida supera el efectivo disponible en la caja.']);
    }

    public function test_negative_or_malformed_amounts_are_rejected_everywhere(): void
    {
        $session = $this->open();

        foreach (['-5', '-0.01', '1e3', '12.345', 'abc', '0'] as $bad) {
            $this->actingAs($this->owner)->post(route('cash.sales.store', $session), ['amount' => $bad, 'method' => 'cash'])
                ->assertSessionHasErrors('amount');
            $this->actingAs($this->owner)->post(route('cash.withdrawals.store', $session), ['amount' => $bad, 'reason' => 'x'])
                ->assertSessionHasErrors('amount');
        }

        foreach (['-1', '-0.5', '1e3'] as $bad) {
            $this->actingAs($this->owner)->post(route('cash.close.store', $session), ['counted_cash' => $bad])
                ->assertSessionHasErrors('counted_cash');
        }
        $this->actingAs($this->owner)->post(route('cash.store'), [
            'cash_register_id' => $this->register('Caja 2')->id, 'cashier_id' => $this->cashier->id,
            'opening_float' => '-300', 'closing_mode' => 'normal',
        ])->assertSessionHasErrors('opening_float');

        $this->assertSame(0, Sale::count());
        $this->assertSame(0, Withdrawal::count());
        $this->assertTrue($session->fresh()->isOpen());
        $this->assertSame(1, CashSession::count());
    }

    public function test_services_reject_negative_amounts_even_without_http(): void
    {
        $session = $this->open();

        $this->expectException(DomainException::class);
        app(SaleService::class)->register($session, '-5.00', PaymentMethod::Cash);
    }

    public function test_service_rejects_a_negative_count_and_a_negative_float(): void
    {
        $session = $this->open();

        try {
            app(CashClosingService::class)->close($session, '-1.00', $this->owner);
            $this->fail('Debió rechazar el conteo negativo');
        } catch (DomainException) {
            $this->assertTrue($session->fresh()->isOpen());
        }

        $this->expectException(DomainException::class);
        app(\App\Services\CashSessionService::class)
            ->open($this->owner, $this->cashier->id, $this->register('Caja 2')->id, '-300.00', ClosingMode::Normal);
    }

    // ---- Monto inicial secuencial y resumen del turno anterior ----

    public function test_next_turn_proposes_the_previous_float_and_the_owner_sees_how_it_went(): void
    {
        $session = CashSession::create([
            'cash_register_id' => $this->register()->id, 'cashier_id' => $this->cashier->id, 'opened_by' => $this->owner->id,
            'opening_float' => '250.00', 'closing_mode' => ClosingMode::Normal, 'status' => SessionStatus::Open,
            'opened_at' => now()->subHours(5),
        ]);
        $this->actingAs($this->owner)->post(route('cash.sales.store', $session), ['amount' => '100', 'method' => 'cash']);
        app(CashClosingService::class)->close($session, '350', $this->owner, $session->opened_at->copy()->addMinutes(214));

        $this->actingAs($this->owner)->get(route('cash.index'))
            ->assertOk()
            ->assertSee('value="250.00"', false)             // monto inicial secuencial
            ->assertSee('turno anterior')
            ->assertSeeInOrder(['Se inició con', 'Bs 250,00', 'se ganó', 'Bs 100,00', 'en total', 'Bs 350,00'])
            ->assertSee('3 hr 34 min');                      // tiempo trabajado

        // El cajero recibe el fondo propuesto, pero NO las cifras del turno anterior
        $this->actingAs($this->cashier)->get(route('cash.index'))
            ->assertOk()
            ->assertSee('value="250.00"', false)
            ->assertDontSee('turno anterior')
            ->assertDontSee('Bs 100,00');
    }

    public function test_first_turn_of_a_register_proposes_300(): void
    {
        $this->register();

        $this->actingAs($this->owner)->get(route('cash.index'))->assertSee('value="300.00"', false);
    }
}
