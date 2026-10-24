<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'email' => 'Introduce una dirección de email válida.',
    'unique' => 'El valor de :attribute ya está registrado.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'string' => 'El campo :attribute debe ser un texto.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'array' => 'El campo :attribute debe ser una lista.',
    'in' => 'El valor seleccionado para :attribute no está permitido.',
    'distinct' => 'El campo :attribute contiene valores repetidos.',
    'accepted' => 'Debes marcar la confirmación para continuar.',
    'date_format' => 'El campo :attribute debe contener una fecha válida.',
    'after_or_equal' => 'La fecha de :attribute debe ser igual o posterior a :date.',
    'before_or_equal' => 'La fecha de :attribute debe ser igual o anterior a :date.',
    'custom' => [
        'from' => ['before_or_equal' => 'La fecha inicial no puede ser posterior a la fecha final.'],
        'consented_at' => [
            'date_format' => 'Introduce una fecha y hora válidas para el consentimiento.',
            'before_or_equal' => 'La fecha de consentimiento no puede ser futura.',
        ],
    ],
    'max' => ['string' => 'El campo :attribute no puede superar :max caracteres.', 'array' => 'El campo :attribute no puede contener más de :max elementos.'],
    'min' => ['string' => 'El campo :attribute debe contener al menos :min caracteres.'],
    'attributes' => ['name' => 'nombre', 'account_name' => 'nombre de la cuenta', 'email' => 'email', 'password' => 'contraseña', 'role' => 'acceso', 'scopes' => 'permisos', 'scopes.*' => 'permiso', 'from' => 'inicio', 'to' => 'fin', 'consented_at' => 'consentimiento'],
];
