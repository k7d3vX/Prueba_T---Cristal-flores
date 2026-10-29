@extends('layouts.app')
@section('title', 'Turno #'.$session->id)
@section('content')
    @use('App\Support\Money')

    <hgroup>
        <h2>{{ $session->cashRegister->name }} · Turno #{{ $session->id }} · {{ $session->status->label() }}</h2>
        <p>
            Cajero: {{ $session->cashier->name }} · {{ $session->closing_mode->label() }} ·
            Fondo inicial: {{ Money::format(\App\Support\Money::toCents($session->opening_float)) }} ·
            Abierto: {{ $session->opened_at->format('d/m/Y H:i') }}@if ($session->closed_at) · Cerrado: {{ $session->closed_at->format('H:i') }}@endif
        </p>
    </hgroup>

    @if ($session->isOpen())
        <div class="grid">
            <article>
                <header>Registrar venta</header>
                <form method="post" action="{{ route('cash.sales.store', $session) }}">
                    @csrf
                    <label>Monto (Bs)
                        <input type="number" name="amount" step="0.01" min="0.01" required>
                    </label>
                    <label>Forma de pago
                        <select name="method" required>
                            <option value="cash">Efectivo</option>
                            <option value="qr">QR</option>
                        </select>
                    </label>
                    <button type="submit">Registrar venta</button>
                </form>
            </article>
            <article>
                <header>Registrar salida de caja</header>
                <form method="post" action="{{ route('cash.withdrawals.store', $session) }}">
                    @csrf
                    <label>Monto (Bs)
                        <input type="number" name="amount" step="0.01" min="0.01" required>
                    </label>
                    <label>Motivo
                        <input type="text" name="reason" maxlength="255" placeholder="Ej: pago a proveedor" required>
                    </label>
                    <button type="submit">Registrar salida</button>
                </form>
            </article>
        </div>

        @if ($canClose)
            <p><a role="button" href="{{ route('cash.close', $session) }}">Cerrar caja</a></p>
        @else
            <p><small>Esta caja la cierra {{ $session->isBlind() ? 'el cajero (caja ciega)' : 'la dueña (caja normal)' }}.</small></p>
        @endif
    @elseif (! $showTotals)
        <p>La caja está cerrada. La dueña revisará el cierre.</p>
    @endif

    @if ($showTotals && $summary)
        <article>
            <header>Totales</header>
            <table>
                <tr><td>Ventas en efectivo</td><td class="num">{{ Money::format($summary->cashSales) }}</td></tr>
                <tr><td>Ventas por QR</td><td class="num">{{ Money::format($summary->qrSales) }}</td></tr>
                <tr><td><strong>Total vendido</strong></td><td class="num"><strong>{{ Money::format($summary->totalSales) }}</strong></td></tr>
                <tr><td>Salidas de caja</td><td class="num">{{ Money::format($summary->withdrawals) }}</td></tr>
                <tr><td>Esperado en caja</td><td class="num">{{ Money::format($summary->expectedCash) }}</td></tr>
            </table>
            @if (! $session->isOpen() && auth()->user()->isOwner())
                <a href="{{ route('cash.summary', $session) }}">Ver resumen del cierre</a>
            @endif
        </article>

        <div class="grid">
            <div>
                <h4>Ventas</h4>
                <table>
                    @forelse ($sales as $s)
                        <tr><td>{{ $s->created_at->format('H:i') }}</td><td>{{ $s->method->label() }}</td><td class="num">{{ Money::format(Money::toCents($s->amount)) }}</td></tr>
                    @empty
                        <tr><td>Sin ventas todavía.</td></tr>
                    @endforelse
                </table>
            </div>
            <div>
                <h4>Salidas</h4>
                <table>
                    @forelse ($withdrawals as $w)
                        <tr><td>{{ $w->created_at->format('H:i') }}</td><td>{{ $w->reason }}</td><td class="num">{{ Money::format(Money::toCents($w->amount)) }}</td></tr>
                    @empty
                        <tr><td>Sin salidas.</td></tr>
                    @endforelse
                </table>
            </div>
        </div>
    @endif
@endsection
