<?php

return [
    'flash' => [
        'created' => 'Vacunación registrada correctamente.',
        'updated' => 'Vacunación actualizada correctamente.',
        'deleted' => 'Vacunación eliminada correctamente.',
    ],
    'validation' => [
        'consulta_invalida' => 'La consulta no pertenece a este paciente.',
        'consulta_cerrada' => 'No puedes vincular una aplicación a una consulta ya cerrada.',
    ],
    'stock' => [
        'notas' => 'Vacunación: :vacuna — paciente :paciente (registro :id).',
        'reversion' => 'Reversión por edición o anulación de vacunación (mov. salida :id).',
        'insufficient_stock' => 'No hay stock suficiente en la sede elegida para este producto.',
        'revert_failed' => 'No se pudo devolver el stock al eliminar la vacunación.',
    ],
];
