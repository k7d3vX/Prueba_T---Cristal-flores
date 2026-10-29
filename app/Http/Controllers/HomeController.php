<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Cada rol cae en su zona: dueña → /panel, cajero → /mi-turno. */
class HomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->isOwner() ? 'owner.dashboard' : 'cashier.home');
    }
}
