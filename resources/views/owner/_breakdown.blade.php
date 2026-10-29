@use('App\Support\Money')
<article>
    <header>Desglose del conteo</header>
    <table>
        @foreach ($session->count_breakdown as $key => $qty)
            @php [$label, $cents, $type] = \App\Support\Denominations::all()[$key]; @endphp
            <tr>
                <td>{{ $type }} de {{ $label }} × {{ $qty }}</td>
                <td class="num">{{ Money::format($cents * $qty) }}</td>
            </tr>
        @endforeach
    </table>
</article>
