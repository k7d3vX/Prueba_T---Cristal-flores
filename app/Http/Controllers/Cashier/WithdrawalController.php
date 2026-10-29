<?php

namespace App\Http\Controllers\Cashier;

use App\Enums\WithdrawalCategory;
use App\Exceptions\InsufficientCashException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWithdrawalRequest;
use App\Services\CashSessionService;
use App\Services\WithdrawalService;
use App\Support\Money;
use DomainException;
use Illuminate\Http\RedirectResponse;

class WithdrawalController extends Controller
{
    public function __construct(private WithdrawalService $service, private CashSessionService $shifts) {}

    public function store(StoreWithdrawalRequest $request): RedirectResponse
    {
        $session = $this->shifts->activeFor($request->user());

        if ($session === null) {
            return back()->withErrors(['session' => 'No tienes un turno abierto.']);
        }

        try {
            $this->service->register(
                $session,
                $request->validated('amount'),
                $request->validated('reason'),
                category: WithdrawalCategory::tryFrom((string) $request->validated('category')) ?? WithdrawalCategory::Other,
            );
        } catch (InsufficientCashException $e) {
            // El cajero nunca se entera del saldo, en ningún modo (RN-16): mensaje sin cifras.
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        } catch (DomainException $e) {
            return back()->withErrors(['session' => $e->getMessage()]);
        }

        return back()->with('status', 'Salida '.Money::format(Money::toCents($request->validated('amount'))).' registrada.');
    }
}
