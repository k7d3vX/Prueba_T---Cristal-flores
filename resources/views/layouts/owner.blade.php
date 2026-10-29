@extends('layouts.base')
@section('nav')
    <nav>
        <ul><li><strong><a href="{{ route('owner.dashboard') }}">{{ config('app.name') }}</a></strong></li></ul>
        <ul>
            <li><a href="{{ route('owner.dashboard') }}">Panel</a></li>
            <li><a href="{{ route('owner.history') }}">Historial</a></li>
            <li><a href="{{ route('owner.audit-log') }}">Bitácora</a></li>
            <li>{{ auth()->user()->name }} (dueña)</li>
            <li>
                <form method="post" action="{{ route('logout') }}" style="margin:0">
                    @csrf
                    <button class="secondary outline" style="margin:0">Salir</button>
                </form>
            </li>
        </ul>
    </nav>
@endsection
