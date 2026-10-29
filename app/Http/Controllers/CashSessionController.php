<?php

namespace App\Http\Controllers;

use App\Enums\ClosingMode;
use App\Http\Requests\OpenCashSessionRequest;
use App\Models\CashSession;
use App\Services\CashSessionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CashSessionController extends Controller
{
    public function __construct(private CashSessionService $service) {}

    public function index(Request $request): View
    {
        $registers = $this->service->availableRegisters();

        return view('cash.index', [
            'sessions' => $this->service->listFor($request->user()),
            'cashiers' => $this->service->cashiers(),
            'registers' => $registers,
            'suggestions' => $this->service->openingSuggestions($registers, $request->user()),
        ]);
    }

    public function store(OpenCashSessionRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $session = $this->service->open(
                by: $request->user(),
                cashierId: (int) $data['cashier_id'],
                registerId: (int) $data['cash_register_id'],
                openingFloat: $data['opening_float'],
                mode: ClosingMode::from($data['closing_mode']),
            );
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['open' => $e->getMessage()]);
        }

        return redirect()->route('cash.show', $session)->with('status', 'Caja abierta.');
    }

    public function show(Request $request, CashSession $cashSession): View
    {
        Gate::authorize('view', $cashSession);

        $details = $this->service->details($cashSession, $request->user());

        return view('cash.show', [
            'session' => $details->session,
            'showTotals' => $details->showTotals,
            'summary' => $details->summary,
            'sales' => $details->sales,
            'withdrawals' => $details->withdrawals,
            'canClose' => $details->canClose,
        ]);
    }
}
