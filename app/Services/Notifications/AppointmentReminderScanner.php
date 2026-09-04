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
    /** Ventana alrededor de (ahora + N días / 2 h). El cron corre cada 5 min. */
    public const WINDOW_MINUTES = 45;

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
                $this->tipoDias($days),
                fn (Cita $cita) => $this->messages->cita48h(
                    $clinicName,
                    $this->ownerName($cita),
                    $this->petName($cita),
                    $cita->inicio_at,
                    $this->motivo($cita),
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
                    $this->motivo($cita),
                ),
            );
        }

        return ['cita_dias' => $countDays, 'cita_2h' => $count2h];
    }

    /**
     * Al crear/reprogramar: si ya está en la ventana, no esperar al cron.
     */
    public function enqueueIfDue(Cita $cita, ?CarbonInterface $now = null): int
    {
        $now ??= now();
        $cita->loadMissing(['paciente.propietario']);
        if (! in_array($cita->estado, [Cita::ESTADO_PROGRAMADA, Cita::ESTADO_CONFIRMADA], true)) {
            return 0;
        }

        $inicio = $cita->inicio_at;
        if ($inicio === null) {
            return 0;
        }

        $setting = ClinicSetting::query()->first();
        $clinicName = $this->messages->clinicDisplayName($setting);
        $enqueued = 0;

        foreach ($setting?->recordatorioCitaDiasAntesOpciones() ?? [] as $days) {
            $target = $now->copy()->addDays($days);
            if (! self::inWindow($inicio, $target)) {
                continue;
            }
            $enqueued += $this->enqueueOne(
                $cita,
                $this->tipoDias($days),
                $this->messages->cita48h(
                    $clinicName,
                    $this->ownerName($cita),
                    $this->petName($cita),
                    $inicio,
                    $this->motivo($cita),
                ),
            );
        }

        if ($setting?->recordatorio_2h_activo && self::inWindow($inicio, $now->copy()->addHours(2))) {
            $enqueued += $this->enqueueOne(
                $cita,
                'cita_2h',
                $this->messages->cita2h(
                    $clinicName,
                    $this->ownerName($cita),
                    $this->petName($cita),
                    $inicio,
                    $this->motivo($cita),
                ),
            );
        }

        return $enqueued;
    }

    public static function inWindow(CarbonInterface $inicio, CarbonInterface $target, int $minutes = self::WINDOW_MINUTES): bool
    {
        return $inicio->betweenIncluded(
            $target->copy()->subMinutes($minutes),
            $target->copy()->addMinutes($minutes),
        );
    }

    private function tipoDias(int $days): string
    {
        return $days === 2 ? 'cita_48h' : 'cita_'.$days.'d';
    }

    /**
     * @param  callable(Cita): string  $bodyBuilder
     */
    private function scanWindow(CarbonInterface $target, string $tipo, callable $bodyBuilder): int
    {
        $from = $target->copy()->subMinutes(self::WINDOW_MINUTES);
        $to = $target->copy()->addMinutes(self::WINDOW_MINUTES);

        $citas = Cita::query()
            ->with(['paciente.propietario'])
            ->whereIn('estado', [Cita::ESTADO_PROGRAMADA, Cita::ESTADO_CONFIRMADA])
            ->whereBetween('inicio_at', [$from, $to])
            ->get();

        $enqueued = 0;
        foreach ($citas as $cita) {
            $enqueued += $this->enqueueOne($cita, $tipo, $bodyBuilder($cita));
        }

        return $enqueued;
    }

    private function enqueueOne(Cita $cita, string $tipo, string $cuerpo): int
    {
        $phone = $cita->paciente?->propietario?->telefono;
        $chatId = WhatsAppChatId::fromPhone($phone);
        if ($chatId === null) {
            return 0;
        }

        $created = $this->queue->enqueue(
            tipo: $tipo,
            destinatario: $chatId,
            cuerpo: $cuerpo,
            enviarAt: now(),
            destinatarioNombre: $this->ownerName($cita),
            referenciaTipo: 'cita',
            referenciaId: $cita->id,
            dedupeKey: $tipo.':'.$cita->id,
            prioridad: $tipo === 'cita_2h' ? 3 : 5,
        );

        return $created instanceof NotificationQueue ? 1 : 0;
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

    private function motivo(Cita $cita): ?string
    {
        $motivo = trim((string) ($cita->motivo ?? ''));

        return $motivo !== '' ? $motivo : null;
    }
}
