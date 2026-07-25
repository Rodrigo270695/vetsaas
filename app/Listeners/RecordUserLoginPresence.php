<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Marca último login y presencia. Ya no escribe en user_auth_session_logs.
 */
final class RecordUserLoginPresence
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_seen_at' => now(),
        ])->save();
    }
}
