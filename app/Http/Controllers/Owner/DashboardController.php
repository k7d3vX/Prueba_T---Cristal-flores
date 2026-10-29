<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Services\CashSessionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Panel de la dueña: cajas, turnos activos, abrir turno e historial. */
class DashboardController extends Controller
{
    public function __construct(private CashSessionService $service) {}

    public function __invoke(Request $request): View
    {
        return view('owner.dashboard', ['dashboard' => $this->service->dashboard($request->user())]);
    }
}
