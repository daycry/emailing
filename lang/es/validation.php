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
    'max' => ['string' => 'El campo :attribute no puede superar :max caracteres.', 'array' => 'El campo :attribute no puede contener más de :max elementos.'],
    'min' => ['string' => 'El campo :attribute debe contener al menos :min caracteres.'],
    'attributes' => ['name' => 'nombre', 'account_name' => 'nombre de la cuenta', 'email' => 'email', 'password' => 'contraseña', 'role' => 'acceso', 'scopes' => 'permisos', 'scopes.*' => 'permiso'],
];
