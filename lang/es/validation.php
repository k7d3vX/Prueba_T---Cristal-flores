<?php

// Mensajes mínimos en español para los campos del sistema (el resto cae al inglés).
return [
    'required' => 'El campo :attribute es obligatorio.',
    'numeric' => 'El campo :attribute debe ser un número.',
    'decimal' => 'El campo :attribute admite como máximo :decimal decimales.',
    'gt' => ['numeric' => 'El campo :attribute debe ser mayor que :value.'],
    'min' => ['numeric' => 'El campo :attribute debe ser al menos :min.'],
    'max' => [
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
        'regex' => 'El campo :attribute debe ser un monto positivo con hasta 2 decimales (sin signos negativos).',
    'string' => 'El campo :attribute no puede superar :max caracteres.',
    ],
    'string' => 'El campo :attribute debe ser texto.',
    'email' => 'El campo :attribute debe ser un correo válido.',
    'exists' => 'El valor de :attribute no es válido.',
    'enum' => 'El valor de :attribute no es válido.',

    'attributes' => [
        'amount' => 'monto',
        'method' => 'forma de pago',
        'reason' => 'motivo',
        'cashier_id' => 'cajero',
        'cash_register_id' => 'caja',
        'opening_float' => 'fondo inicial',
        'closing_mode' => 'modo de cierre',
        'counted_cash' => 'efectivo contado',
        'email' => 'correo',
        'password' => 'contraseña',
    ],
];
