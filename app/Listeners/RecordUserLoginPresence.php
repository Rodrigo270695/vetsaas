<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Services\Platform\UserAuthSessionLogger;
use Illuminate\Auth\Events\Login;

/**
 * Registra último login, marca presencia y abre fila en el historial de sesiones.
 */
final class RecordUserLoginPresence
{
    public function __construct(
        private readonly UserAuthSessionLogger $authSessionLogger,
    ) {}

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

        $this->authSessionLogger->openFromLogin($user);
    }
}
