@extends('layouts.cashier')
@section('title', 'Revisar conteo')
@section('content')
    @use('App\Support\Money')
    {{-- Muestra solo lo que el cajero acaba de escribir. Nada de esperado ni diferencia. --}}
    <h2>Contaste {{ Money::format($countedCents) }}</h2>
    <p>Una vez enviado no podrás cambiarlo.</p>

    @if ($counts)
        <table class="recent">
            @foreach ($counts as $key => $qty)
                @php [$label, $cents, $type] = \App\Support\Denominations::all()[$key]; @endphp
                <tr><td>{{ $type === 'Billetes' ? 'Billetes' : 'Monedas' }} de {{ $label }}</td><td class="num">{{ $qty }}</td></tr>
            @endforeach
        </table>
    @endif

    <form method="post" action="{{ route('cashier.close.store') }}">
        @csrf
        @if ($counts)
            @foreach ($counts as $key => $qty)
                <input type="hidden" name="counts[{{ $key }}]" value="{{ $qty }}">
            @endforeach
        @else
            <input type="hidden" name="counted_cash" value="{{ $countedCash }}">
        @endif
        <button type="submit">Enviar cierre</button>
    </form>
    <a role="button" class="secondary outline" href="{{ route('cashier.close') }}">Volver a contar</a>
@endsection
