<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Cita;
use App\Models\ClinicSetting;
use App\Models\NotificationQueue;
use App\Support\WhatsApp\WhatsAppChatId;
use Carbon\CarbonInterface;

final class AppointmentReminderScanner
{
    public function __construct(
        private readonly NotificationQueueService $queue,
        private readonly ReminderMessageBuilder $messages,
    ) {}

    /**
     * @return array{cita_dias: int, cita_2h: int}
     */
    public function scan(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $setting = ClinicSetting::query()->first();

        $clinicName = $this->messages->clinicDisplayName($setting);
        $countDays = 0;
        $count2h = 0;

        foreach ($setting?->recordatorioCitaDiasAntesOpciones() ?? [] as $days) {
            $countDays += $this->scanWindow(
                $now->copy()->addDays($days),
                $days === 2 ? 'cita_48h' : 'cita_'.$days.'d',
                fn (Cita $cita) => $this->messages->cita48h(
                    $clinicName,
                    $this->ownerName($cita),
                    $this->petName($cita),
                    $cita->inicio_at,
                ),
            );
        }

        if ($setting?->recordatorio_2h_activo) {
            $count2h = $this->scanWindow(
                $now->copy()->addHours(2),
                'cita_2h',
                fn (Cita $cita) => $this->messages->cita2h(
                    $clinicName,
                    $this->ownerName($cita),
                    $this->petName($cita),
                    $cita->inicio_at,
                ),
            );
        }

        return ['cita_dias' => $countDays, 'cita_2h' => $count2h];
    }

    /**
     * @param  callable(Cita): string  $bodyBuilder
     */
    private function scanWindow(CarbonInterface $target, string $tipo, callable $bodyBuilder): int
    {
        $from = $target->copy()->subMinutes(30);
        $to = $target->copy()->addMinutes(30);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Cita> $citas */
        $citas = Cita::query()
            ->with(['paciente.propietario'])
            ->whereIn('estado', [Cita::ESTADO_PROGRAMADA, Cita::ESTADO_CONFIRMADA])
            ->whereBetween('inicio_at', [$from, $to])
            ->get();

        $enqueued = 0;

        foreach ($citas as $cita) {
            $phone = $cita->paciente?->propietario?->telefono;
            $chatId = WhatsAppChatId::fromPhone($phone);
            if ($chatId === null) {
                continue;
            }

            $created = $this->queue->enqueue(
                tipo: $tipo,
                destinatario: $chatId,
                cuerpo: $bodyBuilder($cita),
                enviarAt: now(),
                destinatarioNombre: $this->ownerName($cita),
                referenciaTipo: 'cita',
                referenciaId: $cita->id,
                dedupeKey: $tipo.':'.$cita->id,
                prioridad: $tipo === 'cita_2h' ? 3 : 5,
            );

            if ($created instanceof NotificationQueue) {
                $enqueued++;
            }
        }

        return $enqueued;
    }

    private function ownerName(Cita $cita): string
    {
        $prop = $cita->paciente?->propietario;
        if ($prop === null) {
            return 'cliente';
        }

        $full = trim((string) $prop->nombres.' '.(string) ($prop->apellidos ?? ''));

        return $full !== '' ? $full : 'cliente';
    }

    private function petName(Cita $cita): string
    {
        return (string) ($cita->paciente?->nombre ?? 'tu mascota');
    }
}
