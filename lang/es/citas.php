<?php

return [
    'flash' => [
        'created' => 'Cita registrada correctamente.',
        'updated' => 'Cita actualizada correctamente.',
        'rescheduled' => 'Cita reprogramada correctamente.',
        'deleted' => 'Cita eliminada correctamente.',
        'cancelled' => 'Cita cancelada correctamente.',
        'whatsapp_queued' => 'WhatsApp de confirmación enviado al propietario.',
        'whatsapp_no_phone' => 'La cita se guardó, pero el propietario no tiene un número de WhatsApp registrado.',
        'whatsapp_queue_failed' => 'La cita se guardó, pero no se pudo enviar el WhatsApp.',
    ],
    'validation' => [
        'cancel_not_allowed' => 'Esta cita no se puede cancelar en su estado actual.',
        'inicio_pasado' => 'No puedes agendar ni reprogramar una cita en una fecha u hora ya pasada.',
    ],
    'reschedule' => [
        'estado_bloqueado' => 'Solo se pueden reprogramar citas programadas o confirmadas.',
    ],
];
