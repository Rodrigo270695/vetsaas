<?php

return [
    'flash' => [
        'created' => 'Appointment created successfully.',
        'updated' => 'Appointment updated successfully.',
        'rescheduled' => 'Appointment rescheduled successfully.',
        'deleted' => 'Appointment deleted successfully.',
        'cancelled' => 'Appointment cancelled successfully.',
        'aperturada' => 'Appointment in progress. Complete the consultation.',
        'aperturada_seguimiento' => 'Appointment in progress. Continuing follow-up on the open consultation.',
        'aperturar_estado_invalido' => 'Only waiting appointments (scheduled or confirmed) can be started.',
        'whatsapp_queued' => 'WhatsApp confirmation sent to the owner.',
        'whatsapp_no_phone' => 'The appointment was saved, but the owner has no WhatsApp number registered.',
        'whatsapp_queue_failed' => 'The appointment was saved, but the WhatsApp message could not be sent.',
    ],
    'validation' => [
        'cancel_not_allowed' => 'This appointment cannot be cancelled in its current state.',
        'inicio_pasado' => 'The appointment date and time cannot be before the current minute.',
    ],
    'reschedule' => [
        'estado_bloqueado' => 'Only scheduled or confirmed appointments can be rescheduled.',
    ],
];
