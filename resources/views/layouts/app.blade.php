<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Caja') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2.0.6/css/pico.min.css">
    <style>
        .ok { color: #2e7d32; } .bad { color: #c62828; } .warn { color: #ef6c00; }
        .num { text-align: right; white-space: nowrap; }
        .banner { padding: 1rem; border-radius: .5rem; margin-bottom: 1rem; }
        .banner.ok { background: #e8f5e9; } .banner.bad { background: #ffebee; } .banner.warn { background: #fff3e0; }
        .flash { background: #e3f2fd; padding: .75rem 1rem; border-radius: .5rem; margin-bottom: 1rem; }
        .errors { background: #ffebee; color: #b71c1c; padding: .75rem 1rem; border-radius: .5rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
<main class="container">
    @auth
        <nav>
            <ul><li><strong><a href="{{ route('cash.index') }}">{{ config('app.name') }}</a></strong></li></ul>
            <ul>
                <li>{{ auth()->user()->name }} ({{ auth()->user()->isOwner() ? 'dueña' : 'cajero' }})</li>
                <li>
                    <form method="post" action="{{ route('logout') }}" style="margin:0">
                        @csrf
                        <button class="secondary outline" style="margin:0">Salir</button>
                    </form>
                </li>
            </ul>
        </nav>
    @endauth

    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="errors">
            <ul style="margin:0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>
</body>
</html>
