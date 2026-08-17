<?php

return [

    'requires_sede' => 'Te recomendamos crear al menos una sede activa con ubicación.',
    'requires_sede_geo' => 'Completa departamento, provincia y distrito en tu sede activa.',

    'banner' => [
        'title' => 'Configura tu clínica',
        'subtitle' => 'Completa estos pasos para dejar VetSaaS listo. Puedes explorar la plataforma; con sede configurarás mejor caja, citas e inventario.',
        'progress' => ':completed de :total pasos',
        'required_badge' => 'Recomendado',
        'locked_hint' => 'Conviene completar la sede primero',
        'cta' => 'Ir a configurar',
        'completed' => 'Completado',
    ],

    'steps' => [
        'sede' => [
            'title' => 'Crear tu primera sede',
            'description' => 'Sucursal donde atiendes. Recomendado para operar con claridad.',
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
