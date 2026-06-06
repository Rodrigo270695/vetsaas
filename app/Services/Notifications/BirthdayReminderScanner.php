<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\ClinicSetting;
use App\Models\Paciente;
use App\Support\WhatsApp\WhatsAppChatId;
use Carbon\CarbonInterface;

final class BirthdayReminderScanner
{
    public function __construct(
        private readonly NotificationQueueService $queue,
        private readonly ReminderMessageBuilder $messages,
    ) {}

    public function scan(?CarbonInterface $today = null): int
    {
        $today ??= now();
        $setting = ClinicSetting::query()->first();

        if (! $setting?->recordatorio_cumple_activo) {
            return 0;
        }

        $clinicName = $this->messages->clinicDisplayName($setting);
        $month = (int) $today->month;
        $day = (int) $today->day;
        $enqueued = 0;

        $pacientes = Paciente::query()
            ->with('propietario')
            ->where('activo', true)
            ->whereNotNull('fecha_nacimiento')
            ->whereMonth('fecha_nacimiento', $month)
            ->whereDay('fecha_nacimiento', $day)
            ->get();

        foreach ($pacientes as $paciente) {
            $phone = $paciente->propietario?->telefono;
            $chatId = WhatsAppChatId::fromPhone($phone);
            if ($chatId === null) {
                continue;
            }

            $owner = trim(
                (string) ($paciente->propietario?->nombres ?? '').' '
                .(string) ($paciente->propietario?->apellidos ?? ''),
            );

            $created = $this->queue->enqueue(
                tipo: 'cumple_paciente',
                destinatario: $chatId,
                cuerpo: $this->messages->cumple(
                    $clinicName,
                    $owner !== '' ? $owner : 'cliente',
                    (string) $paciente->nombre,
                ),
                enviarAt: now(),
                destinatarioNombre: $owner !== '' ? $owner : null,
                referenciaTipo: 'paciente',
                referenciaId: $paciente->id,
                dedupeKey: 'cumple_paciente:'.$paciente->id.':'.$today->toDateString(),
            );

            if ($created instanceof \App\Models\NotificationQueue) {
                $enqueued++;
            }
        }

        return $enqueued;
    }
}
