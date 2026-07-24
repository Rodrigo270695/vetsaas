<?php

return [
    'flash' => [
        'created' => 'Cita registrada correctamente.',
        'updated' => 'Cita actualizada correctamente.',
        'rescheduled' => 'Cita reprogramada correctamente.',
        'deleted' => 'Cita eliminada correctamente.',
        'cancelled' => 'Cita cancelada correctamente.',
        'aperturada' => 'Cita en atención. Completa la consulta.',
        'aperturada_seguimiento' => 'Cita en atención. Continuando el seguimiento en la consulta abierta.',
        'aperturada_reactivada' => 'Consulta reactivada. Continúa el seguimiento en la historia clínica.',
        'aperturar_estado_invalido' => 'Solo se pueden aperturar citas en espera (programadas o confirmadas).',
        'aperturar_consulta_invalida' => 'La consulta seleccionada no es válida para este paciente.',
        'whatsapp_queued' => 'WhatsApp de confirmación enviado al propietario.',
        'whatsapp_no_phone' => 'La cita se guardó, pero el propietario no tiene un número de WhatsApp registrado.',
        'whatsapp_queue_failed' => 'La cita se guardó, pero no se pudo enviar el WhatsApp.',
    ],
    'validation' => [
        'cancel_not_allowed' => 'Esta cita no se puede cancelar en su estado actual.',
        'inicio_pasado' => 'La fecha y hora de la cita no puede ser anterior al minuto actual.',
    ],
    'reschedule' => [
        'estado_bloqueado' => 'Solo se pueden reprogramar citas programadas o confirmadas.',
    ],
];
