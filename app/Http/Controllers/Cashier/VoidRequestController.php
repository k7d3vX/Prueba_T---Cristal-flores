<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Requests\VoidMovementRequest;
use App\Models\Sale;
use App\Models\Withdrawal;
use App\Services\VoidService;
use DomainException;
use Illuminate\Http\RedirectResponse;

/** El cajero no anula: solo avisa a la dueña con una nota (RN-23). */
class VoidRequestController extends Controller
{
    public function __construct(private VoidService $service) {}

    public function sale(VoidMovementRequest $request, Sale $sale): RedirectResponse
    {
        return $this->run(fn () => $this->service->requestSaleVoid($sale, $request->user(), $request->validated('reason')));
    }

    public function withdrawal(VoidMovementRequest $request, Withdrawal $withdrawal): RedirectResponse
    {
        return $this->run(fn () => $this->service->requestWithdrawalVoid($withdrawal, $request->user(), $request->validated('reason')));
    }

    private function run(callable $action): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return back()->withErrors(['void' => $e->getMessage()]);
        }

        return back()->with('status', 'Avisamos a la dueña. El registro sigue contando hasta que ella lo resuelva.');
    }
}
