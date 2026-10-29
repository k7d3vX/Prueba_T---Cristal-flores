@extends('layouts.cashier')
@section('title', 'Terminar turno')
@section('content')
    {{-- Caja ciega: ninguna cifra esperada. El cajero cuenta billetes y monedas y pasa a revisar su propio conteo (RN-25, RN-26). --}}
    <h2>Cuenta el efectivo de tu caja</h2>
    <p>{{ $registerName }} · escribe cuántos billetes y monedas hay de cada uno.</p>

    <form method="post" action="{{ route('cashier.close.review') }}">
        @csrf
        <x-denominations />
        <button type="submit">Revisar y enviar</button>
    </form>
    <p><a href="{{ route('cashier.home') }}">← Volver a mi turno</a></p>
@endsection
