<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Models\UserAuthSessionLog;
use App\Services\Platform\UserAuthSessionLogger;
use Illuminate\Auth\Events\Logout;

/**
 * Cierra la fila abierta del historial tras logout explícito.
 *
 * SessionGuard regenera/destruye la cookie antes de disparar Logout, por eso
 * no se puede confiar en session()->getId() actual.
 */
final class RecordUserLogoutPresence
{
    public function __construct(
        private readonly UserAuthSessionLogger $authSessionLogger,
    ) {}

    public function handle(Logout $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $this->authSessionLogger->closeDestroyedSessionsForUser(
            $user,
            UserAuthSessionLog::REASON_LOGOUT,
        );
    }
}
