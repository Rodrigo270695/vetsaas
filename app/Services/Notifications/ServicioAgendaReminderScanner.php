<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\ClinicSetting;
use App\Models\GroomingTurno;
use App\Models\HotelEstancia;
use App\Models\NotificationQueue;
use App\Support\WhatsApp\WhatsAppChatId;
use Carbon\CarbonInterface;

final class ServicioAgendaReminderScanner
{
    public function __construct(
        private readonly NotificationQueueService $queue,
        private readonly ReminderMessageBuilder $messages,
    ) {}

    /**
     * @return array{grooming_dias: int, grooming_2h: int, hotel_dias: int, hotel_2h: int}
     */
    public function scan(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $setting = ClinicSetting::query()->first();
        $clinicName = $this->messages->clinicDisplayName($setting);
        $days = $setting?->recordatorioAgendaServiciosDiasAntesOpciones() ?? [];
        $twoHours = (bool) ($setting?->recordatorio_agenda_servicios_2h_activo ?? true);

        $groomingDays = 0;
        $grooming2h = 0;
        $hotelDays = 0;
        $hotel2h = 0;

        foreach ($days as $dayCount) {
            $target = $now->copy()->addDays($dayCount);
            $groomingDays += $this->scanGroomingWindow(
                $target,
                $this->tipoGroomingDias($dayCount),
                fn (GroomingTurno $turno) => $this->messages->groomingDiasAntes(
                    $clinicName,
                    $this->groomingOwner($turno),
                    $this->groomingPet($turno),
                    $turno->servicio_label,
                    $turno->inicio_at,
                ),
            );
            $hotelDays += $this->scanHotelWindow(
                $target,
                $this->tipoHotelDias($dayCount),
                fn (HotelEstancia $estancia) => $this->messages->hotelDiasAntes(
                    $clinicName,
                    $this->hotelOwner($estancia),
                    $this->hotelPet($estancia),
                    $estancia->ingreso_at,
                    $estancia->egreso_at,
                ),
            );
        }

        if ($twoHours) {
            $target = $now->copy()->addHours(2);
            $grooming2h = $this->scanGroomingWindow(
                $target,
                'grooming_2h',
                fn (GroomingTurno $turno) => $this->messages->grooming2h(
                    $clinicName,
                    $this->groomingOwner($turno),
                    $this->groomingPet($turno),
                    $turno->servicio_label,
                    $turno->inicio_at,
                ),
            );
            $hotel2h = $this->scanHotelWindow(
                $target,
                'hotel_2h',
                fn (HotelEstancia $estancia) => $this->messages->hotel2h(
                    $clinicName,
                    $this->hotelOwner($estancia),
                    $this->hotelPet($estancia),
                    $estancia->ingreso_at,
                ),
            );
        }

        return [
            'grooming_dias' => $groomingDays,
            'grooming_2h' => $grooming2h,
            'hotel_dias' => $hotelDays,
            'hotel_2h' => $hotel2h,
        ];
    }

    public function enqueueGroomingIfDue(GroomingTurno $turno, ?CarbonInterface $now = null): int
    {
        $now ??= now();
        $turno->loadMissing(['paciente.propietario', 'groomingServicio']);
        if (! in_array($turno->estado, [GroomingTurno::ESTADO_PROGRAMADA, GroomingTurno::ESTADO_CONFIRMADA], true)) {
            return 0;
        }

        $inicio = $turno->inicio_at;
        if ($inicio === null) {
            return 0;
        }

        return $this->enqueueDueAt(
            $inicio,
            $now,
            function (string $tipo) use ($turno): int {
                $setting = ClinicSetting::query()->first();
                $clinicName = $this->messages->clinicDisplayName($setting);
                $cuerpo = str_starts_with($tipo, 'grooming_2h')
                    ? $this->messages->grooming2h(
                        $clinicName,
                        $this->groomingOwner($turno),
                        $this->groomingPet($turno),
                        $turno->servicio_label,
                        $turno->inicio_at,
                    )
                    : $this->messages->groomingDiasAntes(
                        $clinicName,
                        $this->groomingOwner($turno),
                        $this->groomingPet($turno),
                        $turno->servicio_label,
                        $turno->inicio_at,
                    );

                return $this->enqueueOne(
                    $tipo,
                    $turno->paciente?->propietario?->telefono,
                    $this->groomingOwner($turno),
                    'grooming_turno',
                    $turno->id,
                    $cuerpo,
                );
            },
            fn (int $days): string => $this->tipoGroomingDias($days),
            'grooming_2h',
        );
    }

    public function enqueueHotelIfDue(HotelEstancia $estancia, ?CarbonInterface $now = null): int
    {
        $now ??= now();
        $estancia->loadMissing(['paciente.propietario']);
        if (! in_array($estancia->estado, [HotelEstancia::ESTADO_PROGRAMADA, HotelEstancia::ESTADO_CONFIRMADA], true)) {
            return 0;
        }

        $ingreso = $estancia->ingreso_at;
        if ($ingreso === null) {
            return 0;
        }

        return $this->enqueueDueAt(
            $ingreso,
            $now,
            function (string $tipo) use ($estancia): int {
                $setting = ClinicSetting::query()->first();
                $clinicName = $this->messages->clinicDisplayName($setting);
                $cuerpo = str_starts_with($tipo, 'hotel_2h')
                    ? $this->messages->hotel2h(
                        $clinicName,
                        $this->hotelOwner($estancia),
                        $this->hotelPet($estancia),
                        $estancia->ingreso_at,
                    )
                    : $this->messages->hotelDiasAntes(
                        $clinicName,
                        $this->hotelOwner($estancia),
                        $this->hotelPet($estancia),
                        $estancia->ingreso_at,
                        $estancia->egreso_at,
                    );

                return $this->enqueueOne(
                    $tipo,
                    $estancia->paciente?->propietario?->telefono,
                    $this->hotelOwner($estancia),
                    'hotel_estancia',
                    $estancia->id,
                    $cuerpo,
                );
            },
            fn (int $days): string => $this->tipoHotelDias($days),
            'hotel_2h',
        );
    }

    /**
     * @param  callable(string): int  $enqueueTipo
     * @param  callable(int): string  $tipoDias
     */
    private function enqueueDueAt(
        CarbonInterface $when,
        CarbonInterface $now,
        callable $enqueueTipo,
        callable $tipoDias,
        string $tipo2h,
    ): int {
        $setting = ClinicSetting::query()->first();
        $enqueued = 0;

        foreach ($setting?->recordatorioAgendaServiciosDiasAntesOpciones() ?? [] as $days) {
            if (! AppointmentReminderScanner::inWindow($when, $now->copy()->addDays($days))) {
                continue;
            }
            $enqueued += $enqueueTipo($tipoDias($days));
        }

        if (
            ($setting?->recordatorio_agenda_servicios_2h_activo ?? true)
            && AppointmentReminderScanner::inWindow($when, $now->copy()->addHours(2))
        ) {
            $enqueued += $enqueueTipo($tipo2h);
        }

        return $enqueued;
    }

    /**
     * @param  callable(GroomingTurno): string  $bodyBuilder
     */
    private function scanGroomingWindow(CarbonInterface $target, string $tipo, callable $bodyBuilder): int
    {
        $from = $target->copy()->subMinutes(AppointmentReminderScanner::WINDOW_MINUTES);
        $to = $target->copy()->addMinutes(AppointmentReminderScanner::WINDOW_MINUTES);

        $turnos = GroomingTurno::query()
            ->with(['paciente.propietario', 'groomingServicio'])
            ->whereIn('estado', [GroomingTurno::ESTADO_PROGRAMADA, GroomingTurno::ESTADO_CONFIRMADA])
            ->whereBetween('inicio_at', [$from, $to])
            ->get();

        $enqueued = 0;
        foreach ($turnos as $turno) {
            $enqueued += $this->enqueueOne(
                $tipo,
                $turno->paciente?->propietario?->telefono,
                $this->groomingOwner($turno),
                'grooming_turno',
                $turno->id,
                $bodyBuilder($turno),
            );
        }

        return $enqueued;
    }

    /**
     * @param  callable(HotelEstancia): string  $bodyBuilder
     */
    private function scanHotelWindow(CarbonInterface $target, string $tipo, callable $bodyBuilder): int
    {
        $from = $target->copy()->subMinutes(AppointmentReminderScanner::WINDOW_MINUTES);
        $to = $target->copy()->addMinutes(AppointmentReminderScanner::WINDOW_MINUTES);

        $estancias = HotelEstancia::query()
            ->with(['paciente.propietario'])
            ->whereIn('estado', [HotelEstancia::ESTADO_PROGRAMADA, HotelEstancia::ESTADO_CONFIRMADA])
            ->whereBetween('ingreso_at', [$from, $to])
            ->get();

        $enqueued = 0;
        foreach ($estancias as $estancia) {
            $enqueued += $this->enqueueOne(
                $tipo,
                $estancia->paciente?->propietario?->telefono,
                $this->hotelOwner($estancia),
                'hotel_estancia',
                $estancia->id,
                $bodyBuilder($estancia),
            );
        }

        return $enqueued;
    }

    private function enqueueOne(
        string $tipo,
        mixed $phone,
        string $ownerName,
        string $referenciaTipo,
        string $referenciaId,
        string $cuerpo,
    ): int {
        $chatId = WhatsAppChatId::fromPhone(is_string($phone) ? $phone : null);
        if ($chatId === null) {
            return 0;
        }

        if ($this->shouldSkipReminderAfterLifecycleNotice($referenciaTipo, $referenciaId, $tipo)) {
            return 0;
        }

        $created = $this->queue->enqueue(
            tipo: $tipo,
            destinatario: $chatId,
            cuerpo: $cuerpo,
            enviarAt: now(),
            destinatarioNombre: $ownerName,
            referenciaTipo: $referenciaTipo,
            referenciaId: $referenciaId,
            dedupeKey: $tipo.':'.$referenciaId,
            prioridad: str_ends_with($tipo, '_2h') ? 3 : 5,
        );

        return $created instanceof NotificationQueue ? 1 : 0;
    }

    private function shouldSkipReminderAfterLifecycleNotice(string $referenciaTipo, string $referenciaId, string $tipo): bool
    {
        $lifecycle = match ($referenciaTipo) {
            'grooming_turno' => ['grooming_programado', 'grooming_reprogramado'],
            'hotel_estancia' => ['hotel_registrada', 'hotel_reprogramada'],
            default => [],
        };
        if ($lifecycle === [] || in_array($tipo, $lifecycle, true)) {
            return false;
        }

        return NotificationQueue::query()
            ->where('referencia_tipo', $referenciaTipo)
            ->where('referencia_id', $referenciaId)
            ->whereIn('tipo', $lifecycle)
            ->where('created_at', '>=', now()->subHours(6))
            ->exists();
    }

    private function tipoGroomingDias(int $days): string
    {
        return $days === 2 ? 'grooming_48h' : 'grooming_'.$days.'d';
    }

    private function tipoHotelDias(int $days): string
    {
        return $days === 2 ? 'hotel_48h' : 'hotel_'.$days.'d';
    }

    private function groomingOwner(GroomingTurno $turno): string
    {
        return $this->ownerDisplay($turno->paciente?->propietario);
    }

    private function hotelOwner(HotelEstancia $estancia): string
    {
        return $this->ownerDisplay($estancia->paciente?->propietario);
    }

    private function ownerDisplay(mixed $prop): string
    {
        if ($prop === null) {
            return 'cliente';
        }

        if (method_exists($prop, 'displayName')) {
            $named = trim((string) $prop->displayName());
            if ($named !== '') {
                return $named;
            }
        }

        $full = trim((string) ($prop->nombres ?? '').' '.(string) ($prop->apellidos ?? ''));

        return $full !== '' ? $full : 'cliente';
    }

    private function groomingPet(GroomingTurno $turno): string
    {
        return (string) ($turno->paciente?->nombre ?? 'tu mascota');
    }

    private function hotelPet(HotelEstancia $estancia): string
    {
        return (string) ($estancia->paciente?->nombre ?? 'tu mascota');
    }
}
