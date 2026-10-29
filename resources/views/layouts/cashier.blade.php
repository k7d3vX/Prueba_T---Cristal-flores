@extends('layouts.base')
@section('body_class', 'cashier')
@section('nav')
    {{-- Barra mínima del cajero: nombre y salir. Sin menús, sin cifras. --}}
    <nav>
        <ul><li><strong>{{ auth()->user()->name }}</strong></li></ul>
        <ul>
            <li>
                <form method="post" action="{{ route('logout') }}" style="margin:0">
                    @csrf
                    <button class="secondary outline" style="margin:0;min-height:44px;width:auto">Salir</button>
                </form>
            </li>
        </ul>
    </nav>
@endsection
