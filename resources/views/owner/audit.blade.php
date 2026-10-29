@use('App\Support\Money')
@use('App\Support\Duration')
@php
    $cashPct = $summary->totalSales > 0 ? (int) round($summary->cashSales / $summary->totalSales * 100) : 0;
    $qrPct = $summary->totalSales > 0 ? 100 - $cashPct : 0;
    $tone = match ($summary->verdict) { 'balanced' => 'ok', 'short' => 'bad', default => 'warn' };
    $review = $session->review_status;
@endphp

<hgroup>
    <h2>Resumen del cierre · {{ $session->cashRegister->name }} · Turno #{{ $session->id }}</h2>
    <p>
        {{ $session->opened_at->format('d/m/Y') }} · Cajero: {{ $session->cashier->name }} ·
        {{ $session->closing_mode->label() }} · Cerró: {{ $session->closedBy?->name }}
    </p>
</hgroup>

<x-verdict :summary="$summary" :tolerance="$toleranceCents">
    <small>Estado: <strong>{{ $review?->label() }}</strong> · Se vendió {{ Money::format($summary->totalSales) }}; debía haber {{ Money::format($summary->expectedCash) }} en efectivo y se contaron {{ Money::format($summary->countedCash) }}.</small>
</x-verdict>

<div class="grid">
    <x-stat title="Se ganó" :cents="$summary->totalSales" help="ventas en efectivo + QR" />
    <x-stat title="Se inició con" :cents="$summary->openingFloat" help="fondo inicial" />
    <x-stat title="En total" :cents="$summary->naiveExpected" help="inicio + ganado" />
</div>
<p>
    Turno de <strong>{{ $session->opened_at->format('H:i') }}</strong> a <strong>{{ $session->closed_at->format('H:i') }}</strong>
    ({{ $session->opened_at->format('d/m/Y') }}) · Tiempo trabajado: <strong>{{ Duration::format($session->workedSeconds()) }}</strong>
</p>

<article>
    <header>Efectivo vs. QR</header>
    <label>Efectivo {{ Money::format($summary->cashSales) }} ({{ $cashPct }} %)
        <progress value="{{ $cashPct }}" max="100"></progress>
    </label>
    <label>QR {{ Money::format($summary->qrSales) }} ({{ $qrPct }} %)
        <progress value="{{ $qrPct }}" max="100"></progress>
    </label>
</article>

<div class="grid">
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
    @if ($session->count_breakdown)
        @include('owner._breakdown')
    @endif
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

<h3>Línea de tiempo</h3>
@include('owner._timeline', ['timeline' => $timeline, 'canVoid' => $canVoid])

<article>
    <header>Revisión · {{ $review?->label() }}</header>
    @if ($session->reviewed_at)
        <p>
            {{ $review->label() }} por <strong>{{ $session->reviewedBy?->name }}</strong> el {{ $session->reviewed_at->format('d/m/Y H:i') }}.
            @if ($session->review_note) <br>Comentario: «{{ $session->review_note }}» @endif
        </p>
    @endif

    @if ($review !== \App\Enums\ReviewStatus::Reviewed)
        <form method="post" action="{{ route('owner.sessions.review', $session) }}">
            @csrf
            <label>Comentario (obligatorio para dejar una observación)
                <textarea name="note" maxlength="500" rows="2">{{ old('note') }}</textarea>
            </label>
            <div class="grid">
                <button type="submit" name="decision" value="reviewed">Marcar como revisado</button>
                <button type="submit" name="decision" value="flagged" class="contrast">Dejar observación</button>
            </div>
        </form>
    @endif
</article>
