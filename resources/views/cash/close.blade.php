@extends('layouts.app')
@section('title', 'Cerrar turno #'.$session->id)
@section('content')
    @use('App\Support\Money')

    <h2>Cerrar {{ $session->cashRegister->name }} · Turno #{{ $session->id }} · {{ $session->closing_mode->label() }}</h2>

    {{-- Solo la caja NORMAL recibe $summary: la caja ciega no muestra ninguna cifra (RN-5). --}}
    @if ($summary)
        <article>
            <header>Lo que debería haber en el cajón</header>
            <table>
                <tr><td>Fondo inicial</td><td class="num">{{ Money::format($summary->openingFloat) }}</td></tr>
                <tr><td>+ Ventas en efectivo</td><td class="num">{{ Money::format($summary->cashSales) }}</td></tr>
                <tr><td>− Salidas</td><td class="num">{{ Money::format($summary->withdrawals) }}</td></tr>
                <tr><td><strong>Esperado en caja</strong></td><td class="num"><strong>{{ Money::format($summary->expectedCash) }}</strong></td></tr>
            </table>
            <small>Las ventas por QR ({{ Money::format($summary->qrSales) }}) no cuentan: no hay billetes en el cajón.</small>
        </article>
    @else
        <p>Cuenta el efectivo que hay físicamente en la caja y registra el total.</p>
    @endif

    <form method="post" action="{{ route('cash.close.store', $session) }}">
        @csrf
        <label>Efectivo contado (Bs)
            <input type="number" name="counted_cash" step="0.01" min="0" required autofocus value="{{ old('counted_cash') }}">
        </label>
        <button type="submit">Cerrar caja</button>
    </form>
@endsection
