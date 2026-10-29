<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\VoidMovementRequest;
use App\Models\Sale;
use App\Models\Withdrawal;
use App\Services\VoidService;
use DomainException;
use Illuminate\Http\RedirectResponse;

/** Solo la dueña anula, con motivo. Nada se borra (RN-22). */
class VoidController extends Controller
{
    public function __construct(private VoidService $service) {}

    public function sale(VoidMovementRequest $request, Sale $sale): RedirectResponse
    {
        return $this->run(fn () => $this->service->voidSale($sale, $request->user(), $request->validated('reason')), 'Venta anulada.');
    }

    public function withdrawal(VoidMovementRequest $request, Withdrawal $withdrawal): RedirectResponse
    {
        return $this->run(fn () => $this->service->voidWithdrawal($withdrawal, $request->user(), $request->validated('reason')), 'Salida anulada.');
    }

    private function run(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return back()->withErrors(['void' => $e->getMessage()]);
        }

        return back()->with('status', $message);
    }
}
