@extends('layouts.owner')
@section('title', 'Bitácora')
@section('content')
    <h2>Bitácora</h2>
    <p><small>Quién hizo qué y cuándo (últimas 200 acciones). Nada se edita ni se borra.</small></p>
    <table>
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Turno</th><th>Detalle</th></tr></thead>
        <tbody>
        @forelse ($logs as $log)
            <tr>
                <td>{{ $log->created_at->format('d/m H:i') }}</td>
                <td>{{ $log->user?->name ?? 'Sistema' }}</td>
                <td>{{ $log->action }}</td>
                <td>@if ($log->cash_session_id)<a href="{{ route('owner.sessions.show', $log->cash_session_id) }}">#{{ $log->cash_session_id }}</a>@endif</td>
                <td>{{ $log->detail }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Sin registros.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
