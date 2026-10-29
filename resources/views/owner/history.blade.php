@extends('layouts.owner')
@section('title', 'Historial')
@section('content')
    @use('App\Support\Money')
    @use('App\Support\VerdictLabel')

    <h2>Historial y control del cajero</h2>

    <form method="get" action="{{ route('owner.history') }}">
        <div class="grid">
            <label>Cajero
                <select name="cashier_id">
                    <option value="">Todos</option>
                    @foreach ($report->cashiers as $c)
                        <option value="{{ $c->id }}" @selected(($filters['cashier_id'] ?? null) == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Caja
                <select name="cash_register_id">
                    <option value="">Todas</option>
                    @foreach ($report->registers as $r)
                        <option value="{{ $r->id }}" @selected(($filters['cash_register_id'] ?? null) == $r->id)>{{ $r->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Desde <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
            <label>Hasta <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
            <label>Revisión
                <select name="review">
                    <option value="">Todas</option>
                    @foreach (\App\Enums\ReviewStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['review'] ?? null) === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <button type="submit">Filtrar</button>
    </form>

    <h3>Por cajero</h3>
    <table>
        <thead><tr><th>Cajero</th><th class="num">Turnos</th><th class="num">Diferencia total</th><th class="num">Promedio por turno</th><th class="num">Faltantes en los últimos 7 turnos</th></tr></thead>
        <tbody>
        @forelse ($report->perCashier as $row)
            <tr>
                <td>{{ $row['name'] }}</td>
                <td class="num">{{ $row['turns'] }}</td>
                <td class="num">{{ Money::format($row['total']) }}</td>
                <td class="num">{{ Money::format($row['average']) }}</td>
                <td class="num">{{ $row['recent_shortages'] }}@if ($row['recent_shortages'] >= config('caja.repeated_shortages.count')) ⚠ @endif</td>
            </tr>
        @empty
            <tr><td colspan="5">Sin turnos cerrados con esos filtros.</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>Turnos cerrados</h3>
    <table>
        <thead><tr><th>#</th><th>Fecha</th><th>Caja</th><th>Cajero</th><th>Resultado</th><th>Revisión</th><th></th></tr></thead>
        <tbody>
        @forelse ($report->turns as $s)
            <tr>
                <td>{{ $s->id }}</td>
                <td>{{ $s->closed_at->format('d/m/Y H:i') }}</td>
                <td>{{ $s->cashRegister->name }}</td>
                <td>{{ $s->cashier->name }}</td>
                <td>{{ VerdictLabel::compact(Money::toCents($s->difference), $report->tolerance) }}</td>
                <td>{{ $s->review_status?->label() }}</td>
                <td><a href="{{ route('owner.sessions.show', $s) }}">Ver resumen</a></td>
            </tr>
        @empty
            <tr><td colspan="7">Sin turnos.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
