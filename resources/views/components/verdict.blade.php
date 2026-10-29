@props(['summary', 'tolerance' => 0])
@php $label = \App\Support\VerdictLabel::for($summary->difference, $tolerance); @endphp
{{-- Veredicto en grande. El color siempre va con símbolo y texto; la tolerancia solo cambia la etiqueta (AR-28). --}}
<div class="banner {{ $label['tone'] }}">
    <h3 class="{{ $label['tone'] }}" style="margin:0">{{ $label['icon'] }} {{ $label['text'] }}</h3>
    {{ $slot }}
</div>
