<?php

return [
    'flash' => [
        'created' => 'Appointment created successfully.',
        'updated' => 'Appointment updated successfully.',
        'rescheduled' => 'Appointment rescheduled successfully.',
        'deleted' => 'Appointment deleted successfully.',
        'cancelled' => 'Appointment cancelled successfully.',
        'whatsapp_queued' => 'WhatsApp confirmation was queued for the owner.',
        'whatsapp_no_phone' => 'Appointment created, but the owner has no WhatsApp number registered.',
        'whatsapp_queue_failed' => 'Appointment created, but the WhatsApp confirmation could not be queued.',
    ],
    'validation' => [
        'cancel_not_allowed' => 'This appointment cannot be cancelled in its current state.',
    ],
    'reschedule' => [
        'estado_bloqueado' => 'Only scheduled or confirmed appointments can be rescheduled.',
    ],
];
