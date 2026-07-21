<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\ClinicSetting;
use App\Models\NotificationQueue;
use App\Models\VacunaAplicada;
use App\Support\WhatsApp\WhatsAppChatId;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

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

        $targetDates = collect($setting->recordatorioVacunaDiasAntesOpciones())
            ->mapWithKeys(fn (int $dias): array => [
                $today->copy()->addDays($dias)->toDateString() => $dias,
            ]);
        $clinicName = $this->messages->clinicDisplayName($setting);
        $enqueued = 0;

        $vacunas = VacunaAplicada::query()
            ->with(['paciente.propietario'])
            ->where(function ($query) use ($targetDates): void {
                foreach ($targetDates->keys() as $targetDate) {
                    $query->orWhereDate('fecha_proxima_sugerida', $targetDate);
                }
            })
            ->get();

        foreach ($vacunas as $vacuna) {
            $targetDate = Carbon::parse($vacuna->fecha_proxima_sugerida)->toDateString();
            if (! $targetDates->has($targetDate)) {
                continue;
            }
            $diasAntes = (int) $targetDates->get($targetDate);
            $legacyDays = max(1, (int) ($setting->recordatorio_vacuna_dias_antes ?? 7));
            $dedupeKey = 'vacuna_proxima:'.$vacuna->id.':'.$targetDate;
            if ($diasAntes !== $legacyDays) {
                $dedupeKey .= ':'.$diasAntes;
            }

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
                    Carbon::parse($targetDate),
                ),
                enviarAt: now(),
                destinatarioNombre: $owner !== '' ? $owner : null,
                referenciaTipo: 'vacuna',
                referenciaId: $vacuna->id,
                dedupeKey: $dedupeKey,
            );

            if ($created instanceof NotificationQueue) {
                $enqueued++;
            }
        }

        return $enqueued;
    }
}
