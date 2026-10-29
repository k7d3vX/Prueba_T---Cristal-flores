<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseCashSessionRequest;
use App\Models\CashSession;
use App\Services\CashClosingService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** La dueña cierra los turnos en caja NORMAL, viendo lo esperado mientras cuenta (RN-6). */
class ClosingController extends Controller
{
    public function __construct(private CashClosingService $closing) {}

    public function show(Request $request, CashSession $cashSession): View|RedirectResponse
    {
        if (! $cashSession->isOpen()) {
            return redirect()->route('owner.sessions.show', $cashSession);
        }

        abort_unless($this->closing->canClose($cashSession, $request->user()), 403);

        return view('owner.close', [
            'session' => $cashSession,
            'summary' => $this->closing->summary($cashSession),
        ]);
    }

    public function store(CloseCashSessionRequest $request, CashSession $cashSession): RedirectResponse
    {
        if (! $cashSession->isOpen()) { // RN-7
            return redirect()->route('owner.sessions.show', $cashSession)
                ->withErrors(['session' => 'El turno ya estaba cerrado.']);
        }

        abort_unless($this->closing->canClose($cashSession, $request->user()), 403);

        try {
            $this->closing->close($cashSession, $request->countedCash(), $request->user(), breakdown: $request->breakdown());
        } catch (DomainException $e) {
            return back()->withErrors(['session' => $e->getMessage()]);
        }

        return redirect()->route('owner.sessions.show', $cashSession);
    }
}
