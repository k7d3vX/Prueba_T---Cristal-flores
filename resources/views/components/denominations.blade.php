@props(['expected' => null])
@php $groups = collect(\App\Support\Denominations::all())->groupBy(fn ($d) => $d[2], preserveKeys: true); @endphp
{{-- Planilla de billetes y monedas (RN-25). El total que se ve es solo ayuda: el servidor lo recalcula. --}}
<div data-denominations @if ($expected !== null) data-expected="{{ $expected }}" @endif>
    @foreach ($groups as $type => $items)
        <h4>{{ $type }}</h4>
        <div class="denoms">
            @foreach ($items as $key => [$label, $cents])
                <label>{{ $label }}
                    <input type="number" name="counts[{{ $key }}]" data-cents="{{ $cents }}" min="0" max="{{ \App\Support\Denominations::MAX_QUANTITY }}" step="1"
                           inputmode="numeric" placeholder="0" value="{{ old('counts.'.$key) }}">
                </label>
            @endforeach
        </div>
    @endforeach

    <p style="font-size:1.2rem">Tu conteo: <strong data-total>Bs 0,00</strong></p>
    @if ($expected !== null)
        <p>Diferencia en vivo: <strong data-diff>—</strong></p>
    @endif

    <details>
        <summary>¿No contaste por billetes? Escribe solo el total</summary>
        <label>Total contado (Bs)
            <input type="text" name="counted_cash" inputmode="decimal" autocomplete="off" placeholder="0.00" value="{{ old('counted_cash') }}">
        </label>
    </details>
</div>
@once
    <script>
        (function () {
            function fmt(c) {
                var neg = c < 0; c = Math.abs(c);
                var e = Math.floor(c / 100).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                return 'Bs ' + (neg ? '-' : '') + e + ',' + ('0' + (c % 100)).slice(-2);
            }
            document.querySelectorAll('[data-denominations]').forEach(function (box) {
                function recalc() {
                    var t = 0;
                    box.querySelectorAll('input[data-cents]').forEach(function (i) {
                        t += (parseInt(i.value, 10) || 0) * parseInt(i.dataset.cents, 10);
                    });
                    box.querySelector('[data-total]').textContent = fmt(t);
                    if (box.dataset.expected !== undefined) {
                        var d = t - parseInt(box.dataset.expected, 10);
                        box.querySelector('[data-diff]').textContent = fmt(d) + (d === 0 ? ' ✔ cuadra' : (d < 0 ? ' ▼ faltante' : ' ▲ sobrante'));
                    }
                }
                box.addEventListener('input', recalc);
                recalc();
            });
        })();
    </script>
@endonce
