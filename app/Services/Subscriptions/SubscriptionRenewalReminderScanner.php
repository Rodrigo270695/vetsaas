<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use App\Models\SubscriptionRenewalReminder;
use App\Models\Tenant;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Support\Subscriptions\SubscriptionRenewalUrl;
use App\Support\WhatsApp\WhatsAppChatId;
use Carbon\CarbonInterface;

/**
 * Avisa por WhatsApp al teléfono del tenant cuando su suscripción está por vencer.
 */
final class SubscriptionRenewalReminderScanner
{
    public function __construct(
        private readonly PlatformWhatsAppMessenger $messenger,
        private readonly SubscriptionPaymentCoverage $coverage,
        private readonly SubscriptionRenewalUrl $renewalUrl,
    ) {}

    /**
     * @return array{scanned: int, sent: int, skipped: int, failed: int}
     */
    public function run(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $stats = ['scanned' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];

        if (! $this->messenger->isReady()) {
            return $stats;
        }

        $reminderDays = $this->reminderDays();

        Subscription::query()
            ->with(['tenant', 'plan'])
            ->whereIn('estado', ['active', 'trial'])
            ->whereNull('cancelled_at')
            ->where('precio_pactado', '>', 0)
            ->whereHas('plan', fn ($query) => $query->where('codigo', '!=', 'free'))
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use ($now, $reminderDays, &$stats): void {
                foreach ($subscriptions as $subscription) {
                    $stats['scanned']++;
                    $result = $this->processSubscription($subscription, $now, $reminderDays);
                    $stats[$result]++;
                }
            });

        return $stats;
    }

    /**
     * Vista previa del aviso (sin enviar). Incluye motivo si hoy no se enviaría.
     *
     * @return array{
     *     would_send: bool,
     *     skip_code: string|null,
     *     skip_reason: string|null,
     *     message: string|null,
     *     anchor_at: string|null,
     *     anchor_source: string|null,
     *     days_until: int|null,
     *     reminder_kind: string|null,
     *     destinatario: string|null,
     *     already_sent: bool,
     *     whatsapp_ready: bool,
     *     reminder_days: list<int>,
     * }
     */
    public function preview(Subscription $subscription, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $reminderDays = $this->reminderDays();
        $evaluation = $this->evaluate($subscription, $now, $reminderDays);

        return [
            'would_send' => $evaluation['would_send'] && $this->messenger->isReady(),
            'skip_code' => $evaluation['would_send'] && ! $this->messenger->isReady()
                ? 'whatsapp_not_ready'
                : $evaluation['skip_code'],
            'skip_reason' => $evaluation['would_send'] && ! $this->messenger->isReady()
                ? 'WhatsApp de plataforma no conectado. Conéctalo en Avisos renovación.'
                : $evaluation['skip_reason'],
            'message' => $evaluation['message'],
            'anchor_at' => $evaluation['anchor_at'],
            'anchor_source' => $evaluation['anchor_source'],
            'days_until' => $evaluation['days_until'],
            'reminder_kind' => $evaluation['reminder_kind'],
            'destinatario' => $evaluation['destinatario'],
            'already_sent' => $evaluation['already_sent'],
            'whatsapp_ready' => $this->messenger->isReady(),
            'reminder_days' => $reminderDays,
        ];
    }

    /**
     * @param  list<int>  $reminderDays
     * @return 'sent'|'skipped'|'failed'
     */
    private function processSubscription(Subscription $subscription, CarbonInterface $now, array $reminderDays): string
    {
        $evaluation = $this->evaluate($subscription, $now, $reminderDays);

        if (! $evaluation['would_send']) {
            return 'skipped';
        }

        $tenant = $subscription->tenant;
        $anchor = $this->expiryAnchor($subscription);
        $kind = $evaluation['reminder_kind'];
        $chatId = $evaluation['destinatario'];

        if (! $tenant instanceof Tenant || $anchor === null || $kind === null || $chatId === null) {
            return 'skipped';
        }

        try {
            $this->messenger->sendText($chatId, $evaluation['message'] ?? $this->buildMessage($tenant, $subscription, $anchor));
        } catch (\Throwable) {
            return 'failed';
        }

        SubscriptionRenewalReminder::query()->create([
            'subscription_id' => $subscription->id,
            'reminder_kind' => $kind,
            'anchor_at' => $anchor,
            'channel' => SubscriptionRenewalReminder::CHANNEL_WHATSAPP,
            'destinatario' => $chatId,
            'sent_at' => now(),
        ]);

        return 'sent';
    }

    /**
     * @param  list<int>  $reminderDays
     * @return array{
     *     would_send: bool,
     *     skip_code: string|null,
     *     skip_reason: string|null,
     *     message: string|null,
     *     anchor_at: string|null,
     *     anchor_source: string|null,
     *     days_until: int|null,
     *     reminder_kind: string|null,
     *     destinatario: string|null,
     *     already_sent: bool,
     * }
     */
    private function evaluate(Subscription $subscription, CarbonInterface $now, array $reminderDays): array
    {
        $empty = [
            'would_send' => false,
            'skip_code' => null,
            'skip_reason' => null,
            'message' => null,
            'anchor_at' => null,
            'anchor_source' => null,
            'days_until' => null,
            'reminder_kind' => null,
            'destinatario' => null,
            'already_sent' => false,
        ];

        if (! in_array($subscription->estado, ['active', 'trial'], true) || $subscription->cancelled_at !== null) {
            return [
                ...$empty,
                'skip_code' => 'invalid_state',
                'skip_reason' => 'La suscripción no está activa ni en prueba.',
            ];
        }

        if ($this->isFreeSubscription($subscription)) {
            return [
                ...$empty,
                'skip_code' => 'free_plan',
                'skip_reason' => 'Plan gratuito o precio pactado en cero: no se envían avisos de renovación.',
            ];
        }

        $tenant = $subscription->tenant;
        if (! $tenant instanceof Tenant) {
            return [
                ...$empty,
                'skip_code' => 'no_tenant',
                'skip_reason' => 'No se encontró el tenant asociado.',
            ];
        }

        [$anchor, $anchorSource] = $this->expiryAnchorWithSource($subscription);
        if ($anchor === null) {
            return [
                ...$empty,
                'skip_code' => 'no_anchor',
                'skip_reason' => $subscription->estado === 'trial'
                    ? 'Falta la fecha de fin de prueba (trial_ends_at).'
                    : 'Falta próximo cobro o fin del período actual.',
            ];
        }

        if ($this->coverage->hasCoveringPayment($subscription)) {
            return [
                ...$empty,
                'skip_code' => 'already_paid',
                'skip_reason' => 'Ya hay un pago procesado que cubre este vencimiento.',
                'anchor_at' => $anchor->toIso8601String(),
                'anchor_source' => $anchorSource,
            ];
        }

        $daysUntil = (int) $now->copy()->startOfDay()->diffInDays($anchor->copy()->startOfDay(), false);
        $kind = $this->matchingKind($daysUntil, $reminderDays);

        $message = $this->buildMessage($tenant, $subscription, $anchor);
        $base = [
            'message' => $message,
            'anchor_at' => $anchor->toIso8601String(),
            'anchor_source' => $anchorSource,
            'days_until' => $daysUntil,
            'reminder_kind' => $kind,
            'already_sent' => false,
            'destinatario' => WhatsAppChatId::fromPhone($tenant->telefono),
        ];

        if ($daysUntil < 0) {
            return [
                ...$base,
                'would_send' => false,
                'skip_code' => 'expired',
                'skip_reason' => 'El vencimiento ya pasó; no se envían avisos retroactivos.',
            ];
        }

        if ($kind === null) {
            $daysList = implode(' o ', $reminderDays);

            return [
                ...$base,
                'would_send' => false,
                'skip_code' => 'wrong_day',
                'skip_reason' => "Hoy faltan {$daysUntil} día(s). El aviso solo se envía exactamente a {$daysList} día(s) antes del vencimiento.",
            ];
        }

        if ($this->alreadySent($subscription, $kind, $anchor)) {
            return [
                ...$base,
                'would_send' => false,
                'skip_code' => 'already_sent',
                'skip_reason' => 'Ya se envió este aviso para este vencimiento.',
                'already_sent' => true,
            ];
        }

        if ($base['destinatario'] === null) {
            return [
                ...$base,
                'would_send' => false,
                'skip_code' => 'no_phone',
                'skip_reason' => 'El tenant no tiene teléfono válido para WhatsApp.',
            ];
        }

        return [
            ...$base,
            'would_send' => true,
            'skip_code' => null,
            'skip_reason' => null,
        ];
    }

    private function isFreeSubscription(Subscription $subscription): bool
    {
        if ((float) $subscription->precio_pactado <= 0) {
            return true;
        }

        $plan = $subscription->plan;
        if ($plan === null) {
            return false;
        }

        if ($plan->codigo === 'free') {
            return true;
        }

        $price = $subscription->ciclo === 'anual'
            ? (float) ($plan->precio_anual ?? 0)
            : (float) ($plan->precio_mensual ?? 0);

        return $price <= 0;
    }

    private function expiryAnchor(Subscription $subscription): ?CarbonInterface
    {
        return $this->expiryAnchorWithSource($subscription)[0];
    }

    /**
     * @return array{0: CarbonInterface|null, 1: string|null}
     */
    private function expiryAnchorWithSource(Subscription $subscription): array
    {
        if ($subscription->estado === 'trial') {
            $anchor = $subscription->trial_ends_at?->copy();

            return [$anchor, $anchor !== null ? 'trial_ends_at' : null];
        }

        if ($subscription->proximo_cobro_at !== null) {
            return [$subscription->proximo_cobro_at->copy(), 'proximo_cobro_at'];
        }

        if ($subscription->current_period_end !== null) {
            return [$subscription->current_period_end->copy(), 'current_period_end'];
        }

        return [null, null];
    }

    /**
     * @param  list<int>  $reminderDays
     */
    private function matchingKind(int $daysUntil, array $reminderDays): ?string
    {
        if ($daysUntil < 0) {
            return null;
        }

        foreach ($reminderDays as $days) {
            if ($daysUntil === $days) {
                return match ($days) {
                    7 => SubscriptionRenewalReminder::KIND_7D,
                    1 => SubscriptionRenewalReminder::KIND_1D,
                    default => null,
                };
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function reminderDays(): array
    {
        $days = config('billing.renewal_reminder_days', [7, 1]);

        return collect(is_array($days) ? $days : [7, 1])
            ->map(fn (mixed $day): int => max(0, (int) $day))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function alreadySent(Subscription $subscription, string $kind, CarbonInterface $anchor): bool
    {
        return SubscriptionRenewalReminder::query()
            ->where('subscription_id', $subscription->id)
            ->where('reminder_kind', $kind)
            ->where('anchor_at', $anchor)
            ->exists();
    }

    private function buildMessage(Tenant $tenant, Subscription $subscription, CarbonInterface $anchor): string
    {
        $name = trim((string) ($tenant->nombre_comercial ?: $tenant->razon_social));
        $ciclo = $subscription->ciclo === 'anual' ? 'anual' : 'mensual';
        $fecha = $anchor->timezone(config('app.timezone', 'America/Lima'))->format('d/m/Y');
        $renewUrl = $this->renewalUrl->for($tenant, $subscription);

        return implode("\n", [
            "Hola, {$name} 👋",
            '',
            "Tu plan VetSaaS ({$ciclo}) vence el {$fecha}.",
            'Renueva para seguir usando la plataforma sin interrupciones.',
            '',
            "Paga aquí: {$renewUrl}",
            '',
            'Si ya pagaste, tu próximo vencimiento se actualizará automáticamente.',
            '',
            'Soporte Orvae',
        ]);
    }
}
