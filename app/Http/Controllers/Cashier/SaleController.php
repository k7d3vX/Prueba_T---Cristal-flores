<?php

namespace App\Http\Controllers\Cashier;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use App\Services\CashSessionService;
use App\Services\SaleService;
use App\Support\Money;
use DomainException;
use Illuminate\Http\RedirectResponse;

class SaleController extends Controller
{
    public function __construct(private SaleService $service, private CashSessionService $shifts) {}

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $session = $this->shifts->activeFor($request->user());

        if ($session === null) {
            return back()->withErrors(['session' => 'No tienes un turno abierto.']);
        }

        try {
            $this->service->register(
                $session,
                $request->validated('amount'),
                PaymentMethod::from($request->validated('method')),
            );
        } catch (DomainException $e) {
            return back()->withErrors(['session' => $e->getMessage()]);
        }

        return back()->with('status', 'Venta '.Money::format(Money::toCents($request->validated('amount'))).' registrada.');
    }
}
