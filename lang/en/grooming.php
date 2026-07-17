<?php

return [
    'flash' => [
        'created' => 'Grooming appointment saved successfully.',
        'updated' => 'Grooming appointment updated successfully.',
        'deleted' => 'Grooming appointment deleted successfully.',
        'foto_uploaded' => 'Process photo saved.',
        'foto_deleted' => 'Photo deleted.',
        'fotos_max' => 'This appointment already has the maximum number of photos (8).',
        'estado_updated' => 'Appointment status updated.',
        'estado_updated_whatsapp' => 'Status updated and owner notified via WhatsApp.',
        'estado_updated_sin_whatsapp' => 'Status updated, but WhatsApp notification failed.',
        'whatsapp_enviado' => ':count photo(s) sent to the owner via WhatsApp.',
        'whatsapp_sin_fotos' => 'There are no pending photos to send.',
        'whatsapp_no_phone' => 'The owner does not have a valid WhatsApp phone number.',
        'whatsapp_fallo' => 'Could not send the photos via WhatsApp.',
    ],
    'servicios' => [
        'flash' => [
            'created' => 'Grooming service created.',
            'updated' => 'Grooming service updated.',
            'deleted' => 'Grooming service deleted.',
        ],
        'errors' => [
            'en_uso' => 'Cannot delete: appointments use this service.',
        ],
    ],
];
