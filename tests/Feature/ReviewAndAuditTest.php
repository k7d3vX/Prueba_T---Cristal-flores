<?php

namespace Tests\Feature;

use App\Enums\ClosingMode;
use App\Enums\PaymentMethod;
use App\Enums\ReviewStatus;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Sale;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\CashClosingService;
use App\Services\CashSessionService;
use App\Services\SaleService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Fase 2: revisión, anulación sin borrar, solicitud del cajero y bitácora (AC-16 a AC-21; AR-21 a AR-24). */
class ReviewAndAuditTest extends TestCase
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

        // Turno ciego abierto: fondo 300 · efectivo 450 + 1.200 · QR 600 · salida 200  → esperado 1.750
        $cash = app(CashSessionService::class);
        $register = CashRegister::create(['name' => 'Caja Principal']);
        $this->session = $cash->openForCashier($this->owner, $this->cashier->id, $register->id, '300.00', ClosingMode::Blind);
        $this->session = $cash->confirmFloat($this->session, $this->cashier);
        app(SaleService::class)->register($this->session, '450.00', PaymentMethod::Cash);
        app(SaleService::class)->register($this->session, '1200.00', PaymentMethod::Cash);
        app(SaleService::class)->register($this->session, '600.00', PaymentMethod::Qr);
        app(WithdrawalService::class)->register($this->session, '200.00', 'Proveedor');
    }

    private function closeAs(string $counted = '1700'): void
    {
        $this->actingAs($this->cashier)->post(route('cashier.close.store'), ['counted_cash' => $counted])->assertSessionHasNoErrors();
        $this->session->refresh();
    }

    // ---- Revisión (AC-16, AR-24) ----

    public function test_closing_leaves_the_turn_pending_review_and_the_owner_reviews_it(): void
    {
        $this->closeAs();
        $this->assertSame(ReviewStatus::Pending, $this->session->review_status);

        $this->actingAs($this->owner)->get(route('owner.dashboard'))
            ->assertSee('Pendientes de revisión')
            ->assertSee('Faltante Bs 50,00');

        $this->actingAs($this->owner)
            ->post(route('owner.sessions.review', $this->session), ['decision' => 'reviewed'])
            ->assertSessionHasNoErrors();

        $this->session->refresh();
        $this->assertSame(ReviewStatus::Reviewed, $this->session->review_status);
        $this->assertSame($this->owner->id, $this->session->reviewed_by);
        $this->assertNotNull($this->session->reviewed_at);
    }

    public function test_a_flagged_review_needs_a_comment_and_can_be_resolved_later(): void
    {
        $this->closeAs();
        $url = route('owner.sessions.review', $this->session);

        $this->actingAs($this->owner)->post($url, ['decision' => 'flagged', 'note' => ''])->assertSessionHasErrors('note');
        $this->assertSame(ReviewStatus::Pending, $this->session->fresh()->review_status);

        $this->actingAs($this->owner)->post($url, ['decision' => 'flagged', 'note' => 'Faltante repetido, hablar con Ana'])->assertSessionHasNoErrors();
        $this->assertSame(ReviewStatus::Flagged, $this->session->fresh()->review_status);
        $this->assertSame('Faltante repetido, hablar con Ana', $this->session->fresh()->review_note);

        $this->actingAs($this->owner)->post($url, ['decision' => 'reviewed'])->assertSessionHasNoErrors();
        $this->assertSame(ReviewStatus::Reviewed, $this->session->fresh()->review_status);
    }

    public function test_only_closed_turns_are_reviewed_and_the_cashier_cannot_review(): void
    {
        $this->actingAs($this->owner)
            ->post(route('owner.sessions.review', $this->session), ['decision' => 'reviewed'])
            ->assertSessionHasErrors('review'); // sigue abierto

        $this->actingAs($this->cashier)
            ->post(route('owner.sessions.review', $this->session), ['decision' => 'reviewed'])
            ->assertForbidden();
    }

    public function test_when_the_owner_closes_a_normal_turn_it_is_reviewed_right_away(): void
    {
        $session = CashSession::query()->whereKey($this->session->id)->firstOrFail();
        $session->update(['closing_mode' => ClosingMode::Normal]);

        $this->actingAs($this->owner)->post(route('owner.close.store', $session), ['counted_cash' => '1750']);

        $this->assertSame(ReviewStatus::Reviewed, $session->fresh()->review_status);
    }

    // ---- Anulación (AC-17, AC-18, AR-21, AR-22) ----

    public function test_voiding_keeps_the_record_visible_and_out_of_the_totals(): void
    {
        $sale = Sale::where('amount', '1200.00')->firstOrFail();

        $this->actingAs($this->owner)
            ->post(route('owner.sales.void', $sale), ['reason' => 'Se cobró dos veces'])
            ->assertSessionHasNoErrors();

        $sale->refresh();
        $this->assertTrue($sale->isVoided());
        $this->assertSame($this->owner->id, $sale->voided_by);
        $this->assertSame(1, Sale::where('amount', '1200.00')->count()); // nada se borra

        // fuera de los totales: 300 + 450 − 200 = 550
        $this->assertSame(55000, app(CashClosingService::class)->summary($this->session)->expectedCash);

        $this->actingAs($this->owner)->get(route('owner.sessions.show', $this->session))
            ->assertSee('Anulado: Se cobró dos veces', false)
            ->assertSee('<del>Bs 1.200,00</del>', false);
    }

    public function test_void_needs_a_reason_and_is_blocked_after_review_or_twice(): void
    {
        $sale = Sale::where('amount', '450.00')->firstOrFail();
        $url = route('owner.sales.void', $sale);

        $this->actingAs($this->owner)->post($url, ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertFalse($sale->fresh()->isVoided());

        $this->actingAs($this->cashier)->post($url, ['reason' => 'x'])->assertForbidden();

        $this->actingAs($this->owner)->post($url, ['reason' => 'Error de monto'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post($url, ['reason' => 'otra vez'])->assertSessionHasErrors('void'); // dos veces

        $other = Sale::where('amount', '1200.00')->firstOrFail();
        $this->closeAs('1700');
        $this->actingAs($this->owner)->post(route('owner.sessions.review', $this->session), ['decision' => 'reviewed']);
        $this->actingAs($this->owner)
            ->post(route('owner.sales.void', $other), ['reason' => 'tarde'])
            ->assertSessionHasErrors('void'); // turno ya revisado
        $this->assertFalse($other->fresh()->isVoided());
    }

    public function test_voiding_in_a_closed_turn_recalculates_expected_and_difference(): void
    {
        $this->closeAs('1700'); // esperado 1.750 → −50
        $this->assertSame('-50.00', $this->session->difference);

        // Anular la salida de 200 sube el esperado a 1.950 → diferencia −250
        $this->actingAs($this->owner)
            ->post(route('owner.withdrawals.void', Withdrawal::sole()), ['reason' => 'Era personal'])
            ->assertSessionHasNoErrors();

        $this->session->refresh();
        $this->assertSame('1950.00', $this->session->expected_cash);
        $this->assertSame('-250.00', $this->session->difference);
        $this->assertSame('1700.00', $this->session->counted_cash); // el conteo no cambia
    }

    public function test_voiding_a_cash_sale_cannot_leave_the_expected_cash_negative(): void
    {
        // Sin la venta de 1.200 quedarían 300 + 450 − 200 = 550: permitido. Sin las dos ventas: 300 − 200 = 100 (ok).
        // Una salida mayor que el efectivo restante sí lo bloquea:
        app(WithdrawalService::class)->register($this->session, '1500.00', 'Proveedor grande'); // esperado 250

        $this->actingAs($this->owner)
            ->post(route('owner.sales.void', Sale::where('amount', '1200.00')->firstOrFail()), ['reason' => 'x'])
            ->assertSessionHasErrors('void');
    }

    // ---- Solicitud del cajero (AC-19) ----

    public function test_cashier_requests_a_void_and_the_owner_sees_it(): void
    {
        $sale = Sale::where('amount', '450.00')->firstOrFail();
        $url = route('cashier.sales.void-request', $sale);

        $this->actingAs($this->cashier)->post($url, ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->cashier)->post($url, ['reason' => 'Era 45, no 450'])->assertSessionHasNoErrors();

        $sale->refresh();
        $this->assertFalse($sale->isVoided()); // sigue contando hasta que la dueña decida
        $this->assertSame('Era 45, no 450', $sale->void_request_note);
        $this->assertSame(175000, app(CashClosingService::class)->summary($this->session)->expectedCash); // 300 + 1.650 − 200

        $this->actingAs($this->owner)->get(route('owner.dashboard'))
            ->assertSee('Anulaciones que pidió el cajero')
            ->assertSee('Era 45, no 450');
        $this->actingAs($this->owner)->get(route('owner.sessions.show', $this->session))
            ->assertSee('El cajero pidió anularlo');

        $this->actingAs($this->cashier)->post($url, ['reason' => 'otra vez'])->assertSessionHasErrors('void');
    }

    public function test_cashier_cannot_request_a_void_for_another_turn(): void
    {
        $other = User::factory()->cashier()->create();
        $register = CashRegister::create(['name' => 'Caja 2']);
        $session = app(CashSessionService::class)->openByCashier($other, $register->id);
        app(SaleService::class)->register($session, '99.00', PaymentMethod::Cash);

        $this->actingAs($this->cashier)
            ->post(route('cashier.sales.void-request', Sale::where('amount', '99.00')->firstOrFail()), ['reason' => 'ajeno'])
            ->assertSessionHasErrors('void');
        $this->assertNull(Sale::where('amount', '99.00')->firstOrFail()->void_requested_at);
    }

    public function test_voided_records_leave_the_cashier_recent_list(): void
    {
        $this->actingAs($this->owner)->post(route('owner.sales.void', Sale::where('amount', '450.00')->firstOrFail()), ['reason' => 'x']);

        $this->actingAs($this->cashier)->get(route('cashier.home'))
            ->assertDontSee('Bs 450,00')
            ->assertSee('Bs 1.200,00');
    }

    // ---- Bitácora (AC-20, AR-23) ----

    public function test_every_relevant_action_is_logged(): void
    {
        $this->actingAs($this->owner)->post(route('owner.sales.void', Sale::where('amount', '450.00')->firstOrFail()), ['reason' => 'Error']);
        $this->closeAs();
        $this->actingAs($this->owner)->post(route('owner.sessions.review', $this->session), ['decision' => 'reviewed']);

        $actions = AuditLog::pluck('action')->all();
        foreach (['turno.abierto', 'fondo.confirmado', 'venta', 'salida', 'anulacion', 'cierre', 'revision'] as $expected) {
            $this->assertContains($expected, $actions);
        }

        $this->actingAs($this->owner)->get(route('owner.audit-log'))
            ->assertOk()
            ->assertSee('anulacion')
            ->assertSee($this->cashier->name);
    }

    public function test_login_and_logout_are_logged_and_the_cashier_cannot_read_the_log(): void
    {
        $this->post(route('login'), ['email' => $this->cashier->email, 'password' => 'password']);
        $this->post(route('logout'));

        $this->assertContains('sesion.inicio', AuditLog::pluck('action')->all());
        $this->assertContains('sesion.cierre', AuditLog::pluck('action')->all());

        $this->actingAs($this->cashier)->get(route('owner.audit-log'))->assertForbidden();
    }
}
