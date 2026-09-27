<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\ClinicSetting;
use App\Models\NotificationQueue;
use App\Models\Receta;
use App\Support\Notifications\ReminderSendWindow;
use App\Support\WhatsApp\WhatsAppChatId;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

final class ConsultaControlReminderScanner
{
    public function __construct(
        private readonly NotificationQueueService $queue,
        private readonly ReminderMessageBuilder $messages,
    ) {}

    public function scan(?CarbonInterface $today = null): int
    {
        if (
            ! Schema::hasTable('recetas')
            || ! Schema::hasColumn('recetas', 'consulta_control_at')
            || ! Schema::hasColumn('cfg_clinic_settings', 'recordatorio_consulta_control_activo')
        ) {
            return 0;
        }

        $today ??= now()->startOfDay();
        $setting = ClinicSetting::query()->first();

        if (! $setting?->recordatorio_consulta_control_activo) {
            return 0;
        }

        $targetDates = collect($setting->recordatorioConsultaControlDiasAntesOpciones())
            ->mapWithKeys(fn (int $dias): array => [
                $today->copy()->addDays($dias)->toDateString() => $dias,
            ]);
        if ($targetDates->isEmpty()) {
            return 0;
        }

        $clinicName = $this->messages->clinicDisplayName($setting);
        $enqueued = 0;

        $recetas = Receta::query()
            ->with(['paciente.propietario'])
            ->where('estado', Receta::ESTADO_EMITIDA)
            ->where(function ($query) use ($targetDates): void {
                foreach ($targetDates->keys() as $targetDate) {
                    $query->orWhereDate('consulta_control_at', $targetDate);
                }
            })
            ->get();

        foreach ($recetas as $receta) {
            if ($receta->consulta_control_at === null) {
                continue;
            }

            $targetDate = $receta->consulta_control_at->toDateString();
            if (! $targetDates->has($targetDate)) {
                continue;
            }

            $phone = $receta->paciente?->propietario?->telefono;
            $chatId = WhatsAppChatId::fromPhone($phone);
            if ($chatId === null) {
                continue;
            }

            $propietario = $receta->paciente?->propietario;
            $owner = trim((string) ($propietario?->razon_social ?: trim(
                (string) ($propietario?->nombres ?? '').' '.(string) ($propietario?->apellidos ?? ''),
            )));

            $created = $this->queue->enqueue(
                tipo: 'consulta_control',
                destinatario: $chatId,
                cuerpo: $this->messages->consultaControl(
                    $clinicName,
                    $owner !== '' ? $owner : 'cliente',
                    (string) ($receta->paciente?->nombre ?? 'tu mascota'),
                    Carbon::parse($targetDate),
                ),
                enviarAt: ReminderSendWindow::enqueueAt(),
                destinatarioNombre: $owner !== '' ? $owner : null,
                referenciaTipo: 'receta',
                referenciaId: $receta->id,
                dedupeKey: 'consulta_control:'.$receta->id.':'.$targetDate.':'.$targetDates->get($targetDate),
            );

            if ($created instanceof NotificationQueue) {
                $enqueued++;
            }
        }

        return $enqueued;
    }
}
