<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use App\Models\SubscriptionRenewalReminder;
use App\Models\Tenant;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Support\WhatsApp\WhatsAppChatId;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Avisa por WhatsApp al teléfono del tenant cuando su suscripción está por vencer.
 */
final class SubscriptionRenewalReminderScanner
{
    public function __construct(
        private readonly PlatformWhatsAppMessenger $messenger,
        private readonly SubscriptionPaymentCoverage $coverage,
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
     * @param  list<int>  $reminderDays
     * @return 'sent'|'skipped'|'failed'
     */
    private function processSubscription(Subscription $subscription, CarbonInterface $now, array $reminderDays): string
    {
        $tenant = $subscription->tenant;
        if (! $tenant instanceof Tenant) {
            return 'skipped';
        }

        $anchor = $this->expiryAnchor($subscription);
        if ($anchor === null) {
            return 'skipped';
        }

        if ($this->coverage->hasCoveringPayment($subscription)) {
            return 'skipped';
        }

        $daysUntil = $now->copy()->startOfDay()->diffInDays($anchor->copy()->startOfDay(), false);
        $kind = $this->matchingKind($daysUntil, $reminderDays);
        if ($kind === null) {
            return 'skipped';
        }

        if ($this->alreadySent($subscription, $kind, $anchor)) {
            return 'skipped';
        }

        $chatId = WhatsAppChatId::fromPhone($tenant->telefono);
        if ($chatId === null) {
            return 'skipped';
        }

        try {
            $this->messenger->sendText($chatId, $this->buildMessage($tenant, $subscription, $anchor));
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

    private function expiryAnchor(Subscription $subscription): ?Carbon
    {
        if ($subscription->estado === 'trial') {
            return $subscription->trial_ends_at?->copy();
        }

        if ((float) $subscription->precio_pactado <= 0) {
            return null;
        }

        return ($subscription->proximo_cobro_at ?? $subscription->current_period_end)?->copy();
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
        $renewUrl = rtrim((string) config('billing.renewal_url', 'https://orvae.pe'), '/');

        return implode("\n", [
            "Hola, {$name} 👋",
            '',
            "Tu plan VetSaaS ({$ciclo}) vence el {$fecha}.",
            'Renueva para seguir usando la plataforma sin interrupciones.',
            '',
            "Renovar: {$renewUrl}",
            '',
            'Soporte Orvae',
        ]);
    }
}
