<?php

use App\Models\ChatParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels (chat interno)
|--------------------------------------------------------------------------
| Activo cuando BROADCAST_CONNECTION=reverb (o pusher). Con "log"/null
| los eventos se registran pero no hay socket; el frontend usa polling.
*/

Broadcast::channel('tenant.{tenantId}.chat.{conversationId}', function (User $user, string $tenantId, string $conversationId) {
    if ((string) $user->tenant_id !== (string) $tenantId) {
        return false;
    }

    return ChatParticipant::query()
        ->where('conversation_id', $conversationId)
        ->where('user_id', $user->id)
        ->exists();
});
