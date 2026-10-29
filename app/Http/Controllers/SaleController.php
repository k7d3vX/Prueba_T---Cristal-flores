<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\StoreSaleRequest;
use App\Models\CashSession;
use App\Services\SaleService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SaleController extends Controller
{
    public function __construct(private SaleService $service) {}

    public function store(StoreSaleRequest $request, CashSession $cashSession): RedirectResponse
    {
        Gate::authorize('operate', $cashSession);

        try {
            $this->service->register(
                $cashSession,
                $request->validated('amount'),
                PaymentMethod::from($request->validated('method')),
            );
        } catch (DomainException $e) {
            return back()->withErrors(['session' => $e->getMessage()]);
        }

        return back()->with('status', 'Venta registrada.');
    }
}
