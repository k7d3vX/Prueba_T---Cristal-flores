<?php

/** Umbrales de la tolerancia y de las alertas de la dueña (RN-29, RN-30). Montos en Bs. */
return [
    // |diferencia| ≤ tolerancia se etiqueta «Cuadra»; la diferencia exacta siempre se guarda y se muestra.
    'tolerance' => '5.00',

    // ≥ count faltantes sobre la tolerancia en los últimos `turns` turnos del mismo cajero (ventana de `window_days` días).
    'repeated_shortages' => ['count' => 3, 'turns' => 7, 'window_days' => 60],

    // Más de `limit` Bs en faltantes pequeños (dentro de la tolerancia) durante `days` días.
    'accumulated_shortage' => ['limit' => '20.00', 'days' => 30],

    'high_withdrawals_percent' => 25, // salidas > % de las ventas en efectivo del turno
    'review_hours' => 24,             // cierre sin revisar más de estas horas
    'long_turn_hours' => 12,          // turno abierto más de estas horas
    'max_voids' => 2,                 // más de estas anulaciones en un turno
];
