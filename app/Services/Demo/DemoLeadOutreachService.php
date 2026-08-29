<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Models\DemoAccessLog;
use App\Notifications\Demo\DemoLeadPaidInviteNotification;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Support\WhatsApp\WhatsAppChatId;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Envía invitación comercial a un lead de la demo (WhatsApp primero, email fallback).
 *
 * @phpstan-type OutreachResult array{
 *     ok: bool,
 *     channel: ?string,
 *     skipped: bool,
 *     message: string
 * }
 */
final class DemoLeadOutreachService
{
    private const COOLDOWN_HOURS = 72;

    public function __construct(
        private readonly PlatformWhatsAppMessenger $messenger,
    ) {}

    /**
     * @return OutreachResult
     */
    public function send(DemoAccessLog $log, bool $force = false): array
    {
        if ($log->lead_captured_at === null || (! $log->phone && ! $log->email)) {
            return [
                'ok' => false,
                'channel' => null,
                'skipped' => true,
                'message' => 'Este acceso no tiene celular ni correo capturado.',
            ];
        }

        if (
            ! $force
            && $log->outreach_sent_at !== null
            && $log->outreach_sent_at->greaterThan(Carbon::now()->subHours(self::COOLDOWN_HOURS))
        ) {
            $when = $log->outreach_sent_at->timezone(config('app.timezone'))->format('d/m H:i');
            $channel = $log->outreach_channel ?? '—';

            return [
                'ok' => false,
                'channel' => $log->outreach_channel,
                'skipped' => true,
                'message' => "Ya se envió hace poco ({$channel} · {$when}). Usa forzar si quieres reenviar.",
            ];
        }

        $registerUrl = (string) config('salesbot.register_url', 'https://orvae.pe/software/VETSAAS');
        $clinic = trim((string) ($log->clinic_name ?? ''));
        $waMessage = $this->whatsAppMessage($clinic !== '' ? $clinic : null, $registerUrl);

        $chatId = WhatsAppChatId::fromPhone($log->phone);
        if ($chatId !== null && $this->messenger->isReady()) {
            try {
                $this->messenger->sendText($chatId, $waMessage);
                $this->markSent($log, 'whatsapp');

                return [
                    'ok' => true,
                    'channel' => 'whatsapp',
                    'skipped' => false,
                    'message' => 'Invitación enviada por WhatsApp.',
                ];
            } catch (Throwable $e) {
                report($e);
                // cae a email si hay
            }
        }

        $email = filled($log->email) ? strtolower(trim((string) $log->email)) : null;
        if ($email !== null) {
            try {
                Notification::route('mail', $email)
                    ->notify(new DemoLeadPaidInviteNotification(
                        clinicName: $clinic !== '' ? $clinic : null,
                        registerUrl: $registerUrl,
                    ));
                $this->markSent($log, 'email');

                return [
                    'ok' => true,
                    'channel' => 'email',
                    'skipped' => false,
                    'message' => 'Invitación enviada por correo.',
                ];
            } catch (Throwable $e) {
                report($e);

                return [
                    'ok' => false,
                    'channel' => null,
                    'skipped' => false,
                    'message' => 'No se pudo enviar el correo: '.$e->getMessage(),
                ];
            }
        }

        if ($chatId === null && $log->phone) {
            return [
                'ok' => false,
                'channel' => null,
                'skipped' => false,
                'message' => 'Celular inválido para WhatsApp y no hay correo.',
            ];
        }

        if (! $this->messenger->isReady()) {
            return [
                'ok' => false,
                'channel' => null,
                'skipped' => false,
                'message' => 'WhatsApp de plataforma no está conectado y no hay correo.',
            ];
        }

        return [
            'ok' => false,
            'channel' => null,
            'skipped' => false,
            'message' => 'No se pudo enviar la invitación.',
        ];
    }

    private function markSent(DemoAccessLog $log, string $channel): void
    {
        $log->fill([
            'outreach_sent_at' => Carbon::now(),
            'outreach_channel' => $channel,
        ]);
        $log->save();
    }

    private function whatsAppMessage(?string $clinicName, string $registerUrl): string
    {
        $greet = $clinicName !== null && $clinicName !== ''
            ? "Hola, {$clinicName} 👋"
            : 'Hola 👋';

        return implode("\n", [
            $greet,
            '',
            'Vimos que entraste a la demo de VetSaaS. Si te gustó el sistema, el siguiente paso es abrir *tu propia clínica* (no la demo compartida) y empezar a trabajar con tu equipo.',
            '',
            '👉 Crear tu clínica / ver planes:',
            $registerUrl,
            '',
            'Si prefieres un tour rápido de 15 min, responde este mensaje y te agendamos.',
            '',
            '— Equipo Orvae / VetSaaS',
        ]);
    }
}
