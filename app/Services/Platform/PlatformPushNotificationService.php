<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Web Push para el panel central (superadmin / staff plataforma).
 */
final class PlatformPushNotificationService
{
    public function isConfigured(): bool
    {
        return filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'));
    }

    /**
     * @param  array{title: string, body: string, url: string, tag: string}  $payload
     */
    public function notifyPlatformStaff(array $payload): void
    {
        $this->sendToUsers($this->platformStaffRecipients(), $payload);
    }

    /**
     * @return Collection<int, User>
     */
    private function platformStaffRecipients(): Collection
    {
        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId(null);

        try {
            return User::query()
                ->whereNull('tenant_id')
                ->get()
                ->filter(function (User $user): bool {
                    if ($user->isPlatformSuperadmin()) {
                        return true;
                    }

                    return $user->can('salesbot-knowledge.view');
                })
                ->values();
        } finally {
            setPermissionsTeamId($previousTeam);
        }
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  array{title: string, body: string, url: string, tag: string}  $payload
     */
    private function sendToUsers(Collection $users, array $payload): void
    {
        $tag = $payload['tag'] ?? 'unknown';

        if (! $this->isConfigured()) {
            Log::warning('Web push omitido: VAPID no configurado', ['tag' => $tag]);

            return;
        }

        if ($users->isEmpty()) {
            Log::warning('Web push omitido: sin staff de plataforma', ['tag' => $tag]);

            return;
        }

        $subscriptions = PushSubscription::query()
            ->whereIn('user_id', $users->pluck('id')->all())
            ->get();

        if ($subscriptions->isEmpty()) {
            Log::info('Web push omitido: nadie del superadmin tiene campana activa', [
                'tag' => $tag,
                'staff_user_ids' => $users->pluck('id')->all(),
            ]);

            return;
        }

        try {
            $webPush = new WebPush(
                [
                    'VAPID' => [
                        'subject' => (string) config('webpush.vapid.subject'),
                        'publicKey' => (string) config('webpush.vapid.public_key'),
                        'privateKey' => (string) config('webpush.vapid.private_key'),
                    ],
                ],
                [
                    'TTL' => 300,
                    'urgency' => 'high',
                ],
            );
        } catch (Throwable $e) {
            Log::error('Web push init failed', [
                'tag' => $tag,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding,
                ]),
                $json,
            );
        }

        $sent = 0;
        $failed = 0;

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;

                continue;
            }

            $failed++;
            $endpoint = $report->getRequest()?->getUri()?->__toString();
            if (is_string($endpoint) && $endpoint !== '') {
                PushSubscription::query()->where('endpoint', $endpoint)->delete();
            }

            Log::warning('Web push falló', [
                'tag' => $tag,
                'reason' => $report->getReason(),
            ]);
        }

        Log::info('Web push enviado', [
            'tag' => $tag,
            'subscriptions' => $subscriptions->count(),
            'sent' => $sent,
            'failed' => $failed,
        ]);
    }
}
