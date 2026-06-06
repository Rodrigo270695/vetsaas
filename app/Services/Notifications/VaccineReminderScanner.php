<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\ClinicSetting;
use App\Models\VacunaAplicada;
use App\Support\WhatsApp\WhatsAppChatId;
use Carbon\CarbonInterface;

final class VaccineReminderScanner
{
    public function __construct(
        private readonly NotificationQueueService $queue,
        private readonly ReminderMessageBuilder $messages,
    ) {}

    public function scan(?CarbonInterface $today = null): int
    {
        $today ??= now()->startOfDay();
        $setting = ClinicSetting::query()->first();

        if (! $setting?->recordatorio_vacuna_activo) {
            return 0;
        }

        $diasAntes = max(1, (int) $setting->recordatorio_vacuna_dias_antes);
        $targetDate = $today->copy()->addDays($diasAntes)->toDateString();
        $clinicName = $this->messages->clinicDisplayName($setting);
        $enqueued = 0;

        $vacunas = VacunaAplicada::query()
            ->with(['paciente.propietario'])
            ->whereDate('fecha_proxima_sugerida', $targetDate)
            ->get();

        foreach ($vacunas as $vacuna) {
            $phone = $vacuna->paciente?->propietario?->telefono;
            $chatId = WhatsAppChatId::fromPhone($phone);
            if ($chatId === null) {
                continue;
            }

            $owner = trim(
                (string) ($vacuna->paciente?->propietario?->nombres ?? '').' '
                .(string) ($vacuna->paciente?->propietario?->apellidos ?? ''),
            );

            $created = $this->queue->enqueue(
                tipo: 'vacuna_proxima',
                destinatario: $chatId,
                cuerpo: $this->messages->vacuna(
                    $clinicName,
                    $owner !== '' ? $owner : 'cliente',
                    (string) ($vacuna->paciente?->nombre ?? 'tu mascota'),
                    (string) $vacuna->nombre_vacuna,
                    Carbon::parse($vacuna->fecha_proxima_sugerida),
                ),
                enviarAt: now(),
                destinatarioNombre: $owner !== '' ? $owner : null,
                referenciaTipo: 'vacuna',
                referenciaId: $vacuna->id,
                dedupeKey: 'vacuna_proxima:'.$vacuna->id.':'.$targetDate,
            );

            if ($created instanceof \App\Models\NotificationQueue) {
                $enqueued++;
            }
        }

        return $enqueued;
    }
}
