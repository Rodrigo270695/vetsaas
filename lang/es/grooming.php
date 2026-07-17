<?php

return [
    'flash' => [
        'created' => 'Turno de grooming registrado correctamente.',
        'updated' => 'Turno de grooming actualizado correctamente.',
        'deleted' => 'Turno de grooming eliminado correctamente.',
        'foto_uploaded' => 'Foto del proceso guardada.',
        'foto_deleted' => 'Foto eliminada.',
        'fotos_max' => 'Este turno ya tiene el máximo de fotos permitidas (8).',
        'estado_updated' => 'Estado del turno actualizado.',
        'estado_updated_whatsapp' => 'Estado actualizado y propietario notificado por WhatsApp.',
        'estado_updated_sin_whatsapp' => 'Estado actualizado, pero no se pudo notificar por WhatsApp.',
        'whatsapp_enviado' => 'Se enviaron :count foto(s) por WhatsApp al propietario.',
        'whatsapp_sin_fotos' => 'No hay fotos pendientes para enviar.',
        'whatsapp_no_phone' => 'El propietario no tiene un teléfono válido para WhatsApp.',
        'whatsapp_fallo' => 'No se pudo enviar las fotos por WhatsApp.',
    ],
    'servicios' => [
        'flash' => [
            'created' => 'Servicio de grooming creado.',
            'updated' => 'Servicio de grooming actualizado.',
            'deleted' => 'Servicio de grooming eliminado.',
        ],
        'errors' => [
            'en_uso' => 'No se puede eliminar: hay turnos que usan este servicio.',
        ],
    ],
];
