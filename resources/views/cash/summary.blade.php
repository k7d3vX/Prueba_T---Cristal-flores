@extends('layouts.app')
@section('title', 'Resumen turno #'.$session->id)
@section('content')
    @use('App\Support\Money')
    @use('App\Support\Duration')

    @php
        $tone = match ($summary->verdict) { 'balanced' => 'ok', 'short' => 'bad', default => 'warn' };
    @endphp

    <hgroup>
        <h2>Resumen del cierre · {{ $session->cashRegister->name }} · Turno #{{ $session->id }}</h2>
        <p>
            {{ $session->opened_at->format('d/m/Y') }} · Cajero: {{ $session->cashier->name }} ·
            {{ $session->closing_mode->label() }} · Cerró: {{ $session->closedBy?->name }}
        </p>
    </hgroup>

    <div class="grid">
        <article>
            <header>Se ganó</header>
            <h3 style="margin:0">{{ Money::format($summary->totalSales) }}</h3>
            <small>ventas en efectivo + QR</small>
        </article>
        <article>
            <header>Se inició con</header>
            <h3 style="margin:0">{{ Money::format($summary->openingFloat) }}</h3>
            <small>fondo inicial</small>
        </article>
        <article>
            <header>En total</header>
            <h3 style="margin:0">{{ Money::format($summary->naiveExpected) }}</h3>
            <small>inicio + ganado</small>
        </article>
    </div>
    <p>
        Turno de <strong>{{ $session->opened_at->format('H:i') }}</strong> a <strong>{{ $session->closed_at->format('H:i') }}</strong>
        ({{ $session->opened_at->format('d/m/Y') }}) · Tiempo trabajado: <strong>{{ Duration::format($session->workedSeconds()) }}</strong>
    </p>

    <div class="banner {{ $tone }}">
        <h3 class="{{ $tone }}" style="margin:0">
            @if ($summary->verdict === 'balanced')
                La caja cuadra
            @else
                {{ $summary->verdictLabel() }} de {{ Money::format(abs($summary->difference)) }}
            @endif
        </h3>
        <small>Se vendió {{ Money::format($summary->totalSales) }}; debía haber {{ Money::format($summary->expectedCash) }} en efectivo y se contaron {{ Money::format($summary->countedCash) }}.</small>
    </div>

    <div class="grid">
        <article>
            <header>Cuánto se vendió</header>
            <table>
                <tr><td>En efectivo</td><td class="num">{{ Money::format($summary->cashSales) }}</td></tr>
                <tr><td>Por QR</td><td class="num">{{ Money::format($summary->qrSales) }}</td></tr>
                <tr><td><strong>Total vendido</strong></td><td class="num"><strong>{{ Money::format($summary->totalSales) }}</strong></td></tr>
            </table>
        </article>
        <article>
            <header>¿Cuadra el efectivo?</header>
            <table>
                <tr><td>Fondo inicial</td><td class="num">{{ Money::format($summary->openingFloat) }}</td></tr>
                <tr><td>+ Ventas en efectivo</td><td class="num">{{ Money::format($summary->cashSales) }}</td></tr>
                <tr><td>− Salidas de caja</td><td class="num">{{ Money::format($summary->withdrawals) }}</td></tr>
                <tr><td><strong>Esperado en caja</strong></td><td class="num"><strong>{{ Money::format($summary->expectedCash) }}</strong></td></tr>
                <tr><td>Contado por el cajero</td><td class="num">{{ Money::format($summary->countedCash) }}</td></tr>
                <tr><td><strong>Diferencia</strong></td><td class="num {{ $tone }}"><strong>{{ Money::format($summary->difference) }}</strong></td></tr>
            </table>
        </article>
    </div>

    <blockquote>
        “{{ $summary->ownerClaim() }}”
        <footer>— La cuenta de la dueña</footer>
    </blockquote>

    <article>
        <header>¿Qué pasó realmente? ¿Por qué no son {{ Money::format($summary->naiveGap) }}?</header>
        <table>
            @foreach ($summary->reconciliation() as $i => [$label, $amount])
                @php $last = $loop->last; @endphp
                <tr>
                    <td>{!! $last ? '<strong>' : '' !!}{{ $label }}{!! $last ? '</strong>' : '' !!}</td>
                    <td class="num">{!! $last ? '<strong>' : '' !!}{{ Money::format($amount) }}{!! $last ? '</strong>' : '' !!}</td>
                </tr>
            @endforeach
        </table>
        <small>El QR nunca entra al cajón y las salidas sí lo vacían: por eso la cuenta simple (ventas + fondo − contado) da más de lo que realmente falta.</small>
    </article>

    <div class="grid">
        <div>
            <h4>Ventas</h4>
            <table>
                @foreach ($sales as $s)
                    <tr><td>{{ $s->created_at->format('H:i') }}</td><td>{{ $s->method->label() }}</td><td class="num">{{ Money::format(Money::toCents($s->amount)) }}</td></tr>
                @endforeach
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

    <p><a href="{{ route('cash.index') }}">← Volver a las cajas</a></p>
@endsection
