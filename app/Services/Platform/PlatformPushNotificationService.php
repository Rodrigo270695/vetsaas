<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\User;
use App\Services\Push\WebPushSender;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Web Push para el panel central (superadmin / staff plataforma).
 */
final class PlatformPushNotificationService
{
    public function __construct(
        private readonly WebPushSender $sender,
    ) {}

    public function isConfigured(): bool
    {
        return $this->sender->isConfigured();
    }

    /**
     * @param  array{title: string, body: string, url: string, tag: string}  $payload
     */
    public function notifyPlatformStaff(array $payload): void
    {
        $users = $this->platformStaffRecipients();
        if ($users->isEmpty()) {
            Log::warning('Web push omitido: sin staff de plataforma', [
                'tag' => $payload['tag'] ?? 'unknown',
            ]);

            return;
        }

        $this->sender->sendToUsers($users, $payload);
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
}
