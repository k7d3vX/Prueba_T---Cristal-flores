@extends('layouts.app')
@section('title', 'Cajas')
@section('content')
    @use('App\Support\Money')
    @use('App\Support\Duration')

    @if ($registers->isNotEmpty())
        {{-- Turno anterior de cada caja libre: solo la dueña (trae cifras de ventas) --}}
        @foreach ($registers as $r)
            @php $prev = $suggestions[$r->id]->previous; @endphp
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
            <header>Abrir un turno de caja</header>
            <form method="post" action="{{ route('cash.store') }}">
                @csrf
                <div class="grid">
                    <label>Caja
                        <select name="cash_register_id" required>
                            @foreach ($registers as $r)
                                <option value="{{ $r->id }}" data-float="{{ $suggestions[$r->id]->openingFloat }}" @selected(old('cash_register_id') == $r->id)>{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if (auth()->user()->isOwner())
                        <label>Cajero responsable
                            <select name="cashier_id" required>
                                @foreach ($cashiers as $c)
                                    <option value="{{ $c->id }}" @selected(old('cashier_id') == $c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    <label>Fondo inicial (Bs)
                        <input type="number" name="opening_float" step="0.01" min="0" value="{{ old('opening_float', $suggestions[$registers->first()->id]->openingFloat) }}" required>
                    </label>
                    <label>Modo de cierre
                        <select name="closing_mode" required>
                            <option value="normal" @selected(old('closing_mode') === 'normal')>Caja normal (cierra la dueña)</option>
                            <option value="blind" @selected(old('closing_mode') === 'blind')>Caja ciega (cierra el cajero)</option>
                        </select>
                    </label>
                </div>
                <small>El fondo inicial se propone según el turno anterior de esa caja; puedes cambiarlo.</small>
                <button type="submit">Abrir caja</button>
            </form>
            <script>
                // Monto inicial secuencial: al cambiar de caja se propone el fondo de su turno anterior.
                document.querySelector('[name=cash_register_id]').addEventListener('change', function (e) {
                    var f = e.target.selectedOptions[0].dataset.float;
                    if (f) { document.querySelector('[name=opening_float]').value = f; }
                });
            </script>
        </article>
    @else
        <p><small>Todas las cajas ya tienen un turno abierto.</small></p>
    @endif

    <h3>Turnos de caja</h3>
    <table>
        <thead><tr><th>#</th><th>Caja</th><th>Fecha</th><th>Horario</th><th>Cajero</th><th>Modo</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @forelse ($sessions as $s)
            <tr>
                <td>{{ $s->id }}</td>
                <td>{{ $s->cashRegister->name }}</td>
                <td>{{ $s->opened_at->format('d/m/Y') }}</td>
                <td>
                    @if ($s->closed_at)
                        {{ $s->opened_at->format('H:i') }} – {{ $s->closed_at->format('H:i') }}
                        ({{ Duration::format($s->workedSeconds()) }})
                    @else
                        En curso desde {{ $s->opened_at->format('H:i') }}
                    @endif
                </td>
                <td>{{ $s->cashier->name }}</td>
                <td>{{ $s->closing_mode->label() }}</td>
                <td>{{ $s->status->label() }}</td>
                <td>
                    @if (auth()->user()->isOwner() && ! $s->isOpen())
                        <a href="{{ route('cash.summary', $s) }}">Ver resumen</a>
                    @else
                        <a href="{{ route('cash.show', $s) }}">Abrir</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8">Todavía no hay cajas.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
