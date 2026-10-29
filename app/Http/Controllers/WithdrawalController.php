<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientCashException;
use App\Http\Requests\StoreWithdrawalRequest;
use App\Models\CashSession;
use App\Services\WithdrawalService;
use App\Support\Money;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class WithdrawalController extends Controller
{
    public function __construct(private WithdrawalService $service) {}

    public function store(StoreWithdrawalRequest $request, CashSession $cashSession): RedirectResponse
    {
        Gate::authorize('operate', $cashSession);

        try {
            $this->service->register(
                $cashSession,
                $request->validated('amount'),
                $request->validated('reason'),
            );
        } catch (InsufficientCashException $e) {
            // En caja ciega el cajero no debe enterarse del saldo: mensaje sin cifras (RN-5).
            $canSeeFigures = $request->user()->isOwner() || ! $cashSession->isBlind();
            $message = $canSeeFigures
                ? 'La salida supera el efectivo disponible en la caja ('.Money::format($e->available).').'
                : $e->getMessage();

            return back()->withInput()->withErrors(['amount' => $message]);
        } catch (DomainException $e) {
            return back()->withErrors(['session' => $e->getMessage()]);
        }

        return back()->with('status', 'Salida registrada.');
    }
}
