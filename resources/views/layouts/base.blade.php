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
        /* Zona del cajero: móvil primero, botones de al menos 56 px */
        body.cashier main { max-width: 36rem; }
        body.cashier button, body.cashier [role=button] { min-height: 56px; font-size: 1.15rem; width: 100%; }
        body.cashier input, body.cashier select { min-height: 56px; font-size: 1.3rem; }
        body.cashier .two { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
        .keypad { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; margin-bottom: 1rem; }
        .keypad button { margin: 0; font-size: 1.4rem; }
        .denoms { display: grid; grid-template-columns: repeat(auto-fill, minmax(7rem, 1fr)); gap: .5rem; }
        body.cashier .recent td { padding: .5rem .25rem; font-size: 1.05rem; }
    </style>
</head>
<body class="@yield('body_class')">
<main class="container">
    @yield('nav')

    @if (session('status'))
        <div class="flash" role="status">✔ {{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="errors" role="alert">
            <ul style="margin:0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>
<script>
    // La confirmación de cada registro se ve unos segundos y se va (mostrador).
    setTimeout(function () { document.querySelectorAll('.flash').forEach(function (e) { e.style.display = 'none'; }); }, 4000);
</script>
</body>
</html>
