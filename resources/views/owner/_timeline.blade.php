@use('App\Support\Money')
{{-- Línea de tiempo: cada venta y salida con hora, monto, forma de pago, motivo; las anuladas, tachadas con su motivo. --}}
<table>
    <thead><tr><th>Hora</th><th>Movimiento</th><th>Detalle</th><th class="num">Monto</th><th></th></tr></thead>
    <tbody>
    @forelse ($timeline as $row)
        @php
            $m = $row['movement'];
            $isSale = $row['kind'] === 'sale';
        @endphp
        <tr>
            <td>{{ $row['at']->format('H:i') }}</td>
            <td>{{ $isSale ? 'Venta '.($m->method->value === 'qr' ? 'QR' : 'efectivo') : 'Salida · '.$m->category->label() }}</td>
            <td>
                @if (! $isSale) {{ $m->reason }} @endif
                @if ($m->isVoided())
                    <br><small class="bad">Anulado: {{ $m->void_reason }} ({{ $m->voidedBy?->name }})</small>
                @elseif ($m->hasPendingVoidRequest())
                    <br><small class="warn">⚠ El cajero pidió anularlo: {{ $m->void_request_note }}</small>
                @endif
            </td>
            <td class="num">
                @if ($m->isVoided())<del>{{ Money::format(Money::toCents($m->amount)) }}</del>@else{{ $isSale ? '' : '− ' }}{{ Money::format(Money::toCents($m->amount)) }}@endif
            </td>
            <td>
                @if ($canVoid && ! $m->isVoided())
                    <details>
                        <summary>Anular</summary>
                        <form method="post" action="{{ $isSale ? route('owner.sales.void', $m) : route('owner.withdrawals.void', $m) }}">
                            @csrf
                            <input type="text" name="reason" maxlength="255" placeholder="Motivo (obligatorio)" required>
                            <button type="submit" class="secondary">Anular movimiento</button>
                        </form>
                    </details>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="5">Sin movimientos todavía.</td></tr>
    @endforelse
    </tbody>
</table>
