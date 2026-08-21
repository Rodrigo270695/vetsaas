<?php

use App\Models\ChatParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels (chat interno)
|--------------------------------------------------------------------------
| Nunca lanzar excepciones aquí: se convierten en 500 en /broadcasting/auth.
| Superadmin en modo soporte puede no ser participante del hilo.
*/

$matchesTenant = static function (User $user, string $tenantId): bool {
    if ($tenantId === '') {
        return false;
    }

    if ((string) $user->tenant_id === $tenantId) {
        return true;
    }

    if ($user->isPlatformSuperadmin()) {
        $resolved = function_exists('tenant_id') ? tenant_id() : null;

        return $resolved !== null && (string) $resolved === $tenantId;
    }

    return false;
};

Broadcast::channel('tenant.{tenantId}.chat.{conversationId}', function (User $user, string $tenantId, string $conversationId) use ($matchesTenant) {
    try {
        if (! $matchesTenant($user, $tenantId)) {
            return false;
        }

        if ($user->isPlatformSuperadmin()) {
            return ['id' => (string) $user->id, 'name' => (string) $user->name];
        }

        $ok = ChatParticipant::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->exists();

        return $ok ? ['id' => (string) $user->id, 'name' => (string) $user->name] : false;
    } catch (Throwable $e) {
        report($e);

        return false;
    }
});

Broadcast::channel('tenant.{tenantId}.chat.presence', function (User $user, string $tenantId) use ($matchesTenant) {
    try {
        if (! $matchesTenant($user, $tenantId)) {
            return false;
        }

        if ($user->isPlatformSuperadmin()) {
            return ['id' => (string) $user->id, 'name' => (string) $user->name];
        }

        try {
            if (! $user->can('comunicaciones-chat.view')) {
                return false;
            }
        } catch (Throwable) {
            return false;
        }

        return ['id' => (string) $user->id, 'name' => (string) $user->name];
    } catch (Throwable $e) {
        report($e);

        return false;
    }
});
