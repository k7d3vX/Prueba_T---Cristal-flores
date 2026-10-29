@props(['target'])
{{-- Teclado numérico propio para el mostrador: escribe en el campo #{{ $target }} sin abrir el teclado del sistema. --}}
<div class="keypad" data-keypad="{{ $target }}">
    @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9', '.', '0', '⌫'] as $key)
        <button type="button" class="secondary outline" data-key="{{ $key }}" aria-label="{{ $key === '⌫' ? 'Borrar' : $key }}">{{ $key }}</button>
    @endforeach
</div>
@once
    <script>
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-keypad] [data-key]');
            if (!btn) { return; }
            var input = document.getElementById(btn.closest('[data-keypad]').dataset.keypad);
            var key = btn.dataset.key, v = input.value;
            if (key === '⌫') { v = v.slice(0, -1); }
            else if (key === '.') { if (v.indexOf('.') === -1) { v = (v === '' ? '0' : v) + '.'; } }
            else if (!/\.\d{2}$/.test(v) && v.length < 10) { v += key; }
            input.value = v;
        });
    </script>
@endonce
