<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Support\Subscriptions\SubscriptionRenewalUrl;
use App\Support\WhatsApp\WhatsAppChatId;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Reenganche de clínicas vencidas: mensaje (plantilla + IA) y WhatsApp de plataforma.
 */
final class SubscriptionWinBackService
{
    public function __construct(
        private readonly PlatformWhatsAppMessenger $messenger,
        private readonly SubscriptionRenewalUrl $renewalUrl,
    ) {}

    public function defaultMessage(Tenant $tenant, Subscription $subscription): string
    {
        $name = trim((string) ($tenant->nombre_comercial ?: $tenant->razon_social ?: 'equipo'));
        $renewUrl = $this->renewalUrl->for($tenant, $subscription);

        return implode("\n", [
            "Hola, {$name} 👋",
            '',
            'Te extrañamos en VetSaaS. Queremos que vuelvas a probar la plataforma con tranquilidad.',
            '',
            'Novedades recientes:',
            '• Chat interno del equipo de la clínica',
            '• Plantillas de mensajes listas para WhatsApp',
            '• Programa de referidos',
            '',
            'Como gesto, te regalamos 1 mes gratis para que explores todo sin compromiso.',
            '',
            "Cuando quieras reactivar o renovar: {$renewUrl}",
            '',
            '¿Te parece si te dejamos el mes activo y nos cuentas qué te gustaría ver?',
            '',
            '— Equipo Orvae / VetSaaS',
        ]);
    }

    /**
     * Mejora o completa el borrador con IA, asegurando oferta de 1 mes gratis
     * y mención de chat interno, plantillas y referidos.
     */
    public function generateWithAi(Tenant $tenant, Subscription $subscription, string $currentText): string
    {
        $base = trim($currentText);
        if ($base === '') {
            $base = $this->defaultMessage($tenant, $subscription);
        }

        $apiKey = $this->resolveOpenAiKey();
        if ($apiKey === '') {
            return $this->ensureOfferAndFeatures($base, $tenant, $subscription);
        }

        $name = trim((string) ($tenant->nombre_comercial ?: $tenant->razon_social ?: 'la clínica'));
        $renewUrl = $this->renewalUrl->for($tenant, $subscription);

        $prompt = <<<PROMPT
Eres copywriter de VetSaaS (SaaS para clínicas veterinarias en Perú).
Reescribe o completa el borrador de WhatsApp para REENGANCHAR a una clínica que dejó de renovar.

Clínica: {$name}
Enlace de renovación (inclúyelo si falta): {$renewUrl}

OBLIGATORIO en el mensaje final:
1) Ofrecer claramente 1 mes gratis para seguir probando VetSaaS.
2) Mencionar novedades: chat interno del equipo, plantillas de WhatsApp y programa de referidos.
3) Tono cercano, profesional, sin sonar a spam. Español de Perú. Máximo ~12 líneas.
4) No digas que eres IA. No inventes precios.

Borrador actual del operador (puedes reescribirlo o enriquecerlo):
---
{$base}
---

Devuelve SOLO el texto del mensaje, listo para pegar en WhatsApp.
PROMPT;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(35)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->resolveOpenAiModel(),
                'messages' => [
                    ['role' => 'system', 'content' => 'Escribes mensajes cortos de WhatsApp para reactivar clientes SaaS.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 450,
                'temperature' => 0.7,
            ]);

            if (! $response->successful()) {
                Log::warning('WinBack OpenAI HTTP '.$response->status(), [
                    'body' => $response->body(),
                ]);

                return $this->ensureOfferAndFeatures($base, $tenant, $subscription);
            }

            $generated = trim((string) ($response->json('choices.0.message.content') ?? ''));
            if ($generated === '') {
                return $this->ensureOfferAndFeatures($base, $tenant, $subscription);
            }

            return $this->ensureOfferAndFeatures($generated, $tenant, $subscription);
        } catch (Throwable $e) {
            report($e);

            return $this->ensureOfferAndFeatures($base, $tenant, $subscription);
        }
    }

    /**
     * @return array{ok: bool, error: string|null, granted_days: int|null}
     */
    public function send(
        Subscription $subscription,
        string $message,
        bool $grantFreeMonth = true,
    ): array {
        $subscription->loadMissing(['tenant', 'plan']);

        if ($subscription->estado === 'cancelled' || $subscription->cancelled_at !== null) {
            return ['ok' => false, 'error' => 'La suscripción está cancelada.', 'granted_days' => null];
        }

        if (! $this->messenger->isReady()) {
            return [
                'ok' => false,
                'error' => 'WhatsApp de plataforma no conectado. Conéctalo en Avisos renovación.',
                'granted_days' => null,
            ];
        }

        $tenant = $subscription->tenant;
        if (! $tenant instanceof Tenant) {
            return ['ok' => false, 'error' => 'No se encontró el tenant asociado.', 'granted_days' => null];
        }

        $chatId = WhatsAppChatId::fromPhone($tenant->telefono);
        if ($chatId === null) {
            return [
                'ok' => false,
                'error' => 'El tenant no tiene teléfono válido para WhatsApp.',
                'granted_days' => null,
            ];
        }

        $text = trim($message);
        if ($text === '') {
            return ['ok' => false, 'error' => 'El mensaje no puede estar vacío.', 'granted_days' => null];
        }

        try {
            $this->messenger->sendText($chatId, $text);
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'error' => app()->hasDebugModeEnabled()
                    ? 'No se pudo enviar: '.$e->getMessage()
                    : 'No se pudo enviar el WhatsApp. Revisa la sesión de plataforma.',
                'granted_days' => null,
            ];
        }

        $granted = null;
        if ($grantFreeMonth) {
            $granted = $this->grantFreeMonth($subscription);
        }

        return ['ok' => true, 'error' => null, 'granted_days' => $granted];
    }

    private function grantFreeMonth(Subscription $subscription): int
    {
        $days = 30;
        $base = $subscription->trial_ends_at && $subscription->trial_ends_at->isFuture()
            ? $subscription->trial_ends_at
            : now();

        $subscription->update([
            'estado' => 'trial',
            'trial_ends_at' => $base->copy()->addDays($days),
            'cancelled_at' => null,
        ]);

        return $days;
    }

    private function ensureOfferAndFeatures(string $text, Tenant $tenant, Subscription $subscription): string
    {
        $out = trim($text);
        $lower = mb_strtolower($out);

        $bits = [];
        if (! str_contains($lower, 'mes gratis') && ! str_contains($lower, '1 mes')) {
            $bits[] = 'Te regalamos 1 mes gratis para que sigas probando VetSaaS con calma.';
        }
        if (! str_contains($lower, 'chat interno')) {
            $bits[] = 'Incluye chat interno del equipo, plantillas de WhatsApp y programa de referidos.';
        } elseif (! str_contains($lower, 'plantilla') || ! str_contains($lower, 'referid')) {
            $bits[] = 'También tienes plantillas de mensajes y programa de referidos.';
        }

        $renewUrl = $this->renewalUrl->for($tenant, $subscription);
        if ($renewUrl !== '' && ! str_contains($out, $renewUrl)) {
            $bits[] = "Link de renovación: {$renewUrl}";
        }

        if ($bits === []) {
            return $out;
        }

        return $out."\n\n".implode("\n", $bits);
    }

    private function resolveOpenAiKey(): string
    {
        foreach ([
            (string) config('salesbot.openai_api_key', ''),
            (string) config('bot-ia.openai_api_key', ''),
            (string) config('in-app-assistant.openai_api_key', ''),
        ] as $key) {
            $key = trim($key);
            if ($key !== '') {
                return $key;
            }
        }

        return '';
    }

    private function resolveOpenAiModel(): string
    {
        $model = trim((string) config('salesbot.openai_model', ''));
        if ($model !== '') {
            return $model;
        }

        return (string) config('bot-ia.openai_model', 'gpt-4o-mini');
    }
}
