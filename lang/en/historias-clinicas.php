<?php

return [
    'errors' => [
        'no_editable_cerrada' => 'This visit is closed. Reopen it to edit.',
    ],
    'flash' => [
        'created' => 'Visit record saved successfully.',
        'created_open_plan' => 'Visit saved. You can define or review the medication plan and follow-up from this screen.',
        'updated' => 'Visit record updated successfully.',
        'updated_open_plan' => 'Visit updated. Continue with the medication plan and follow-up.',
        'deleted' => 'Visit record deleted successfully.',
        'cerrada' => 'Visit closed successfully.',
        'cerrar_abiertas' => ':count open visit(s) closed successfully.',
        'cerrar_abiertas_ninguna' => 'There are no open visits to close.',
        'reabierta' => 'Visit reopened; you can edit it again.',
        'ya_cerrada' => 'The visit was already closed.',
        'ya_abierta' => 'The visit was already open.',
        'plan_saved' => 'Medication plan saved successfully.',
        'plan_seguimiento_created' => 'Follow-up note saved successfully.',
        'plan_consulta_cerrada' => 'The plan cannot be changed because the visit is closed.',
    ],
    'plan' => [
        'stock' => [
            'notas' => 'Treatment plan: :medicamento (visit :consulta)',
            'sede_requerida' => 'Select an active branch to deduct plan inventory.',
            'cantidad_requerida' => 'Enter the quantity to deduct when linking an inventory product.',
        ],
    ],
];
