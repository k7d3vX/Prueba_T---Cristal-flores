<?php

namespace App\Http\Controllers;

use App\Http\Requests\CloseCashSessionRequest;
use App\Models\CashSession;
use App\Services\CashClosingService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ClosingController extends Controller
{
    public function __construct(private CashClosingService $closing) {}

    /** Pantalla para contar el efectivo. Normal: con esperado. Ciega: sin ningún dato (RN-5, RN-6). */
    public function show(Request $request, CashSession $cashSession): View|RedirectResponse
    {
        Gate::authorize('view', $cashSession);

        if (! $cashSession->isOpen()) {
            return redirect()->route('cash.show', $cashSession);
        }

        abort_unless($this->closing->canClose($cashSession, $request->user()), 403);

        return view('cash.close', [
            'session' => $cashSession,
            'summary' => $cashSession->isBlind() ? null : $this->closing->summary($cashSession),
        ]);
    }

    public function store(CloseCashSessionRequest $request, CashSession $cashSession): RedirectResponse
    {
        Gate::authorize('view', $cashSession);

        if (! $cashSession->isOpen()) { // RN-7
            return redirect()->route('cash.show', $cashSession)
                ->withErrors(['session' => 'La caja ya estaba cerrada.']);
        }

        abort_unless($this->closing->canClose($cashSession, $request->user()), 403);

        try {
            $this->closing->close($cashSession, $request->validated('counted_cash'), $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['session' => $e->getMessage()]);
        }

        // La dueña va al resumen; el cajero (caja ciega) solo ve una confirmación sin cifras.
        return $request->user()->isOwner()
            ? redirect()->route('cash.summary', $cashSession)
            : redirect()->route('cash.show', $cashSession)->with('status', 'Cierre registrado. La dueña lo revisará.');
    }

    /** Resumen del cierre: solo la dueña (AC-08). */
    public function summary(CashSession $cashSession): View|RedirectResponse
    {
        Gate::authorize('summary', $cashSession);

        if ($cashSession->isOpen()) {
            return redirect()->route('cash.show', $cashSession);
        }

        $report = $this->closing->report($cashSession);

        return view('cash.summary', [
            'session' => $cashSession,
            'summary' => $report->summary,
            'sales' => $report->sales,
            'withdrawals' => $report->withdrawals,
        ]);
    }
}
