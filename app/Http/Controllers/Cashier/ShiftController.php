<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Requests\OpenShiftRequest;
use App\Services\CashSessionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Zona del cajero (/mi-turno): estado del turno, apertura y confirmación del fondo. */
class ShiftController extends Controller
{
    public function __construct(private CashSessionService $service) {}

    public function home(Request $request): View
    {
        return view('cashier.home', ['shift' => $this->service->shiftFor($request->user())]);
    }

    /** Abre una caja libre con el fondo habitual; el cajero no elige fondo ni modo (AR-17). */
    public function open(OpenShiftRequest $request): RedirectResponse
    {
        try {
            $this->service->openByCashier($request->user(), (int) $request->validated('cash_register_id'));
        } catch (DomainException $e) {
            return back()->withErrors(['open' => $e->getMessage()]);
        }

        return redirect()->route('cashier.home')->with('status', 'Turno abierto.');
    }

    public function confirmFloat(Request $request): RedirectResponse
    {
        return $this->withActiveSession($request, function ($session) use ($request) {
            $this->service->confirmFloat($session, $request->user());

            return 'Fondo confirmado. Ya puedes registrar ventas.';
        });
    }

    public function disputeFloat(Request $request): RedirectResponse
    {
        return $this->withActiveSession($request, function ($session) use ($request) {
            $this->service->disputeFloat($session, $request->user());

            return 'Avisamos a la dueña que el fondo no coincide.';
        });
    }

    private function withActiveSession(Request $request, callable $action): RedirectResponse
    {
        $session = $this->service->activeFor($request->user());

        if ($session === null) {
            return back()->withErrors(['session' => 'No tienes un turno por confirmar.']);
        }

        try {
            $message = $action($session);
        } catch (DomainException $e) {
            return back()->withErrors(['session' => $e->getMessage()]);
        }

        return redirect()->route('cashier.home')->with('status', $message);
    }
}
