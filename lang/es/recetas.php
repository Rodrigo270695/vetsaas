<?php

return [
    'flash' => [
        'created' => 'Receta guardada correctamente.',
        'updated' => 'Receta actualizada correctamente.',
        'deleted' => 'Receta eliminada correctamente.',
    ],
    'validation' => [
        'consulta_invalida' => 'La consulta no corresponde al paciente seleccionado.',
        'consulta_cerrada' => 'No puedes vincular una receta a una consulta ya cerrada.',
    ],
    'pdf' => [
        'document_title' => 'Receta veterinaria',
        'subtitle' => 'Documento para entrega al titular. Uso bajo indicación del profesional.',
        'section_patient' => 'Paciente y titular',
        'label_patient' => 'Paciente',
        'label_owner' => 'Titular',
        'section_context' => 'Datos de la prescripción',
        'label_date' => 'Fecha de la receta',
        'label_status' => 'Estado',
        'label_consulta' => 'Consulta vinculada',
        'label_vet' => 'Profesional',
        'label_sede' => 'Sede',
        'label_obs' => 'Observaciones',
        'section_meds' => 'Medicamentos',
        'table_med' => 'Medicamento',
        'table_posology' => 'Posología',
        'table_days' => 'Duración',
        'table_instructions' => 'Instrucciones al titular',
        'empty_meds' => 'No hay líneas de medicación registradas.',
        'days_suffix' => 'días',
        'footer_generated' => 'Generado el :fecha',
        'footer_disclaimer' => 'Este documento no sustituye la indicación verbal del veterinario. Ante dudas o reacciones adversas, contacte con la clínica.',
        'estados' => [
            'borrador' => 'Borrador',
            'emitida' => 'Emitida',
            'anulada' => 'Anulada',
        ],
        'anulada_stamp' => 'RECETA ANULADA',
    ],
];
