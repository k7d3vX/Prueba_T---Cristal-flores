<?php

namespace App\Http\Controllers\Owner;

use App\Enums\ClosingMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustSessionFloatRequest;
use App\Http\Requests\OpenCashSessionRequest;
use App\Http\Requests\RegisterFloatRequest;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Services\CashClosingService;
use App\Services\CashSessionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function __construct(private CashSessionService $service, private CashClosingService $closing) {}

    /** La dueña abre el turno: queda por confirmar hasta que el cajero reciba el fondo. */
    public function store(OpenCashSessionRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $session = $this->service->openForCashier(
                owner: $request->user(),
                cashierId: (int) $data['cashier_id'],
                registerId: (int) $data['cash_register_id'],
                openingFloat: $data['opening_float'],
                mode: ClosingMode::from($data['closing_mode']),
            );
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['open' => $e->getMessage()]);
        }

        return redirect()->route('owner.sessions.show', $session)->with('status', 'Turno abierto: falta que el cajero confirme el fondo.');
    }

    /** Detalle del turno (por confirmar, abierto o cerrado). Aquí sí hay cifras: es la zona de la dueña. */
    public function show(CashSession $cashSession): View
    {
        $report = $this->closing->report($cashSession);

        return view('owner.session', [
            'session' => $cashSession,
            'summary' => $report->summary,
            'timeline' => $report->timeline(),
            'toleranceCents' => $this->closing->toleranceCents(),
            // RN-22: se puede anular en un turno abierto o cerrado que aún no está Revisado.
            'canVoid' => $cashSession->isOpen() || ($cashSession->isClosed() && $cashSession->review_status !== \App\Enums\ReviewStatus::Reviewed),
            'canClose' => $this->closing->canClose($cashSession, request()->user()),
        ]);
    }

    public function adjustFloat(AdjustSessionFloatRequest $request, CashSession $cashSession): RedirectResponse
    {
        try {
            $this->service->adjustFloat($cashSession, $request->validated('opening_float'));
        } catch (DomainException $e) {
            return back()->withErrors(['float' => $e->getMessage()]);
        }

        return back()->with('status', 'Fondo corregido: el cajero debe confirmarlo de nuevo.');
    }

    public function registerFloat(RegisterFloatRequest $request, CashRegister $cashRegister): RedirectResponse
    {
        $this->service->setRegisterFloat($cashRegister->id, $request->validated('default_float'));

        return back()->with('status', 'Fondo habitual de '.$cashRegister->name.' actualizado.');
    }
}
