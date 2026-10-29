@props(['cents', 'tone' => null])
{{-- Dinero siempre igual: «Bs 1.234,50», alineado a la derecha. --}}
<span class="num {{ $tone }}">{{ \App\Support\Money::format($cents) }}</span>
