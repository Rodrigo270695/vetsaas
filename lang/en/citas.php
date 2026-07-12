<?php

return [
    'flash' => [
        'created' => 'Appointment created successfully.',
        'updated' => 'Appointment updated successfully.',
        'rescheduled' => 'Appointment rescheduled successfully.',
        'deleted' => 'Appointment deleted successfully.',
        'cancelled' => 'Appointment cancelled successfully.',
    ],
    'validation' => [
        'cancel_not_allowed' => 'This appointment cannot be cancelled in its current state.',
    ],
    'reschedule' => [
        'estado_bloqueado' => 'Only scheduled or confirmed appointments can be rescheduled.',
    ],
];
