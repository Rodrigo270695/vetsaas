<?php

return [
    'flash' => [
        'guardado' => 'Cargos de la consulta guardados.',
        'confirmado' => 'Pre-cuenta confirmada. El stock de productos vinculados fue descontado.',
        'solo_borrador' => 'Solo se puede editar mientras el estado es borrador.',
        'ya_cobrado_no_editable' => 'Esta pre-cuenta ya fue cobrada en caja y no se puede editar.',
        'sin_lineas' => 'Añade al menos una línea antes de confirmar.',
        'sin_sede_stock' => 'No hay sede activa para descontar inventario.',
        'stock_insuficiente' => 'Stock insuficiente para confirmar los productos de la consulta.',
    ],
    'stock' => [
        'notas' => 'Cargo consulta: :concepto (consulta :consulta)',
    ],
    'ticket' => [
        'document_title' => 'Pre-cuenta (referencia)',
        'subtitle' => 'Pre-cuenta / cargos de consulta',
        'tipo_servicio' => 'Servicio',
        'tipo_producto' => 'Producto',
        'tipo_otro' => 'Otro',
        'estado_borrador' => 'BORRADOR',
        'estado_confirmado' => 'CONFIRMADO',
        'paciente' => 'Paciente',
        'veterinario' => 'Veterinario',
        'atencion' => 'Fecha de atención',
        'label_ruc' => 'RUC :num',
        'label_telefono' => 'Tel. :tel',
        'col_concepto' => 'Concepto',
        'col_cant' => 'Cant.',
        'col_pu' => 'P.U.',
        'subtotal' => 'Subtotal (sin IGV):',
        'igv' => 'IGV (:pct%):',
        'total' => 'TOTAL:',
        'hint_precios_con_igv' => 'Los precios unitarios incluyen IGV (:pct%).',
        'disclaimer' => 'Documento interno. No es comprobante de pago ni comprobante fiscal SUNAT/SEE.',
        'ancho_papel' => 'Ancho de impresión configurado: :mm mm (ticketera).',
    ],
];
