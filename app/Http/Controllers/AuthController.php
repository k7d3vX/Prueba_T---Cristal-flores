<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Correo o contraseña incorrectos.']);
        }

        $request->session()->regenerate();
        $this->audit->record('sesion.inicio', 'Inició sesión');

        return redirect()->route('home');
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->audit->record('sesion.cierre', 'Cerró sesión');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
