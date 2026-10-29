<?php

namespace Tests\Feature;

use App\Enums\ClosingMode;
use App\Enums\PaymentMethod;
use App\Enums\SessionStatus;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Sale;
use App\Models\User;
use App\Repositories\Contracts\CashRegisterRepositoryInterface;
use App\Repositories\Contracts\CashSessionRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WithdrawalRepositoryInterface;
use App\Repositories\Eloquent\EloquentCashRegisterRepository;
use App\Repositories\Eloquent\EloquentCashSessionRepository;
use App\Repositories\Eloquent\EloquentSaleRepository;
use App\Repositories\Eloquent\EloquentUserRepository;
use App\Repositories\Eloquent\EloquentWithdrawalRepository;
use App\Services\CashSessionService;
use App\Services\SaleService;
use App\Services\WithdrawalService;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/** Capas: Controller → Service → Repository (interfaz). */
class LayeringTest extends TestCase
{
    use RefreshDatabase;

    private function register(): CashRegister
    {
        return CashRegister::firstOrCreate(['name' => 'Caja Principal']);
    }

    public function test_every_repository_interface_is_bound_to_its_eloquent_implementation(): void
    {
        $this->assertInstanceOf(EloquentCashRegisterRepository::class, app(CashRegisterRepositoryInterface::class));
        $this->assertInstanceOf(EloquentCashSessionRepository::class, app(CashSessionRepositoryInterface::class));
        $this->assertInstanceOf(EloquentSaleRepository::class, app(SaleRepositoryInterface::class));
        $this->assertInstanceOf(EloquentWithdrawalRepository::class, app(WithdrawalRepositoryInterface::class));
        $this->assertInstanceOf(EloquentUserRepository::class, app(UserRepositoryInterface::class));
    }

    public function test_service_hides_totals_from_a_blind_cashier_but_not_from_the_owner(): void
    {
        $owner = User::factory()->owner()->create();
        $cashier = User::factory()->cashier()->create();
        $cash = app(CashSessionService::class);

        $session = $cash->open($owner, $cashier->id, $this->register()->id, '300.00', ClosingMode::Blind);
        app(SaleService::class)->register($session, '450.00', PaymentMethod::Cash);
        app(WithdrawalService::class)->register($session, '50.00', 'Proveedor');

        $forCashier = $cash->details($session, $cashier);
        $this->assertFalse($forCashier->showTotals);
        $this->assertNull($forCashier->summary);
        $this->assertCount(0, $forCashier->sales);
        $this->assertTrue($forCashier->canClose);

        $forOwner = $cash->details($session, $owner);
        $this->assertTrue($forOwner->showTotals);
        $this->assertSame(70000, $forOwner->summary->expectedCash); // 300 + 450 − 50
        $this->assertCount(1, $forOwner->sales);
        $this->assertFalse($forOwner->canClose); // en caja ciega no cierra la dueña
    }

    public function test_opening_a_second_session_throws_a_domain_exception(): void
    {
        $owner = User::factory()->owner()->create();
        $cashier = User::factory()->cashier()->create();
        $cash = app(CashSessionService::class);

        $register = $this->register();
        $cash->open($owner, $cashier->id, $register->id, '300.00', ClosingMode::Normal);

        $this->expectException(DomainException::class);
        $cash->open($owner, $cashier->id, $register->id, '300.00', ClosingMode::Normal);
    }

    /** Ventaja de la interfaz: el servicio se prueba con un repositorio falso, sin tocar la BD. */
    public function test_sale_service_works_with_a_fake_repository(): void
    {
        $fake = new class implements SaleRepositoryInterface
        {
            public array $created = [];

            public function create(CashSession $session, string $amount, PaymentMethod $method, ?CarbonInterface $at = null): Sale
            {
                $this->created[] = [$amount, $method];

                return new Sale(['amount' => $amount, 'method' => $method]);
            }

            public function forSession(CashSession $session, bool $newestFirst = false): Collection
            {
                return collect();
            }
        };
        $service = new SaleService($fake);

        $service->register(new CashSession(['status' => SessionStatus::Open]), '450.00', PaymentMethod::Cash);
        $this->assertSame([['450.00', PaymentMethod::Cash]], $fake->created);

        $this->expectException(DomainException::class);
        $service->register(new CashSession(['status' => SessionStatus::Closed]), '10.00', PaymentMethod::Qr);
    }
}
