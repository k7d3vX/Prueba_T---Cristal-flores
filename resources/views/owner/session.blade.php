@extends('layouts.owner')
@section('title', 'Turno #'.$session->id)
@section('content')
    @use('App\Support\Money')

    @if ($session->status === \App\Enums\SessionStatus::Closed)
        {{-- Turno cerrado: resumen completo con conciliación --}}
        @include('owner.audit')
    @else
        <hgroup>
            <h2>{{ $session->cashRegister->name }} · Turno #{{ $session->id }} · {{ $session->status->label() }}</h2>
            <p>
                Cajero: {{ $session->cashier->name }} · {{ $session->closing_mode->label() }} ·
                Fondo inicial: {{ Money::format(Money::toCents($session->opening_float)) }} ·
                @if ($session->isOpen())
                    Abierto: {{ $session->opened_at->format('d/m/Y H:i') }}
                @else
                    Esperando que {{ $session->cashier->name }} confirme el fondo
                @endif
            </p>
        </hgroup>

        @if ($session->isPending())
            @if ($session->float_disputed_at)
                <div class="banner warn">
                    <strong>⚠ El cajero indica que el monto no coincide.</strong>
                    Corrígelo; deberá confirmarlo de nuevo.
                </div>
            @endif
            <article>
                <header>Corregir el fondo entregado</header>
                <form method="post" action="{{ route('owner.sessions.float', $session) }}">
                    @csrf
                    @method('PATCH')
                    <label>Fondo (Bs)
                        <input type="number" name="opening_float" step="0.01" min="0" value="{{ old('opening_float', $session->opening_float) }}" required>
                    </label>
                    <button type="submit">Guardar fondo</button>
                </form>
            </article>
        @else
            <article>
                <header>Totales hasta ahora</header>
                <table>
                    <tr><td>Ventas en efectivo</td><td class="num">{{ Money::format($summary->cashSales) }}</td></tr>
                    <tr><td>Ventas por QR</td><td class="num">{{ Money::format($summary->qrSales) }}</td></tr>
                    <tr><td><strong>Total vendido</strong></td><td class="num"><strong>{{ Money::format($summary->totalSales) }}</strong></td></tr>
                    <tr><td>Salidas de caja</td><td class="num">{{ Money::format($summary->withdrawals) }}</td></tr>
                    <tr><td>Esperado en caja</td><td class="num">{{ Money::format($summary->expectedCash) }}</td></tr>
                </table>
            </article>

            <h3>Movimientos</h3>
            @include('owner._timeline', ['timeline' => $timeline, 'canVoid' => $canVoid])

            @if ($canClose)
                <p><a role="button" href="{{ route('owner.close', $session) }}">Cerrar caja</a></p>
            @else
                <p><small>Esta caja la cierra el cajero (caja ciega). Tú revisas el cierre cuando lo envíe.</small></p>
            @endif
        @endif
    @endif

    <p><a href="{{ route('owner.dashboard') }}">← Volver al panel</a></p>
@endsection
