<?php

return [
    'documentos' => [
        'flash' => [
            'whatsapp_enviado' => 'Receipt sent via WhatsApp.',
            'whatsapp_no_phone' => 'Enter a valid WhatsApp number to send the receipt.',
            'whatsapp_fallo' => 'Could not send the receipt via WhatsApp.',
            'whatsapp_no_emitido' => 'Only issued receipts can be sent.',
            'whatsapp_sandbox' => 'Test-mode receipts cannot be sent via WhatsApp. Move them to production first.',
            'whatsapp_sin_adjuntos' => 'Select at least one file to send.',
            'status_synced' => ':numero updated: :estado.',
            'status_synced_bulk' => 'Synced :checked receipts (:updated updated; :pending pending, :rejected rejected).',
        ],
    ],
];
