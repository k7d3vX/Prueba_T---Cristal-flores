@extends('layouts.owner')
@section('title', 'Cerrar turno #'.$session->id)
@section('content')
    @use('App\Support\Money')

    <h2>Cerrar {{ $session->cashRegister->name }} · Turno #{{ $session->id }} · {{ $session->closing_mode->label() }}</h2>

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

    <form method="post" action="{{ route('owner.close.store', $session) }}">
        @csrf
        <x-denominations :expected="$summary->expectedCash" />
        <button type="submit">Cerrar caja</button>
    </form>
    <p><a href="{{ route('owner.sessions.show', $session) }}">← Volver al turno</a></p>
@endsection
