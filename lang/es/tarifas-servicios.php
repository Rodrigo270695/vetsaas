<?php

return [
    'grooming' => [
        'created' => 'Tarifa de grooming registrada.',
        'updated' => 'Tarifa de grooming actualizada.',
        'deleted' => 'Tarifa de grooming eliminada.',
        'missing_table' => 'Falta la tabla de servicios de grooming. Ejecuta las migraciones del tenant.',
        'missing_legacy_table' => 'Falta la tabla de tarifas legacy de grooming. Ejecuta las migraciones del tenant.',
        'save_failed' => 'No se pudo guardar el servicio. Verifica migraciones del tenant (t094/t095) o revisa storage/logs/laravel.log.',
    ],
    'hotel' => [
        'created' => 'Tarifa de hotel registrada.',
        'updated' => 'Tarifa de hotel actualizada.',
        'deleted' => 'Tarifa de hotel eliminada.',
        'missing_table' => 'Falta la tabla de tipos de hotel. Ejecuta las migraciones del tenant.',
        'missing_legacy_table' => 'Falta la tabla de tarifas legacy de hotel. Ejecuta las migraciones del tenant.',
        'save_failed' => 'No se pudo guardar el tipo de estancia. Verifica migraciones del tenant (t095) o revisa storage/logs/laravel.log.',
    ],
];
