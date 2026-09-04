<?php

declare(strict_types=1);

namespace App\Services\Agenda;

use App\Models\Cita;
use App\Models\ClinicBotConversation;
use App\Models\GroomingTurno;
use App\Models\HotelEstancia;
use App\Models\NotificationQueue;
use App\Models\Paciente;
use App\Models\Propietario;
use App\Services\ClinicBot\ClinicBotClientResolver;
use App\Support\Agenda\AgendaRsvpIntent;
use App\Support\WhatsApp\WhatsAppChatId;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
    public function tryHandle(string $phone, string $body, ?string $waChatId = null): ?array
    {
        $intent = AgendaRsvpIntent::parse($body);
        if ($intent === null) {
            return null;
        }

        $propietario = $this->clients->findPropietarioByPhone($phone);
        if ($propietario === null && $waChatId) {
            $propietario = $this->findPropietarioByChatOrQueue($phone, $waChatId);
        }
        $isLid = str_starts_with($phone, 'lid:')
            || ($waChatId !== null && str_ends_with(strtolower($waChatId), '@lid'));
        if ($propietario === null && $isLid) {
            $propietario = $this->findPropietarioFromRecentNotices();
        }
        if ($propietario === null) {
            Log::warning('Agenda RSVP: no hay propietario para este WhatsApp', [
                'phone' => $phone,
                'wa_chat_id' => $waChatId,
            ]);

            return null;
        }

        $pacienteIds = Paciente::query()
            ->where('propietario_id', $propietario->id)
            ->pluck('id');

        if ($pacienteIds->isEmpty()) {
            return null;
        }

        $onlyUnconfirmed = $intent === AgendaRsvpIntent::YES;
        $pending = $this->pendingSlots($pacienteIds->all(), $onlyUnconfirmed);
        if ($pending === []) {
            Log::warning('Agenda RSVP: no hay turnos pendientes', [
                'propietario_id' => $propietario->id,
                'intent' => $intent,
            ]);

            return null;
        }

        $slot = $this->pickSlot($pending, $phone, $waChatId);
        $when = $slot['at']->timezone((string) config('app.timezone'))->format('d/m/Y H:i');
        $mascota = $slot['mascota'];
        $kindLabel = $slot['label'];
        $several = count($pending) > 1;

        try {
            if ($intent === AgendaRsvpIntent::YES) {
                $this->confirm($slot);
                $reply = "Listo ✅ Confirmamos {$kindLabel} de *{$mascota}* el *{$when}*.";
            } else {
                $this->cancel($slot);
                $reply = "Entendido. Cancelamos {$kindLabel} de *{$mascota}* el *{$when}*. Si quieres otra fecha, escríbenos.";
            }
        } catch (\Throwable $e) {
            Log::warning('Agenda RSVP no pudo guardar el estado', [
                'kind' => $slot['kind'],
                'id' => $slot['id'],
                'error' => $e->getMessage(),
            ]);

            return null;
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

    private function findPropietarioByChatOrQueue(string $phone, string $waChatId): ?Propietario
    {
        $conversation = ClinicBotConversation::query()
            ->where('wa_chat_id', $waChatId)
            ->first();
        if ($conversation !== null) {
            $fromConv = $this->clients->findPropietarioByPhone((string) $conversation->phone);
            if ($fromConv !== null) {
                return $fromConv;
            }
        }

        $destinatarios = array_values(array_unique(array_filter([
            $waChatId,
            WhatsAppChatId::fromPhone($phone),
        ])));
        if ($destinatarios === []) {
            return null;
        }

        $item = NotificationQueue::query()
            ->whereIn('destinatario', $destinatarios)
            ->whereIn('referencia_tipo', ['cita', 'grooming_turno', 'hotel_estancia'])
            ->whereNotNull('referencia_id')
            ->orderByDesc('created_at')
            ->first();

        if ($item === null) {
            return null;
        }

        $cita = Cita::query()->with('paciente.propietario')->find($item->referencia_id);
        $prop = $cita?->paciente?->propietario;
        if ($prop instanceof Propietario) {
            return $prop;
        }

        $turno = GroomingTurno::query()->with('paciente.propietario')->find($item->referencia_id);
        $prop = $turno?->paciente?->propietario;
        if ($prop instanceof Propietario) {
            return $prop;
        }

        $estancia = HotelEstancia::query()->with('paciente.propietario')->find($item->referencia_id);
        $prop = $estancia?->paciente?->propietario;

        return $prop instanceof Propietario ? $prop : null;
    }

    private function findPropietarioFromRecentNotices(): ?Propietario
    {
        $items = NotificationQueue::query()
            ->where('created_at', '>=', now()->subHours(12))
            ->whereIn('referencia_tipo', ['cita', 'grooming_turno', 'hotel_estancia'])
            ->whereNotNull('referencia_id')
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        $found = [];
        foreach ($items as $item) {
            $prop = match ((string) $item->referencia_tipo) {
                'grooming_turno' => GroomingTurno::query()->with('paciente.propietario')->find($item->referencia_id)?->paciente?->propietario,
                'hotel_estancia' => HotelEstancia::query()->with('paciente.propietario')->find($item->referencia_id)?->paciente?->propietario,
                default => Cita::query()->with('paciente.propietario')->find($item->referencia_id)?->paciente?->propietario,
            };
            if ($prop instanceof Propietario) {
                $found[$prop->id] = $prop;
            }
        }

        return count($found) === 1 ? array_values($found)[0] : null;
    }

    /**
     * @param  list<string>  $pacienteIds
     * @return list<array{kind: string, id: string, at: Carbon, mascota: string, label: string, model: Cita|GroomingTurno|HotelEstancia}>
     */
    private function pendingSlots(array $pacienteIds, bool $onlyUnconfirmed = false): array
    {
        $from = now()->subHours(24);
        $slots = [];
        $citaEstados = $onlyUnconfirmed
            ? [Cita::ESTADO_PROGRAMADA]
            : [Cita::ESTADO_PROGRAMADA, Cita::ESTADO_CONFIRMADA];
        $groomingEstados = $onlyUnconfirmed
            ? [GroomingTurno::ESTADO_PROGRAMADA]
            : [GroomingTurno::ESTADO_PROGRAMADA, GroomingTurno::ESTADO_CONFIRMADA];
        $hotelEstados = $onlyUnconfirmed
            ? [HotelEstancia::ESTADO_PROGRAMADA]
            : [HotelEstancia::ESTADO_PROGRAMADA, HotelEstancia::ESTADO_CONFIRMADA];

        $citas = Cita::query()
            ->with('paciente:id,nombre')
            ->whereIn('paciente_id', $pacienteIds)
            ->whereIn('estado', $citaEstados)
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
            ->whereIn('estado', $groomingEstados)
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
            ->whereIn('estado', $hotelEstados)
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
     * Prefiere el turno del último WhatsApp enviado (el de abajo en el chat), no el más temprano.
     *
     * @param  list<array{kind: string, id: string, at: Carbon, mascota: string, label: string, model: Cita|GroomingTurno|HotelEstancia}>  $pending
     * @return array{kind: string, id: string, at: Carbon, mascota: string, label: string, model: Cita|GroomingTurno|HotelEstancia}
     */
    private function pickSlot(array $pending, string $phone, ?string $waChatId): array
    {
        $ids = array_map(static fn (array $slot): string => $slot['id'], $pending);
        $destinatarios = array_values(array_unique(array_filter([
            $waChatId,
            WhatsAppChatId::fromPhone($phone),
        ])));

        $query = NotificationQueue::query()
            ->whereIn('referencia_id', $ids)
            ->whereIn('referencia_tipo', ['cita', 'grooming_turno', 'hotel_estancia'])
            ->orderByDesc('created_at');

        if ($destinatarios !== []) {
            $query->where(function ($inner) use ($destinatarios, $phone): void {
                $inner->whereIn('destinatario', $destinatarios);
                $digits = preg_replace('/\D/', '', $phone) ?? '';
                $last9 = strlen($digits) > 9 ? substr($digits, -9) : $digits;
                if ($last9 !== '' && strlen($last9) >= 9 && ! str_starts_with($phone, 'lid:')) {
                    $inner->orWhere('destinatario', 'like', '%'.$last9.'%');
                }
            });
        }

        $item = $query->first();
        if ($item !== null) {
            foreach ($pending as $slot) {
                if ($slot['id'] === (string) $item->referencia_id) {
                    return $slot;
                }
            }
        }

        $latestAny = NotificationQueue::query()
            ->whereIn('referencia_id', $ids)
            ->orderByDesc('created_at')
            ->first();
        if ($latestAny !== null) {
            foreach ($pending as $slot) {
                if ($slot['id'] === (string) $latestAny->referencia_id) {
                    return $slot;
                }
            }
        }

        return $pending[0];
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

        $payload = ['estado' => $estado];
        $table = $model->getTable();
        if (Schema::hasColumn($table, 'confirmed_at')) {
            $payload['confirmed_at'] = $model->confirmed_at ?? $now;
        }
        if (Schema::hasColumn($table, 'confirmed_via')) {
            $payload['confirmed_via'] = self::VIA_PROPIETARIO;
        }
        if (Schema::hasColumn($table, 'owner_responded_at')) {
            $payload['owner_responded_at'] = $now;
        }

        $model->forceFill($payload)->save();
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

        $payload = ['estado' => $estado];
        if (Schema::hasColumn($model->getTable(), 'owner_responded_at')) {
            $payload['owner_responded_at'] = $now;
        }

        $model->forceFill($payload)->save();
    }
}
