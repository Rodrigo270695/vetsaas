<?php

return [
    'flash' => [
        'guardado' => 'Consultation charges saved.',
        'confirmado' => 'Pre-invoice confirmed. Linked product stock was deducted.',
        'solo_borrador' => 'You can only edit while the status is draft.',
        'sin_lineas' => 'Add at least one line before confirming.',
        'sin_sede_stock' => 'No active branch available to deduct inventory.',
        'stock_insuficiente' => 'Insufficient stock to confirm consultation products.',
    ],
    'stock' => [
        'notas' => 'Consultation charge: :concepto (visit :consulta)',
    ],
    'ticket' => [
        'document_title' => 'Pre-invoice (reference)',
        'subtitle' => 'Pre-invoice / consultation charges',
        'tipo_servicio' => 'Service',
        'tipo_producto' => 'Product',
        'tipo_otro' => 'Other',
        'estado_borrador' => 'DRAFT',
        'estado_confirmado' => 'CONFIRMED',
        'paciente' => 'Patient',
        'veterinario' => 'Veterinarian',
        'atencion' => 'Visit date',
        'label_ruc' => 'Tax ID :num',
        'label_telefono' => 'Tel. :tel',
        'col_concepto' => 'Item',
        'col_cant' => 'Qty',
        'col_pu' => 'Unit',
        'subtotal' => 'Subtotal (excl. VAT):',
        'igv' => 'VAT (:pct%):',
        'total' => 'TOTAL:',
        'hint_precios_con_igv' => 'Unit prices include VAT (:pct%).',
        'disclaimer' => 'Internal document. Not a payment receipt or a tax invoice.',
        'ancho_papel' => 'Configured print width: :mm mm (thermal printer).',
    ],
];
