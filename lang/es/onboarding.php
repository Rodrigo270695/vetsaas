<?php

return [

    'requires_sede' => 'Crea al menos una sede activa para usar el resto del sistema.',

    'banner' => [
        'title' => 'Configura tu clínica',
        'subtitle' => 'Completa estos pasos para dejar VetSaaS listo. Sin sede no podrás usar caja, citas ni inventario.',
        'progress' => ':completed de :total pasos',
        'required_badge' => 'Obligatorio',
        'locked_hint' => 'Completa la sede primero',
        'cta' => 'Ir a configurar',
        'completed' => 'Completado',
    ],

    'steps' => [
        'sede' => [
            'title' => 'Crear tu primera sede',
            'description' => 'Sucursal donde atiendes. Requisito mínimo para operar.',
        ],
        'clinic' => [
            'title' => 'Datos de la clínica',
            'description' => 'RUC, razón social y logo para comprobantes.',
        ],
        'paciente' => [
            'title' => 'Registrar un paciente',
            'description' => 'Primera mascota para citas e historias clínicas.',
        ],
    ],

];
