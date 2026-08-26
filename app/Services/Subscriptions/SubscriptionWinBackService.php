<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\SalesConversation;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Support\Subscriptions\SubscriptionRenewalUrl;
use App\Support\WhatsApp\WhatsAppChatId;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reenganche de clínicas vencidas: oferta por WhatsApp (Conversaciones).
 * El mes gratis se activa solo cuando el cliente responde afirmativamente.
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
            "Cuando quieras renovar: {$renewUrl}",
            '',
            '¿Te activamos el mes gratis? Responde «Sí» o «Acepto» y lo dejamos listo.',
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
2) Pedir que respondan «Sí» o «Acepto» para activar el mes (NO digas que ya está activo).
3) Mencionar novedades: chat interno del equipo, plantillas de WhatsApp y programa de referidos.
4) Tono cercano, profesional, sin sonar a spam. Español de Perú. Máximo ~12 líneas.
5) No digas que eres IA. No inventes precios.

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
     * Envía el WhatsApp. Si $offerFreeMonth, deja la oferta pendiente
     * (el trial se activa al responder sí en Conversaciones).
     *
     * @return array{ok: bool, error: string|null, pending_offer: bool}
     */
    public function send(
        Subscription $subscription,
        string $message,
        bool $offerFreeMonth = true,
    ): array {
        $subscription->loadMissing(['tenant', 'plan']);

        if ($subscription->estado === 'cancelled' || $subscription->cancelled_at !== null) {
            return ['ok' => false, 'error' => 'La suscripción está cancelada.', 'pending_offer' => false];
        }

        if (! $this->messenger->isReady()) {
            return [
                'ok' => false,
                'error' => 'WhatsApp de plataforma no conectado. Conéctalo en Avisos renovación.',
                'pending_offer' => false,
            ];
        }

        $tenant = $subscription->tenant;
        if (! $tenant instanceof Tenant) {
            return ['ok' => false, 'error' => 'No se encontró el tenant asociado.', 'pending_offer' => false];
        }

        $chatId = WhatsAppChatId::fromPhone($tenant->telefono);
        if ($chatId === null) {
            return [
                'ok' => false,
                'error' => 'El tenant no tiene teléfono válido para WhatsApp.',
                'pending_offer' => false,
            ];
        }

        $text = trim($message);
        if ($text === '') {
            return ['ok' => false, 'error' => 'El mensaje no puede estar vacío.', 'pending_offer' => false];
        }

        $phone = $this->normalizePhoneDigits((string) $tenant->telefono);
        if ($phone === '') {
            $phone = str_replace('@c.us', '', $chatId);
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
                'pending_offer' => false,
            ];
        }

        $this->syncOutboundToConversation(
            subscription: $subscription,
            tenant: $tenant,
            phone: $phone,
            waChatId: $chatId,
            message: $text,
        );

        if ($offerFreeMonth) {
            $subscription->update([
                'win_back_pending_at' => now(),
                'win_back_accepted_at' => null,
                'win_back_phone' => $phone,
            ]);
        } else {
            $subscription->update([
                'win_back_pending_at' => null,
                'win_back_phone' => null,
            ]);
        }

        return ['ok' => true, 'error' => null, 'pending_offer' => $offerFreeMonth];
    }

    /**
     * Si hay oferta win-back pendiente para este teléfono, registra el mensaje
     * y activa el mes gratis cuando la respuesta es afirmativa.
     *
     * @return array{handled: bool, status: string|null, granted_days: int|null}
     */
    public function tryHandleInbound(
        string $phone,
        string $waChatId,
        ?string $prospectName,
        string $body,
    ): array {
        $subscription = $this->findPendingByPhone($phone);
        if ($subscription === null) {
            return ['handled' => false, 'status' => null, 'granted_days' => null];
        }

        $subscription->loadMissing('tenant');
        $tenant = $subscription->tenant;
        $name = $tenant instanceof Tenant
            ? trim((string) ($tenant->nombre_comercial ?: $tenant->razon_social ?: 'clínica'))
            : 'clínica';

        $conversation = $this->ensureConversation(
            phone: $this->normalizePhoneDigits($phone) ?: $phone,
            waChatId: $waChatId,
            prospectName: $prospectName ?: $name,
        );

        $conversation->pushMessage('user', $body);

        if (! $this->isAffirmativeAcceptance($body)) {
            $conversation->save();

            return ['handled' => true, 'status' => 'awaiting_yes', 'granted_days' => null];
        }

        $days = $this->acceptPendingOffer($subscription);

        $confirm = "¡Listo! 🎉 Ya activamos 1 mes gratis de VetSaaS para {$name}. "
            .'Entras con tu usuario habitual. Si necesitas ayuda, escríbenos por aquí.';

        $conversation->pushMessage('assistant', $confirm);
        $conversation->save();

        try {
            if ($this->messenger->isReady()) {
                $this->messenger->sendText($waChatId, $confirm);
            }
        } catch (Throwable $e) {
            report($e);
            Log::warning('WinBack: no se pudo enviar confirmación WhatsApp', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('WinBack: mes gratis activado por respuesta afirmativa', [
            'subscription_id' => $subscription->id,
            'phone' => $phone,
            'days' => $days,
        ]);

        return ['handled' => true, 'status' => 'accepted', 'granted_days' => $days];
    }

    public function findPendingByPhone(string $phone): ?Subscription
    {
        $needle = $this->normalizePhoneDigits($phone);
        if ($needle === '') {
            return null;
        }

        $last9 = strlen($needle) >= 9 ? substr($needle, -9) : $needle;

        /** @var list<Subscription> $candidates */
        $candidates = Subscription::query()
            ->with('tenant')
            ->whereNotNull('win_back_pending_at')
            ->whereNull('win_back_accepted_at')
            ->whereNull('cancelled_at')
            ->where('estado', '!=', 'cancelled')
            ->orderByDesc('win_back_pending_at')
            ->limit(80)
            ->get()
            ->all();

        foreach ($candidates as $subscription) {
            $stored = $this->normalizePhoneDigits((string) ($subscription->win_back_phone ?? ''));
            if ($stored !== '' && ($stored === $needle || str_ends_with($stored, $last9) || str_ends_with($needle, substr($stored, -9)))) {
                return $subscription;
            }

            $tenantPhone = $this->normalizePhoneDigits((string) ($subscription->tenant?->telefono ?? ''));
            if ($tenantPhone === '') {
                continue;
            }

            if ($tenantPhone === $needle
                || str_ends_with($tenantPhone, $last9)
                || str_ends_with($needle, substr($tenantPhone, -9))) {
                return $subscription;
            }
        }

        return null;
    }

    public function isAffirmativeAcceptance(string $body): bool
    {
        $t = mb_strtolower(trim($body));
        $t = preg_replace('/[¡!¿?.…,;:]+/u', '', $t) ?? $t;
        $t = trim(preg_replace('/\s+/u', ' ', $t) ?? $t);

        if ($t === '') {
            return false;
        }

        $shorts = [
            'si', 'sí', 'ok', 'okay', 'okei', 'dale', 'va', 'yes', 'yep', 'yeah',
            'claro', 'perfecto', 'listo', 'acepto', 'de acuerdo', 'por supuesto',
            'obvio', 'buenisimo', 'buenísimo', 'excelente', 'vamos', 'hecho',
            'seguro', 'afirmativo', 'correcto',
        ];

        if (in_array($t, $shorts, true)) {
            return true;
        }

        if (preg_match('/^(sí|si|ok|okay|dale|acepto|de acuerdo|va|claro|perfecto|listo|por supuesto|yes|seguro)\b/iu', $t) === 1) {
            return true;
        }

        return preg_match(
            '/\b(acepto|aceptamos|aceptar|quiero el mes|mes gratis|activen|actívenlo|activenlo|adelante con el mes|sí quiero|si quiero)\b/iu',
            $t,
        ) === 1;
    }

    public function acceptPendingOffer(Subscription $subscription): int
    {
        $days = 30;
        $base = $subscription->trial_ends_at && $subscription->trial_ends_at->isFuture()
            ? $subscription->trial_ends_at
            : now();

        $subscription->update([
            'estado' => 'trial',
            'trial_ends_at' => $base->copy()->addDays($days),
            'cancelled_at' => null,
            'win_back_pending_at' => null,
            'win_back_accepted_at' => now(),
        ]);

        return $days;
    }

    private function syncOutboundToConversation(
        Subscription $subscription,
        Tenant $tenant,
        string $phone,
        string $waChatId,
        string $message,
    ): void {
        $name = trim((string) ($tenant->nombre_comercial ?: $tenant->razon_social ?: null));

        $conversation = $this->ensureConversation(
            phone: $phone,
            waChatId: $waChatId,
            prospectName: $name !== '' ? $name : null,
        );

        $conversation->pushMessage('assistant', '[reenganche] '.$message);
        $conversation->activation_trigger = 'win-back:'.$subscription->id;
        $conversation->save();
    }

    private function ensureConversation(
        string $phone,
        string $waChatId,
        ?string $prospectName,
    ): SalesConversation {
        $normalized = $this->normalizePhoneDigits($phone) ?: $phone;

        /** @var SalesConversation|null $conversation */
        $conversation = SalesConversation::query()->where('phone', $normalized)->first();

        if ($conversation === null && $waChatId !== '') {
            $conversation = SalesConversation::query()->where('wa_chat_id', $waChatId)->first();
        }

        if ($conversation === null) {
            /** @var SalesConversation $conversation */
            $conversation = SalesConversation::query()->create([
                'phone' => $normalized,
                'wa_chat_id' => $waChatId,
                'prospect_name' => $prospectName,
                'messages' => [],
                'turn_count' => 0,
                'bot_active' => false,
                'bot_paused_manually' => true,
                'activation_trigger' => 'win-back',
                'product' => 'vetsaas',
                'last_message_at' => now(),
            ]);

            return $conversation;
        }

        if ($prospectName !== null && $prospectName !== '' && blank($conversation->prospect_name)) {
            $conversation->prospect_name = $prospectName;
        }

        if ($waChatId !== '' && (string) $conversation->wa_chat_id !== $waChatId) {
            $conversation->wa_chat_id = $waChatId;
        }

        // Mantener pausado: el sales bot no debe hijackear el reenganche.
        $conversation->bot_active = false;
        $conversation->bot_paused_manually = true;

        return $conversation;
    }

    private function normalizePhoneDigits(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return '';
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            return '51'.$digits;
        }

        return $digits;
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
        if (! str_contains($lower, 'responde') && ! str_contains($lower, 'acepto') && ! str_contains($lower, '«sí»') && ! str_contains($lower, '"sí"')) {
            $bits[] = 'Si te parece, responde «Sí» o «Acepto» y te activamos el mes gratis.';
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
