<?php

declare(strict_types=1);

namespace App\Services\Agenda;

use App\Models\Cita;
use App\Models\GroomingTurno;
use App\Models\HotelEstancia;
use App\Models\Paciente;
use App\Services\ClinicBot\ClinicBotClientResolver;
use App\Support\Agenda\AgendaRsvpIntent;
use Illuminate\Support\Carbon;

/**
 * Confirma o cancela el turno más próximo (cita, grooming u hotel) según SI/NO por WhatsApp.
 *
 * @phpstan-type RsvpResult array{handled: true, reply: string, kind: string, id: string, intent: string}
 */
final class AgendaOwnerRsvpService
{
    public const VIA_PROPIETARIO = 'propietario';

    public function __construct(
        private readonly ClinicBotClientResolver $clients,
    ) {}

    /**
     * @return RsvpResult|null
     */
    public function tryHandle(string $phone, string $body): ?array
    {
        $intent = AgendaRsvpIntent::parse($body);
        if ($intent === null) {
            return null;
        }

        $propietario = $this->clients->findPropietarioByPhone($phone);
        if ($propietario === null) {
            return null;
        }

        $pacienteIds = Paciente::query()
            ->where('propietario_id', $propietario->id)
            ->pluck('id');

        if ($pacienteIds->isEmpty()) {
            return null;
        }

        $pending = $this->pendingSlots($pacienteIds->all());
        if ($pending === []) {
            return null;
        }

        $slot = $pending[0];
        $when = $slot['at']->timezone((string) config('app.timezone'))->format('d/m/Y H:i');
        $mascota = $slot['mascota'];
        $kindLabel = $slot['label'];
        $several = count($pending) > 1;

        if ($intent === AgendaRsvpIntent::YES) {
            $this->confirm($slot);
            $reply = "Listo ✅ Confirmamos {$kindLabel} de *{$mascota}* el *{$when}*.";
        } else {
            $this->cancel($slot);
            $reply = "Entendido. Cancelamos {$kindLabel} de *{$mascota}* el *{$when}*. Si quieres otra fecha, escríbenos.";
        }

        if ($several) {
            $reply .= "\n\nHabía más de un turno pendiente; aplicamos el más próximo.";
        }

        return [
            'handled' => true,
            'reply' => $reply,
            'kind' => $slot['kind'],
            'id' => $slot['id'],
            'intent' => $intent,
        ];
    }

    /**
     * @param  list<string>  $pacienteIds
     * @return list<array{kind: string, id: string, at: Carbon, mascota: string, label: string, model: Cita|GroomingTurno|HotelEstancia}>
     */
    private function pendingSlots(array $pacienteIds): array
    {
        $from = now()->subHours(2);
        $slots = [];

        $citas = Cita::query()
            ->with('paciente:id,nombre')
            ->whereIn('paciente_id', $pacienteIds)
            ->whereIn('estado', [Cita::ESTADO_PROGRAMADA, Cita::ESTADO_CONFIRMADA])
            ->where('inicio_at', '>=', $from)
            ->orderBy('inicio_at')
            ->limit(10)
            ->get();

        foreach ($citas as $cita) {
            $at = $cita->inicio_at instanceof Carbon ? $cita->inicio_at : Carbon::parse((string) $cita->inicio_at);
            $slots[] = [
                'kind' => 'cita',
                'id' => (string) $cita->id,
                'at' => $at,
                'mascota' => (string) ($cita->paciente?->nombre ?? 'tu mascota'),
                'label' => 'la cita',
                'model' => $cita,
            ];
        }

        $turnos = GroomingTurno::query()
            ->with('paciente:id,nombre')
            ->whereIn('paciente_id', $pacienteIds)
            ->whereIn('estado', [GroomingTurno::ESTADO_PROGRAMADA, GroomingTurno::ESTADO_CONFIRMADA])
            ->where('inicio_at', '>=', $from)
            ->orderBy('inicio_at')
            ->limit(10)
            ->get();

        foreach ($turnos as $turno) {
            $at = $turno->inicio_at instanceof Carbon ? $turno->inicio_at : Carbon::parse((string) $turno->inicio_at);
            $slots[] = [
                'kind' => 'grooming',
                'id' => (string) $turno->id,
                'at' => $at,
                'mascota' => (string) ($turno->paciente?->nombre ?? 'tu mascota'),
                'label' => 'el grooming',
                'model' => $turno,
            ];
        }

        $estancias = HotelEstancia::query()
            ->with('paciente:id,nombre')
            ->whereIn('paciente_id', $pacienteIds)
            ->whereIn('estado', [HotelEstancia::ESTADO_PROGRAMADA, HotelEstancia::ESTADO_CONFIRMADA])
            ->where('ingreso_at', '>=', $from)
            ->orderBy('ingreso_at')
            ->limit(10)
            ->get();

        foreach ($estancias as $estancia) {
            $at = $estancia->ingreso_at instanceof Carbon
                ? $estancia->ingreso_at
                : Carbon::parse((string) $estancia->ingreso_at);
            $slots[] = [
                'kind' => 'hotel',
                'id' => (string) $estancia->id,
                'at' => $at,
                'mascota' => (string) ($estancia->paciente?->nombre ?? 'tu mascota'),
                'label' => 'la estancia',
                'model' => $estancia,
            ];
        }

        usort($slots, static fn (array $a, array $b): int => $a['at']->getTimestamp() <=> $b['at']->getTimestamp());

        return $slots;
    }

    /**
     * @param  array{kind: string, model: Cita|GroomingTurno|HotelEstancia}  $slot
     */
    private function confirm(array $slot): void
    {
        $model = $slot['model'];
        $now = now();
        $estado = match ($slot['kind']) {
            'grooming' => GroomingTurno::ESTADO_CONFIRMADA,
            'hotel' => HotelEstancia::ESTADO_CONFIRMADA,
            default => Cita::ESTADO_CONFIRMADA,
        };

        $model->forceFill([
            'estado' => $estado,
            'confirmed_at' => $model->confirmed_at ?? $now,
            'confirmed_via' => self::VIA_PROPIETARIO,
            'owner_responded_at' => $now,
        ])->save();
    }

    /**
     * @param  array{kind: string, model: Cita|GroomingTurno|HotelEstancia}  $slot
     */
    private function cancel(array $slot): void
    {
        $model = $slot['model'];
        $now = now();
        $estado = match ($slot['kind']) {
            'hotel' => HotelEstancia::ESTADO_CANCELADA,
            'grooming' => GroomingTurno::ESTADO_CANCELADA,
            default => Cita::ESTADO_CANCELADA,
        };

        $model->forceFill([
            'estado' => $estado,
            'owner_responded_at' => $now,
        ])->save();
    }
}
