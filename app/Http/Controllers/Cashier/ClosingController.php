<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseCashSessionRequest;
use App\Services\CashClosingService;
use App\Services\CashSessionService;
use App\Support\Money;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** El cajero cierra SOLO su turno en caja ciega: cuenta, envía y no ve el resultado (RN-18). */
class ClosingController extends Controller
{
    public function __construct(private CashClosingService $closing, private CashSessionService $shifts) {}

    public function show(Request $request): View|RedirectResponse
    {
        $session = $this->shifts->activeFor($request->user());

        if ($session === null || ! $session->isOpen()) {
            return redirect()->route('cashier.home');
        }

        abort_unless($this->closing->canClose($session, $request->user()), 403);

        return view('cashier.close', ['registerName' => $session->cashRegister->name]);
    }

    /** RN-26: «Contaste Bs X, ¿enviar?». No escribe nada: cerrar exige el POST final. */
    public function review(CloseCashSessionRequest $request): View|RedirectResponse
    {
        $session = $this->shifts->activeFor($request->user());

        if ($session === null || ! $session->isOpen()) {
            return redirect()->route('cashier.home');
        }

        abort_unless($this->closing->canClose($session, $request->user()), 403);

        return view('cashier.review-count', [
            'countedCents' => Money::toCents($request->countedCash()),
            'counts' => $request->breakdown(),
            'countedCash' => $request->countedCash(),
        ]);
    }

    public function store(CloseCashSessionRequest $request): RedirectResponse
    {
        $session = $this->shifts->activeFor($request->user());

        if ($session === null || ! $session->isOpen()) { // RN-7: ya cerrado, no se reenvía ni se cambia
            return back()->withErrors(['session' => 'No tienes un turno abierto para cerrar.']);
        }

        abort_unless($this->closing->canClose($session, $request->user()), 403);

        try {
            $this->closing->close($session, $request->countedCash(), $request->user(), breakdown: $request->breakdown());
        } catch (DomainException $e) {
            return back()->withErrors(['session' => $e->getMessage()]);
        }

        return redirect()->route('cashier.home')->with('status', 'Cierre enviado. La dueña lo revisará.');
    }
}
