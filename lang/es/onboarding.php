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
            'description' => 'Define la sucursal donde atiendes. Es el requisito mínimo para operar.',
        ],
        'clinic' => [
            'title' => 'Datos de la clínica',
            'description' => 'RUC, razón social y logo para comprobantes y comunicaciones.',
        ],
        'team' => [
            'title' => 'Invitar a tu equipo',
            'description' => 'Agrega veterinarios, recepción u otros usuarios de la clínica.',
        ],
        'paciente' => [
            'title' => 'Registrar un paciente',
            'description' => 'Carga la primera mascota para citas e historias clínicas.',
        ],
        'activity' => [
            'title' => 'Primera cita o venta',
            'description' => 'Agenda una cita o registra una venta para probar el flujo real.',
        ],
        'fel' => [
            'title' => 'Facturación electrónica',
            'description' => 'Conecta APISUNAT y configura series para boletas y facturas.',
        ],
    ],

];
