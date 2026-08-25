<?php

use App\Models\ChatParticipant;
use App\Models\PlatformSupportThread;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels (chat interno)
|--------------------------------------------------------------------------
| Nunca lanzar excepciones aquí: se convierten en 500 en /broadcasting/auth.
| Superadmin en modo soporte puede no ser participante del hilo.
| Operadores de Chat soporte (plataforma) pueden escuchar hilos de clínicas.
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

$canPlatformSupportListen = static function (User $user, string $tenantId, ?string $conversationId = null): bool {
    if ($tenantId === '') {
        return false;
    }

    try {
        $allowed = $user->isPlatformSuperadmin()
            || $user->can('plataforma-chat-soporte.view');
    } catch (Throwable) {
        $allowed = false;
    }

    if (! $allowed) {
        return false;
    }

    if ($conversationId === null || $conversationId === '') {
        return true;
    }

    try {
        return PlatformSupportThread::query()
            ->where('tenant_id', $tenantId)
            ->where('conversation_id', $conversationId)
            ->exists();
    } catch (Throwable) {
        return $user->isPlatformSuperadmin();
    }
};

Broadcast::channel('tenant.{tenantId}.chat.{conversationId}', function (User $user, string $tenantId, string $conversationId) use ($matchesTenant, $canPlatformSupportListen) {
    try {
        if ($canPlatformSupportListen($user, $tenantId, $conversationId)) {
            return ['id' => (string) $user->id, 'name' => (string) $user->name];
        }

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

Broadcast::channel('tenant.{tenantId}.chat.presence', function (User $user, string $tenantId) use ($matchesTenant, $canPlatformSupportListen) {
    try {
        if ($canPlatformSupportListen($user, $tenantId)) {
            return ['id' => (string) $user->id, 'name' => (string) $user->name];
        }

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
