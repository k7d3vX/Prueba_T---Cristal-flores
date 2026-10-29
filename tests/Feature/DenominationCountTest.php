<?php

namespace Tests\Feature;

use App\Enums\ClosingMode;
use App\Enums\PaymentMethod;
use App\Enums\WithdrawalCategory;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\CashSessionService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Fase 3: conteo por denominaciones, revisar antes de enviar, categoría de salida, mostrador (AC-22 a AC-25). */
class DenominationCountTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $cashier;
    private CashSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        $this->cashier = User::factory()->cashier()->create();
        $register = CashRegister::create(['name' => 'Caja Principal']);

        // fondo 300 + efectivo 1.650 = esperado 1.950 (ciega)
        $this->session = app(CashSessionService::class)->openByCashier($this->cashier, $register->id);
        app(SaleService::class)->register($this->session, '1650.00', PaymentMethod::Cash);
    }

    // ---- AC-22, AR-25, AR-26 ----

    public function test_server_computes_the_total_from_the_breakdown(): void
    {
        $this->actingAs($this->cashier)->post(route('cashier.close.store'), [
            'counts' => ['b200' => 9, 'b100' => '1', 'b50' => 1, 'c050' => 0, 'b20' => ''],
            'counted_cash' => '999999', // el navegador no manda: lo ignora
        ])->assertSessionHasNoErrors();

        $this->session->refresh();
        $this->assertSame('1950.00', $this->session->counted_cash);       // 9×200 + 1×100 + 1×50
        $this->assertSame(['b200' => 9, 'b100' => 1, 'b50' => 1], $this->session->count_breakdown);
        $this->assertSame('0.00', $this->session->difference);
    }

    public function test_invalid_counts_are_rejected(): void
    {
        foreach ([['b200' => -1], ['b200' => '1.5'], ['b200' => 'x'], ['b200' => 10000], ['b500' => 1]] as $bad) {
            $this->actingAs($this->cashier)->post(route('cashier.close.store'), ['counts' => $bad])
                ->assertSessionHasErrors();
        }

        $this->assertTrue($this->session->fresh()->isOpen());
    }

    public function test_an_empty_sheet_without_a_total_is_rejected_and_a_direct_total_still_works(): void
    {
        $this->actingAs($this->cashier)->post(route('cashier.close.store'), ['counts' => ['b200' => '', 'b100' => '']])
            ->assertSessionHasErrors();
        $this->assertTrue($this->session->fresh()->isOpen());

        $this->actingAs($this->cashier)->post(route('cashier.close.store'), ['counts' => ['b200' => ''], 'counted_cash' => '1900'])
            ->assertSessionHasNoErrors();
        $this->assertSame('1900.00', $this->session->fresh()->counted_cash);
        $this->assertNull($this->session->fresh()->count_breakdown);
    }

    // ---- AC-23, AR-27 ----

    public function test_reviewing_the_count_does_not_close_the_turn(): void
    {
        $response = $this->actingAs($this->cashier)->post(route('cashier.close.review'), [
            'counts' => ['b200' => 9, 'b100' => 1],
        ]);

        $response->assertOk()
            ->assertSee('Contaste Bs 1.900,00')
            ->assertSee('Una vez enviado no podrás cambiarlo')
            ->assertSee('Volver a contar')
            ->assertSee('Enviar cierre')
            ->assertDontSee('Esperado')
            ->assertDontSee('Diferencia')
            ->assertDontSee('Faltante');

        $this->assertTrue($this->session->fresh()->isOpen());
        $this->assertNull($this->session->fresh()->counted_cash);
    }

    public function test_the_count_sheet_has_every_denomination(): void
    {
        $this->actingAs($this->cashier)->get(route('cashier.close'))
            ->assertOk()
            ->assertSee('name="counts[b200]"', false)
            ->assertSee('name="counts[c050]"', false)
            ->assertSee('Billetes')
            ->assertSee('Monedas');
    }

    // ---- Dueña: planilla con diferencia en vivo y desglose en la auditoría (AC-25) ----

    public function test_the_owner_closes_a_normal_turn_with_the_sheet_and_the_audit_shows_the_breakdown(): void
    {
        $this->session->update(['closing_mode' => ClosingMode::Normal]);

        $this->actingAs($this->owner)->get(route('owner.close', $this->session))
            ->assertOk()
            ->assertSee('data-expected="195000"', false)
            ->assertSee('Diferencia');

        $this->actingAs($this->owner)
            ->post(route('owner.close.store', $this->session), ['counts' => ['b200' => 9, 'b100' => 1, 'b50' => 1]])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->get(route('owner.sessions.show', $this->session))
            ->assertSee('Desglose del conteo')
            ->assertSee('Billetes de 200');
    }

    // ---- AC-24 ----

    public function test_withdrawals_carry_a_category_that_defaults_to_other(): void
    {
        $post = fn (array $extra) => $this->actingAs($this->cashier)
            ->post(route('cashier.withdrawals.store'), ['amount' => '100', 'reason' => 'Aceite'] + $extra);

        $post(['category' => 'supplier'])->assertSessionHasNoErrors();
        $post([])->assertSessionHasNoErrors();
        $post(['category' => 'robo'])->assertSessionHasErrors('category');

        $this->assertSame([WithdrawalCategory::Supplier, WithdrawalCategory::Other], Withdrawal::orderBy('id')->get()->pluck('category')->all());

        $this->actingAs($this->owner)->get(route('owner.sessions.show', $this->session))
            ->assertSee('Proveedor');
    }

    // ---- AC-25 ----

    public function test_the_operation_screen_has_the_keypad_and_the_category_choices(): void
    {
        $this->actingAs($this->cashier)->get(route('cashier.home'))
            ->assertOk()
            ->assertSee('data-keypad', false)
            ->assertSee('name="category"', false)
            ->assertSee('Proveedor')
            ->assertSee('Servicios')
            ->assertSee('Adelanto');
    }
}
