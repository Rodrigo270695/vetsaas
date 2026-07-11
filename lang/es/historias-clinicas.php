<?php

return [
    'errors' => [
        'no_editable_cerrada' => 'Esta consulta está cerrada. Reábrela para poder editarla.',
    ],
    'flash' => [
        'created' => 'Consulta registrada correctamente.',
        'created_open_plan' => 'Consulta registrada. Desde esta pantalla puedes definir o revisar el plan de medicación y el seguimiento.',
        'updated' => 'Consulta actualizada correctamente.',
        'deleted' => 'Consulta eliminada correctamente.',
        'cerrada' => 'Consulta cerrada correctamente.',
        'cerrar_abiertas' => ':count consulta(s) abierta(s) cerrada(s) correctamente.',
        'cerrar_abiertas_ninguna' => 'No hay consultas abiertas para cerrar.',
        'reabierta' => 'Consulta reabierta; ya puedes editarla de nuevo.',
        'ya_cerrada' => 'La consulta ya estaba cerrada.',
        'ya_abierta' => 'La consulta ya estaba abierta.',
        'plan_saved' => 'Plan de medicación guardado correctamente.',
        'plan_seguimiento_created' => 'Nota de seguimiento registrada correctamente.',
        'plan_consulta_cerrada' => 'No se puede modificar el plan: la consulta está cerrada.',
    ],
    'plan' => [
        'stock' => [
            'notas' => 'Plan tratamiento: :medicamento (consulta :consulta)',
            'sede_requerida' => 'Selecciona una sede activa para descontar inventario del plan.',
            'cantidad_requerida' => 'Indica la cantidad a descontar cuando vinculas un producto de inventario.',
        ],
    ],
];
