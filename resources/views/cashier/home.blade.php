@extends('layouts.cashier')
@section('title', 'Mi turno')
@section('content')
    @use('App\Support\Money')

    {{-- RN-16: esta pantalla solo recibe $shift (fondo + últimos 5 registros). Nunca totales ni saldo. --}}

    @if ($shift->state === 'none')
        <h2>No tienes un turno abierto</h2>

        @if ($shift->lastClosedAt)
            <p>
                @if ($shift->lastClosedBySelf)
                    <strong>Cierre enviado</strong> a las {{ $shift->lastClosedAt->format('H:i') }}. La dueña lo revisará.
                @else
                    Tu último turno se cerró a las {{ $shift->lastClosedAt->format('H:i') }}.
                @endif
            </p>
        @endif

        @forelse ($shift->freeRegisters as $register)
            <form method="post" action="{{ route('cashier.open') }}">
                @csrf
                <input type="hidden" name="cash_register_id" value="{{ $register->id }}">
                <button type="submit">Abrir {{ $register->name }}</button>
                <small>Recibes el fondo habitual de {{ Money::format(Money::toCents($register->default_float)) }}.</small>
            </form>
        @empty
            <p>No hay cajas libres. Pídele a la dueña que abra un turno.</p>
        @endforelse

    @elseif ($shift->state === 'pending')
        <h2>{{ $shift->registerName }}</h2>
        <p style="font-size:1.2rem">
            La dueña te entregó <strong>{{ Money::format(Money::toCents($shift->openingFloat)) }}</strong>
            de cambio en <strong>{{ $shift->registerName }}</strong>. ¿Los recibiste?
        </p>

        @if ($shift->floatDisputed)
            <p class="warn"><strong>⚠ Avisaste que el monto no coincide.</strong> Espera a que la dueña lo corrija.</p>
        @endif

        <form method="post" action="{{ route('cashier.float.confirm') }}">
            @csrf
            <button type="submit">Sí, recibí {{ Money::format(Money::toCents($shift->openingFloat)) }}</button>
        </form>

        @unless ($shift->floatDisputed)
            <form method="post" action="{{ route('cashier.float.dispute') }}">
                @csrf
                <button type="submit" class="secondary outline">No coincide, avisar a la dueña</button>
            </form>
        @endunless

    @else
        <h2>{{ $shift->registerName }}</h2>
        <p>Recibiste un fondo de {{ Money::format(Money::toCents($shift->openingFloat)) }}.</p>

        <article>
            <header>Registrar venta</header>
            <form method="post" action="{{ route('cashier.sales.store') }}">
                @csrf
                <label>Monto (Bs)
                    <input type="text" id="sale-amount" name="amount" inputmode="none" autocomplete="off" required placeholder="0.00" value="{{ old('amount') }}">
                </label>
                <x-keypad target="sale-amount" />
                <div class="two">
                    <button type="submit" name="method" value="cash">Efectivo</button>
                    <button type="submit" name="method" value="qr" class="secondary">QR</button>
                </div>
            </form>
        </article>

        <article>
            <header>Salida de caja</header>
            <form method="post" action="{{ route('cashier.withdrawals.store') }}">
                @csrf
                <label>Monto (Bs)
                    <input type="text" name="amount" inputmode="decimal" autocomplete="off" required placeholder="0.00">
                </label>
                <fieldset>
                    <legend>Categoría</legend>
                    @foreach (\App\Enums\WithdrawalCategory::cases() as $category)
                        <label><input type="radio" name="category" value="{{ $category->value }}" @checked(old('category', 'supplier') === $category->value)> {{ $category->label() }}</label>
                    @endforeach
                </fieldset>
                <label>Motivo
                    <input type="text" name="reason" maxlength="255" required placeholder="Ej: pago al proveedor de aceite">
                </label>
                <button type="submit" class="contrast">Registrar salida</button>
            </form>
        </article>

        <h3>Mis últimos registros</h3>
        <table class="recent">
            @forelse ($shift->recent as $row)
                <tr>
                    <td>{{ $row['at']->format('H:i') }}</td>
                    <td>{{ $row['label'] }}</td>
                    <td class="num">{{ Money::format($row['cents']) }}</td>
                </tr>
                <tr>
                    <td colspan="3">
                        @if ($row['requested'])
                            <small>⚠ Ya avisaste a la dueña de este registro.</small>
                        @else
                            <details>
                                <summary><small>¿Te equivocaste? Avisar a la dueña</small></summary>
                                <form method="post" action="{{ $row['kind'] === 'sale' ? route('cashier.sales.void-request', $row['id']) : route('cashier.withdrawals.void-request', $row['id']) }}">
                                    @csrf
                                    <input type="text" name="reason" maxlength="255" required placeholder="¿Qué se equivocó?">
                                    <button type="submit" class="secondary outline">Pedir anulación</button>
                                </form>
                            </details>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td>Todavía no registraste nada.</td></tr>
            @endforelse
        </table>

        @if ($shift->canClose)
            <a role="button" class="contrast" href="{{ route('cashier.close') }}">Terminar mi turno</a>
        @else
            <p><small>La dueña cierra esta caja al final del día.</small></p>
        @endif
    @endif
@endsection
