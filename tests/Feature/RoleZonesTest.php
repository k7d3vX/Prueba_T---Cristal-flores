<?php

namespace Tests\Feature;

use App\Enums\ClosingMode;
use App\Models\CashRegister;
use App\Models\User;
use App\Services\CashSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** AC-13 / AR-18: cada rol vive en su zona (/mi-turno para el cajero, /panel para la dueña). */
class RoleZonesTest extends TestCase
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

    /** Llama a todas las rutas cuyo URI empieza con $prefix (con ids reales) y devuelve cuántas llamó. */
    private function callEveryRouteUnder(string $prefix, User $as): int
    {
        $register = CashRegister::firstOrCreate(['name' => 'Caja Principal']);
        $session = app(CashSessionService::class)
            ->openForCashier($this->owner, $this->cashier->id, $register->id, '300.00', ClosingMode::Normal);
        $sale = $session->sales()->create(['amount' => '10.00', 'method' => 'cash']);
        $withdrawal = $session->withdrawals()->create(['amount' => '5.00', 'reason' => 'x']);

        $called = 0;

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), $prefix)) {
                continue;
            }

            $uri = str_replace(['{cashSession}', '{cashRegister}', '{sale}', '{withdrawal}'], [$session->id, $register->id, $sale->id, $withdrawal->id], $route->uri());
            $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD');

            $this->actingAs($as)->call($method, '/'.$uri)->assertForbidden();
            $called++;
        }

        return $called;
    }

    public function test_guests_are_redirected_to_login_from_every_zone(): void
    {
        foreach (['/', '/mi-turno', '/panel'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_each_role_lands_in_its_own_zone(): void
    {
        $this->actingAs($this->owner)->get(route('home'))->assertRedirect(route('owner.dashboard'));
        $this->actingAs($this->cashier)->get(route('home'))->assertRedirect(route('cashier.home'));
    }

    public function test_login_sends_the_user_to_home_which_routes_by_role(): void
    {
        $this->post(route('login'), ['email' => $this->cashier->email, 'password' => 'password'])
            ->assertRedirect(route('home'));
    }

    public function test_cashier_gets_403_on_every_panel_route(): void
    {
        $called = $this->callEveryRouteUnder('panel', $this->cashier);

        $this->assertGreaterThanOrEqual(12, $called, 'El recorrido debe cubrir todas las rutas del panel.');
    }

    public function test_owner_gets_403_on_every_cashier_route(): void
    {
        $called = $this->callEveryRouteUnder('mi-turno', $this->owner);

        $this->assertGreaterThanOrEqual(11, $called);
    }
}
