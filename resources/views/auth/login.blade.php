@extends('layouts.base')
@section('title', 'Ingresar')
@section('content')
    <article style="max-width:28rem;margin:3rem auto">
        <h2>Ingresar</h2>
        <form method="post" action="{{ route('login') }}">
            @csrf
            <label>Correo
                <input type="email" name="email" value="{{ old('email') }}" required autofocus>
            </label>
            <label>Contraseña
                <input type="password" name="password" required>
            </label>
            <button type="submit">Entrar</button>
        </form>
        <small>Demo: owner@tienda.test / cajero@tienda.test — contraseña <code>password</code></small>
    </article>
@endsection
