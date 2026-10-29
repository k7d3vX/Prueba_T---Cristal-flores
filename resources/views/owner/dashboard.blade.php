@extends('layouts.owner')
@section('title', 'Panel')
@section('content')
    @use('App\Support\Money')
    @use('App\Support\Duration')

    <h2>Panel</h2>

    @foreach ($dashboard->alerts as $alert)
        <x-alert :level="$alert->level">
            {{ $alert->message }}
            @if ($alert->sessionId) <a href="{{ route('owner.sessions.show', $alert->sessionId) }}">Ver turno</a> @endif
        </x-alert>
    @endforeach

    {{-- Una tarjeta por caja: libre / por confirmar / abierta --}}
    <div class="grid">
        @foreach ($dashboard->registers as $r)
            @php $turn = $dashboard->active->get($r->id); @endphp
            <article>
                <header>{{ $r->name }}</header>
                @if (! $turn)
                    <p>○ <strong>Libre</strong></p>
                @elseif ($turn->isPending())
                    <p>◐ <strong>Por confirmar</strong> · {{ $turn->cashier->name }}</p>
                    @if ($turn->float_disputed_at)
                        <p class="warn"><strong>⚠ El monto no coincide:</strong> {{ $turn->cashier->name }} dice que recibió otro fondo.</p>
                    @endif
                    <a href="{{ route('owner.sessions.show', $turn) }}">Ver turno</a>
                @else
                    <p>● <strong>Abierta</strong> · {{ $turn->cashier->name }} · {{ $turn->closing_mode->label() }}</p>
                    <small>Desde {{ $turn->opened_at->format('H:i') }}</small><br>
                    <a href="{{ route('owner.sessions.show', $turn) }}">Ver turno</a>
                @endif
            </article>
        @endforeach
    </div>

    @if ($dashboard->pendingReview->isNotEmpty())
        <article>
            <header>Pendientes de revisión ({{ $dashboard->pendingReview->count() }})</header>
            <table>
                @foreach ($dashboard->pendingReview as $p)
                    <tr>
                        <td>{{ $p->cashRegister->name }} · {{ $p->cashier->name }} · {{ $p->closed_at->format('d/m H:i') }}</td>
                        <td>{{ \App\Support\VerdictLabel::compact(Money::toCents($p->difference), $dashboard->tolerance) }}</td>
                        <td>{{ $p->review_status->label() }}</td>
                        <td><a href="{{ route('owner.sessions.show', $p) }}">Revisar</a></td>
                    </tr>
                @endforeach
            </table>
        </article>
    @endif

    @if ($dashboard->voidRequests->isNotEmpty())
        <article>
            <header>Anulaciones que pidió el cajero ({{ $dashboard->voidRequests->count() }})</header>
            <table>
                @foreach ($dashboard->voidRequests as $r)
                    @php $m = $r['movement']; @endphp
                    <tr>
                        <td>{{ $m->cashSession->cashier->name }} · {{ $r['kind'] === 'sale' ? 'Venta' : 'Salida' }} {{ Money::format(Money::toCents($m->amount)) }}</td>
                        <td>«{{ $m->void_request_note }}»</td>
                        <td><a href="{{ route('owner.sessions.show', $m->cashSession) }}">Resolver</a></td>
                    </tr>
                @endforeach
            </table>
        </article>
    @endif

    @if ($dashboard->free->isNotEmpty())
        {{-- Turno anterior de cada caja libre (trae cifras de ventas: solo la dueña) --}}
        @foreach ($dashboard->free as $r)
            @php $prev = $dashboard->suggestions[$r->id]->previous; @endphp
            @if ($prev)
                <article>
                    <header>{{ $r->name }} · turno anterior (#{{ $prev->sessionId }})</header>
                    <p>
                        Se inició con <strong>{{ Money::format($prev->openingFloat) }}</strong> ·
                        se ganó <strong>{{ Money::format($prev->earned) }}</strong> ·
                        en total <strong>{{ Money::format($prev->total) }}</strong>
                    </p>
                    <small>
                        Trabajó de {{ $prev->openedAt->format('d/m/Y H:i') }} a {{ $prev->closedAt->format('d/m/Y H:i') }}
                        ({{ Duration::format($prev->workedSeconds) }})
                    </small>
                </article>
            @endif
        @endforeach

        <article>
            <header>Abrir un turno</header>
            @if ($dashboard->freeCashiers->isEmpty())
                <p><small>Todos los cajeros ya tienen un turno activo.</small></p>
            @else
                <form method="post" action="{{ route('owner.sessions.store') }}">
                    @csrf
                    <div class="grid">
                        <label>Caja
                            <select name="cash_register_id" required>
                                @foreach ($dashboard->free as $r)
                                    <option value="{{ $r->id }}" data-float="{{ $dashboard->suggestions[$r->id]->openingFloat }}" @selected(old('cash_register_id') == $r->id)>{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Cajero responsable
                            <select name="cashier_id" required>
                                @foreach ($dashboard->freeCashiers as $c)
                                    <option value="{{ $c->id }}" @selected(old('cashier_id') == $c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Fondo inicial (Bs)
                            <input type="number" name="opening_float" step="0.01" min="0" value="{{ old('opening_float', $dashboard->suggestions[$dashboard->free->first()->id]->openingFloat) }}" required>
                        </label>
                        <label>Modo de cierre
                            <select name="closing_mode" required>
                                <option value="blind" @selected(old('closing_mode') === 'blind')>Caja ciega (cierra el cajero)</option>
                                <option value="normal" @selected(old('closing_mode') === 'normal')>Caja normal (cierras tú)</option>
                            </select>
                        </label>
                    </div>
                    <small>El cajero confirma el fondo recibido y recién entonces puede vender. El fondo se propone según el último con que abriste esa caja.</small>
                    <button type="submit">Abrir turno</button>
                </form>
                <script>
                    // Monto inicial secuencial: al cambiar de caja se propone su fondo habitual.
                    document.querySelector('[name=cash_register_id]').addEventListener('change', function (e) {
                        var f = e.target.selectedOptions[0].dataset.float;
                        if (f) { document.querySelector('[name=opening_float]').value = f; }
                    });
                </script>
            @endif
        </article>
    @else
        <p><small>Todas las cajas ya tienen un turno activo.</small></p>
    @endif

    <details>
        <summary>Fondo habitual por caja</summary>
        <p><small>Es el fondo con que un cajero puede abrir esa caja por su cuenta. No puede cambiarlo.</small></p>
        @foreach ($dashboard->registers as $r)
            <form method="post" action="{{ route('owner.registers.float', $r) }}" class="grid">
                @csrf
                @method('PATCH')
                <label>{{ $r->name }} (Bs)
                    <input type="number" name="default_float" step="0.01" min="0" value="{{ $r->default_float }}" required>
                </label>
                <button type="submit" class="secondary" style="align-self:end">Guardar</button>
            </form>
        @endforeach
    </details>

    <h3>Turnos de caja</h3>
    <table>
        <thead><tr><th>#</th><th>Caja</th><th>Fecha</th><th>Horario</th><th>Cajero</th><th>Modo</th><th>Estado</th><th>Revisión</th><th></th></tr></thead>
        <tbody>
        @forelse ($dashboard->sessions as $s)
            <tr>
                <td>{{ $s->id }}</td>
                <td>{{ $s->cashRegister->name }}</td>
                <td>{{ $s->opened_at->format('d/m/Y') }}</td>
                <td>
                    @if ($s->closed_at)
                        {{ $s->opened_at->format('H:i') }} – {{ $s->closed_at->format('H:i') }}
                        ({{ Duration::format($s->workedSeconds()) }})
                    @elseif ($s->isOpen())
                        En curso desde {{ $s->opened_at->format('H:i') }}
                    @else
                        Esperando confirmación
                    @endif
                </td>
                <td>{{ $s->cashier->name }}</td>
                <td>{{ $s->closing_mode->label() }}</td>
                <td>{{ $s->status->label() }}</td>
                <td>{{ $s->review_status?->label() }}</td>
                <td><a href="{{ route('owner.sessions.show', $s) }}">{{ $s->status === \App\Enums\SessionStatus::Closed ? 'Ver resumen' : 'Abrir' }}</a></td>
            </tr>
        @empty
            <tr><td colspan="9">Todavía no hay turnos.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
