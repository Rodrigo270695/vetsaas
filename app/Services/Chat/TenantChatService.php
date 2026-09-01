<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Events\Chat\ChatMessageCreated;
use App\Events\Chat\ChatMessageUpdated;
use App\Events\Chat\ChatPresence;
use App\Events\Chat\ChatRead;
use App\Events\Chat\ChatTyping;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\ChatMessageDelivery;
use App\Models\ChatMessageReaction;
use App\Models\ChatParticipant;
use App\Models\PlatformSupportThread;
use App\Models\User;
use App\Services\Push\WebPushSender;
use App\Support\Tenancy\ClinicAdminScope;
use App\Tenancy\TenantManager;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Pusher\Pusher;
use Throwable;

final class TenantChatService
{
    public const MESSAGES_PAGE = 80;

    public const MAX_ATTACHMENTS = 5;

    public const SUPPORT_GROUP_NAME = 'Soporte VetSaaS';

    public const SUPPORT_EMAIL_DOMAIN = 'vetsaas.internal';

    /** @var list<string> */
    public const ALLOWED_REACTION_EMOJIS = ['👍', '✅', '❤️', '😂', '🎉'];

    public const PRESENCE_TTL_SECONDS = 90;

    public const PRESENCE_ONLINE_SECONDS = 60;

    public const VIEWING_TTL_SECONDS = 45;

    /** @var array<string, bool> */
    private static array $schemaFlagCache = [];

    private function schemaHasTable(string $table): bool
    {
        $key = 't:'.$table;

        return self::$schemaFlagCache[$key] ??= Schema::hasTable($table);
    }

    private function schemaHasColumn(string $table, string $column): bool
    {
        $key = 'c:'.$table.'.'.$column;

        return self::$schemaFlagCache[$key] ??= Schema::hasColumn($table, $column);
    }

    /**
     * @return Collection<int, User>
     */
    public function directoryUsers(?string $exceptUserId = null): Collection
    {
        $q = ClinicAdminScope::usersQuery()
            ->where('is_active', true)
            ->where('email', 'not like', '%@vetsaas.internal')
            ->orderBy('name');

        if ($exceptUserId !== null && $exceptUserId !== '') {
            $q->where('id', '!=', $exceptUserId);
        }

        return $q->get(['id', 'name', 'email']);
    }

    public function assertTenantUser(string $userId): User
    {
        $user = ClinicAdminScope::usersQuery()
            ->whereKey($userId)
            ->where('is_active', true)
            ->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'user_id' => __('El usuario no pertenece a esta clínica o no está activo.'),
            ]);
        }

        return $user;
    }

    /**
     * Superadmin (u operador) con sesión `tenant_impersonation`: puede leer
     * todos los hilos de la clínica sin unirse como participante.
     */
    public function canObserveClinicChats(): bool
    {
        try {
            $request = request();
            if (! $request->hasSession()) {
                return false;
            }
            $imp = $request->session()->get('tenant_impersonation');

            return is_array($imp) && filled($imp['tenant_id'] ?? null);
        } catch (Throwable) {
            return false;
        }
    }

    public function participantOf(ChatConversation $conversation, User $user): ?ChatParticipant
    {
        return ChatParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->first();
    }

    public function assertCanAccessConversation(ChatConversation $conversation, User $user): void
    {
        if ($this->participantOf($conversation, $user) !== null) {
            return;
        }

        if ($this->canObserveClinicChats()) {
            return;
        }

        abort(403, 'No eres participante de esta conversación.');
    }

    public function assertParticipant(ChatConversation $conversation, User $user): ChatParticipant
    {
        $participant = $this->participantOf($conversation, $user);

        if ($participant === null) {
            abort(403, 'No eres participante de esta conversación.');
        }

        return $participant;
    }

    public static function directKey(string $userA, string $userB): string
    {
        $pair = [$userA, $userB];
        sort($pair);

        return $pair[0].':'.$pair[1];
    }

    public function findOrCreateDirect(User $actor, string $otherUserId): ChatConversation
    {
        if ($otherUserId === (string) $actor->id) {
            throw ValidationException::withMessages([
                'user_id' => __('No puedes chatear contigo mismo.'),
            ]);
        }

        $this->assertTenantUser($otherUserId);

        $key = self::directKey((string) $actor->id, $otherUserId);

        $existing = ChatConversation::query()
            ->where('type', ChatConversation::TYPE_DIRECT)
            ->where('direct_key', $key)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($actor, $otherUserId, $key): ChatConversation {
            $conversation = ChatConversation::query()->create([
                'type' => ChatConversation::TYPE_DIRECT,
                'name' => null,
                'direct_key' => $key,
                'created_by_id' => $actor->id,
            ]);

            $now = now();
            foreach ([(string) $actor->id, $otherUserId] as $uid) {
                ChatParticipant::query()->create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $uid,
                    'joined_at' => $now,
                    'last_read_at' => $uid === (string) $actor->id ? $now : null,
                ]);
            }

            return $conversation;
        });
    }

    /**
     * @param  list<string>  $memberIds
     */
    public function createGroup(User $actor, string $name, array $memberIds): ChatConversation
    {
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => __('El nombre del grupo es obligatorio.'),
            ]);
        }

        $ids = collect($memberIds)
            ->map(static fn ($id): string => (string) $id)
            ->filter(static fn (string $id): bool => $id !== '')
            ->unique()
            ->reject(static fn (string $id): bool => $id === (string) $actor->id)
            ->values();

        foreach ($ids as $id) {
            $this->assertTenantUser($id);
        }

        $all = $ids->push((string) $actor->id)->unique()->values();

        return DB::transaction(function () use ($actor, $name, $all): ChatConversation {
            $conversation = ChatConversation::query()->create([
                'type' => ChatConversation::TYPE_GROUP,
                'name' => $name,
                'direct_key' => null,
                'created_by_id' => $actor->id,
            ]);

            $now = now();
            foreach ($all as $uid) {
                ChatParticipant::query()->create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $uid,
                    'joined_at' => $now,
                    'last_read_at' => $uid === (string) $actor->id ? $now : null,
                ]);
            }

            return $conversation;
        });
    }

    /**
     * Agrega integrantes a un grupo de equipo (Caja u otros). No aplica a Soporte VetSaaS.
     *
     * @param  list<string>  $memberIds
     */
    public function addMembers(ChatConversation $conversation, User $actor, array $memberIds): ChatConversation
    {
        $this->assertParticipant($conversation, $actor);

        if (! $conversation->isGroup()) {
            throw ValidationException::withMessages([
                'user_ids' => __('Solo se pueden agregar personas a un grupo.'),
            ]);
        }

        if ($conversation->isSupport()) {
            throw ValidationException::withMessages([
                'user_ids' => __('Los integrantes de Soporte se sincronizan solos.'),
            ]);
        }

        $existing = ChatParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->pluck('user_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        $ids = collect($memberIds)
            ->map(static fn ($id): string => (string) $id)
            ->filter(static fn (string $id): bool => $id !== '')
            ->unique()
            ->reject(static fn (string $id): bool => in_array($id, $existing, true))
            ->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'user_ids' => __('Elegí al menos una persona que aún no esté en el grupo.'),
            ]);
        }

        foreach ($ids as $id) {
            $this->assertTenantUser($id);
        }

        $now = now();
        foreach ($ids as $uid) {
            ChatParticipant::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $uid,
                'joined_at' => $now,
                'last_read_at' => null,
            ]);
        }

        $conversation->touch();

        return $conversation->fresh(['participants.user:id,name,email']) ?? $conversation;
    }

    /**
     * @param  UploadedFile|list<UploadedFile>|null  $attachment
     * @param  list<string>  $mentionedUserIds
     */
    public function sendMessage(
        ChatConversation $conversation,
        User $actor,
        ?string $body,
        UploadedFile|array|null $attachment = null,
        ?string $tenantSlug = null,
        ?string $replyToId = null,
        array $mentionedUserIds = [],
    ): ChatMessage {
        $this->assertParticipant($conversation, $actor);

        $body = trim((string) $body);
        $files = $this->normalizeAttachments($attachment);
        $hasAttachment = $files !== [];

        if ($body === '' && ! $hasAttachment) {
            throw ValidationException::withMessages([
                'body' => __('Escribe un mensaje o adjunta un archivo.'),
            ]);
        }

        if (mb_strlen($body) > 4000) {
            throw ValidationException::withMessages([
                'body' => __('El mensaje es demasiado largo.'),
            ]);
        }

        if (count($files) > self::MAX_ATTACHMENTS) {
            throw ValidationException::withMessages([
                'attachment' => __('Máximo :max archivos por mensaje.', ['max' => self::MAX_ATTACHMENTS]),
            ]);
        }

        $stored = $this->storeAttachmentFiles($files, $conversation, $tenantSlug);
        $first = $stored[0] ?? null;

        $payload = [
            'conversation_id' => $conversation->id,
            'user_id' => $actor->id,
            'body' => $body !== '' ? $body : null,
            'created_at' => now(),
        ];

        if (Schema::hasColumn('chat_messages', 'attachment_path')) {
            $payload['attachment_path'] = $first['path'] ?? null;
            $payload['attachment_name'] = $first['name'] ?? null;
            $payload['attachment_mime'] = $first['mime'] ?? null;
            $payload['attachment_size'] = $first['size'] ?? null;
        }

        if (Schema::hasColumn('chat_messages', 'reply_to_id') && $replyToId !== null && $replyToId !== '') {
            $reply = ChatMessage::query()
                ->whereKey($replyToId)
                ->where('conversation_id', $conversation->id)
                ->first();
            if ($reply !== null) {
                $payload['reply_to_id'] = $reply->id;
            }
        }

        if (Schema::hasColumn('chat_messages', 'mentioned_user_ids')) {
            $mentions = collect($mentionedUserIds)
                ->map(static fn ($id): string => (string) $id)
                ->filter(static fn (string $id): bool => $id !== '')
                ->unique()
                ->values()
                ->all();
            $payload['mentioned_user_ids'] = $mentions !== [] ? $mentions : null;
        }

        $message = ChatMessage::query()->create($payload);

        if ($stored !== [] && Schema::hasTable('chat_message_attachments')) {
            foreach ($stored as $file) {
                ChatMessageAttachment::query()->create([
                    'message_id' => $message->id,
                    'path' => $file['path'],
                    'name' => $file['name'],
                    'mime' => $file['mime'],
                    'size' => $file['size'],
                    'created_at' => now(),
                ]);
            }
        }

        ChatParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $actor->id)
            ->update(['last_read_at' => now()]);

        $conversation->touch();

        $this->loadMessageRelations($message);

        $this->dispatchMessageCreated($conversation, $actor, $message);
        $this->notifyParticipantsWebPush($conversation, $actor, $message);
        $this->syncPlatformSupportThreadIndex($conversation, $actor, $message);

        return $message;
    }

    /**
     * Refleja actividad del grupo Soporte en el índice público (panel plataforma).
     */
    private function syncPlatformSupportThreadIndex(
        ChatConversation $conversation,
        User $actor,
        ChatMessage $message,
    ): void {
        if (! $conversation->isSupport()) {
            return;
        }

        try {
            $tenantId = app(TenantManager::class)->id()
                ?? (is_string($actor->tenant_id) ? $actor->tenant_id : null);

            if ($tenantId === null || $tenantId === '') {
                return;
            }

            $email = strtolower((string) $actor->email);
            $fromClinic = ! str_ends_with($email, '@'.self::SUPPORT_EMAIL_DOMAIN);

            $preview = $this->previewFromRow(
                $message->body,
                $message->attachment_name,
                $message->attachment_mime,
            );

            $payload = [
                'conversation_id' => (string) $conversation->id,
                'last_message_at' => $message->created_at ?? now(),
                'last_preview' => mb_substr($preview, 0, 280),
            ];

            if (Schema::hasColumn('platform_support_threads', 'from_clinic')) {
                $payload['from_clinic'] = $fromClinic;
            }

            if ($fromClinic && Schema::hasColumn('platform_support_threads', 'clinic_waiting_since')) {
                // No reiniciar el reloj SLA si ya estaba esperando.
                $existing = PlatformSupportThread::query()->where('tenant_id', $tenantId)->first();
                if ($existing === null || $existing->clinic_waiting_since === null) {
                    $payload['clinic_waiting_since'] = now();
                }
            }

            if (! $fromClinic && Schema::hasColumn('platform_support_threads', 'clinic_waiting_since')) {
                $payload['clinic_waiting_since'] = null;
            }

            PlatformSupportThread::query()
                ->where('tenant_id', $tenantId)
                ->update($payload);
        } catch (Throwable) {
            // Índice plataforma opcional: no bloquear el chat del tenant.
        }
    }

    public function setMuted(ChatConversation $conversation, User $user, bool $muted): void
    {
        $this->assertParticipant($conversation, $user);

        if (! Schema::hasColumn('chat_participants', 'muted_at')) {
            return;
        }

        ChatParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update(['muted_at' => $muted ? now() : null]);
    }

    public function setPinned(ChatConversation $conversation, User $user, bool $pinned): void
    {
        $this->assertParticipant($conversation, $user);

        if (! Schema::hasColumn('chat_participants', 'pinned_at')) {
            return;
        }

        ChatParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update(['pinned_at' => $pinned ? now() : null]);
    }

    public function touchPresence(User $user, ?string $conversationId = null): void
    {
        $at = now()->toIso8601String();

        Cache::put(
            $this->presenceCacheKey((string) $user->id),
            $at,
            self::PRESENCE_TTL_SECONDS,
        );

        $this->dispatchPresence($user, $at, $conversationId);
    }

    /**
     * @param  list<string>  $userIds
     * @return array<string, array{online: bool, last_seen_at: ?string}>
     */
    public function presenceForUsers(array $userIds): array
    {
        $map = [];
        $now = now();

        foreach ($userIds as $userId) {
            $uid = (string) $userId;
            if ($uid === '') {
                continue;
            }

            $raw = Cache::get($this->presenceCacheKey($uid));
            $lastSeen = is_string($raw) && $raw !== '' ? $raw : null;
            $online = false;

            if ($lastSeen !== null) {
                try {
                    $online = Carbon::parse($lastSeen)->diffInSeconds($now) < self::PRESENCE_ONLINE_SECONDS;
                } catch (Throwable) {
                    $online = false;
                    $lastSeen = null;
                }
            }

            $map[$uid] = [
                'online' => $online,
                'last_seen_at' => $lastSeen,
            ];
        }

        return $map;
    }

    public function touchTyping(ChatConversation $conversation, User $user): void
    {
        $this->assertParticipant($conversation, $user);
        $this->touchPresence($user, (string) $conversation->id);

        $at = now()->toIso8601String();

        Cache::put(
            $this->typingCacheKey((string) $conversation->id, (string) $user->id),
            [
                'name' => (string) $user->name,
                'at' => $at,
            ],
            5,
        );

        $this->dispatchTyping($conversation, $user, $at);
    }

    public static function isAllowedReactionEmoji(string $emoji): bool
    {
        return in_array($emoji, self::ALLOWED_REACTION_EMOJIS, true);
    }

    /**
     * Usuarios del directorio aptos para el grupo "Caja" (ventas/caja/roles).
     *
     * @return list<string>
     */
    public function cashTeamUserIds(?string $exceptUserId = null): array
    {
        $tenantId = tenant_id();
        $previousTeam = getPermissionsTeamId();

        if ($tenantId !== null && $tenantId !== '') {
            setPermissionsTeamId($tenantId);
        }

        try {
            return self::filterCashTeamMembers($this->directoryUsers($exceptUserId));
        } finally {
            setPermissionsTeamId($previousTeam);
        }
    }

    public static function userQualifiesForCashTeam(User $user): bool
    {
        return $user->can('ventas.create')
            || $user->can('caja-sesiones.view')
            || $user->hasRole('recepcionista')
            || $user->hasRole('admin_clinica');
    }

    /**
     * @param  Collection<int, User>  $users
     * @return list<string>
     */
    public static function filterCashTeamMembers(Collection $users): array
    {
        return $users
            ->filter(static fn (User $user): bool => self::userQualifiesForCashTeam($user))
            ->map(static fn (User $user): string => (string) $user->getKey())
            ->values()
            ->all();
    }

    public function editMessage(ChatMessage $message, User $actor, string $body): ChatMessage
    {
        $conversation = $message->conversation ?? ChatConversation::query()->findOrFail($message->conversation_id);
        $this->assertParticipant($conversation, $actor);

        if ((string) $message->user_id !== (string) $actor->id) {
            abort(403, 'Solo puedes editar tus propios mensajes.');
        }

        if ($message->isDeleted() || (Schema::hasColumn('chat_messages', 'deleted_at') && $message->deleted_at !== null)) {
            throw ValidationException::withMessages([
                'body' => __('No puedes editar un mensaje eliminado.'),
            ]);
        }

        $body = trim($body);
        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => __('Escribe un mensaje.'),
            ]);
        }

        if (mb_strlen($body) > 4000) {
            throw ValidationException::withMessages([
                'body' => __('El mensaje es demasiado largo.'),
            ]);
        }

        $message->body = $body;
        if (Schema::hasColumn('chat_messages', 'edited_at')) {
            $message->edited_at = now();
        }
        $message->save();

        $this->loadMessageRelations($message);
        $this->dispatchMessageUpdated($conversation, $actor, $message, 'edited');

        return $message;
    }

    public function softDeleteMessage(ChatMessage $message, User $actor): ChatMessage
    {
        $conversation = $message->conversation ?? ChatConversation::query()->findOrFail($message->conversation_id);
        $this->assertParticipant($conversation, $actor);

        if ((string) $message->user_id !== (string) $actor->id) {
            abort(403, 'Solo puedes eliminar tus propios mensajes.');
        }

        if (! Schema::hasColumn('chat_messages', 'deleted_at')) {
            throw ValidationException::withMessages([
                'message' => __('La eliminación de mensajes no está disponible.'),
            ]);
        }

        if ($message->deleted_at !== null) {
            return $message;
        }

        $message->deleted_at = now();
        // Preferir string vacío si body sigue NOT NULL (migración change puede fallar).
        $message->body = '';
        $message->save();

        $this->loadMessageRelations($message);
        $this->dispatchMessageUpdated($conversation, $actor, $message, 'deleted');

        return $message;
    }

    /**
     * @return array{message: array<string, mixed>, reactions: list<array<string, mixed>>, removed: bool}
     */
    public function toggleReaction(ChatMessage $message, User $actor, string $emoji): array
    {
        $conversation = $message->conversation ?? ChatConversation::query()->findOrFail($message->conversation_id);
        $this->assertParticipant($conversation, $actor);

        if ($message->isDeleted()) {
            throw ValidationException::withMessages([
                'emoji' => __('No puedes reaccionar a un mensaje eliminado.'),
            ]);
        }

        if (! Schema::hasTable('chat_message_reactions')) {
            throw ValidationException::withMessages([
                'emoji' => __('Las reacciones no están disponibles.'),
            ]);
        }

        $emoji = trim($emoji);
        if (! self::isAllowedReactionEmoji($emoji)) {
            throw ValidationException::withMessages([
                'emoji' => __('Emoji de reacción no permitido.'),
            ]);
        }

        $existing = ChatMessageReaction::query()
            ->where('message_id', $message->id)
            ->where('user_id', $actor->id)
            ->first();

        $removed = false;
        if ($existing !== null && $existing->emoji === $emoji) {
            $existing->delete();
            $removed = true;
        } elseif ($existing !== null) {
            $existing->emoji = $emoji;
            $existing->created_at = now();
            $existing->save();
        } else {
            ChatMessageReaction::query()->create([
                'message_id' => $message->id,
                'user_id' => $actor->id,
                'emoji' => $emoji,
                'created_at' => now(),
            ]);
        }

        $this->loadMessageRelations($message);
        $serialized = $this->serializeMessage($message, $actor, $conversation);
        $this->dispatchMessageUpdated($conversation, $actor, $message, 'reaction');

        return [
            'message' => $serialized,
            'reactions' => $serialized['reactions'] ?? [],
            'removed' => $removed,
        ];
    }

    public function forwardMessage(ChatMessage $source, User $actor, ChatConversation $target): ChatMessage
    {
        $sourceConversation = $source->conversation
            ?? ChatConversation::query()->findOrFail($source->conversation_id);

        $this->assertParticipant($sourceConversation, $actor);
        $this->assertParticipant($target, $actor);

        if ($source->isDeleted()) {
            throw ValidationException::withMessages([
                'message' => __('No puedes reenviar un mensaje eliminado.'),
            ]);
        }

        $source->loadMissing('attachments');

        $originalBody = trim((string) ($source->body ?? ''));
        $attachmentNames = $source->attachments
            ->map(static fn (ChatMessageAttachment $a): string => (string) $a->name)
            ->filter()
            ->values()
            ->all();

        if ($attachmentNames === [] && filled($source->attachment_name)) {
            $attachmentNames[] = (string) $source->attachment_name;
        }

        $parts = [];
        if ($originalBody !== '') {
            $parts[] = $originalBody;
        }
        if ($attachmentNames !== []) {
            $parts[] = '📎 '.implode(', ', $attachmentNames);
        }

        $forwardBody = '[Reenviado] '.($parts !== [] ? implode("\n", $parts) : '(sin contenido)');

        return $this->sendMessage($target, $actor, $forwardBody);
    }

    /**
     * Galería de adjuntos de imagen de la conversación.
     *
     * @return list<array{message_id: string, url: ?string, name: string, mime: string, size: int, created_at: ?string}>
     */
    public function mediaGallery(ChatConversation $conversation, User $actor): array
    {
        $this->assertCanAccessConversation($conversation, $actor);

        $items = [];

        if (Schema::hasTable('chat_message_attachments')) {
            $rows = ChatMessageAttachment::query()
                ->whereHas('message', function ($q) use ($conversation): void {
                    $q->where('conversation_id', $conversation->id);
                    if (Schema::hasColumn('chat_messages', 'deleted_at')) {
                        $q->whereNull('deleted_at');
                    }
                })
                ->where('mime', 'ilike', 'image/%')
                ->with('message:id,conversation_id,created_at,deleted_at')
                ->orderByDesc('created_at')
                ->limit(200)
                ->get();

            foreach ($rows as $att) {
                /** @var ChatMessageAttachment $att */
                $items[] = [
                    'message_id' => (string) $att->message_id,
                    'url' => $att->url,
                    'name' => (string) ($att->name ?: 'imagen'),
                    'mime' => (string) ($att->mime ?? ''),
                    'size' => (int) ($att->size ?? 0),
                    'created_at' => ($att->created_at ?? $att->message?->created_at)?->toIso8601String(),
                ];
            }
        }

        if ($items === [] && Schema::hasColumn('chat_messages', 'attachment_path')) {
            $legacy = ChatMessage::query()
                ->where('conversation_id', $conversation->id)
                ->whereNotNull('attachment_path')
                ->where('attachment_mime', 'ilike', 'image/%')
                ->when(
                    Schema::hasColumn('chat_messages', 'deleted_at'),
                    static fn ($q) => $q->whereNull('deleted_at'),
                )
                ->orderByDesc('created_at')
                ->limit(200)
                ->get();

            foreach ($legacy as $message) {
                $items[] = [
                    'message_id' => (string) $message->id,
                    'url' => $message->attachment_url,
                    'name' => (string) ($message->attachment_name ?: 'imagen'),
                    'mime' => (string) ($message->attachment_mime ?? ''),
                    'size' => (int) ($message->attachment_size ?? 0),
                    'created_at' => $message->created_at?->toIso8601String(),
                ];
            }
        }

        return $items;
    }

    /**
     * @return list<array{user_id: string, name: string, at: ?string}>
     */
    public function typingPayload(ChatConversation $conversation, User $actor): array
    {
        $this->assertCanAccessConversation($conversation, $actor);

        $participantIds = ChatParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $actor->id)
            ->pluck('user_id');

        $typing = [];
        foreach ($participantIds as $uid) {
            $uid = (string) $uid;
            $cached = Cache::get($this->typingCacheKey((string) $conversation->id, $uid));
            if (! is_array($cached)) {
                continue;
            }

            $typing[] = [
                'user_id' => $uid,
                'name' => (string) ($cached['name'] ?? 'Usuario'),
                'at' => isset($cached['at']) ? (string) $cached['at'] : null,
            ];
        }

        return $typing;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchInConversation(ChatConversation $conversation, User $actor, string $q): array
    {
        $this->assertCanAccessConversation($conversation, $actor);

        $q = trim($q);
        if ($q === '') {
            return [];
        }

        $like = '%'.addcslashes($q, '%_\\').'%';

        $with = ['user:id,name', 'replyTo.user:id,name'];
        if (Schema::hasTable('chat_message_attachments')) {
            $with[] = 'attachments';
        }

        $messages = ChatMessage::query()
            ->where('conversation_id', $conversation->id)
            ->whereNotNull('body')
            ->where('body', 'ilike', $like)
            ->when(
                Schema::hasColumn('chat_messages', 'deleted_at'),
                static fn ($q) => $q->whereNull('deleted_at'),
            )
            ->with($with)
            ->orderByDesc('created_at')
            ->limit(40)
            ->get();

        $this->loadReactionsSafely($messages);

        return $messages
            ->sortBy('created_at')
            ->values()
            ->map(fn (ChatMessage $m): array => $this->serializeMessage($m, $actor, $conversation))
            ->all();
    }

    /**
     * @return list<array{user_id: string, name: string, read_at: ?string}>
     */
    public function readersForMessage(ChatMessage $message, ChatConversation $conversation): array
    {
        if (! $conversation->relationLoaded('participants')) {
            $conversation->load(['participants.user:id,name']);
        }

        return $this->readersFromParticipants($message, $conversation->participants);
    }

    /**
     * @param  Collection<int, ChatParticipant>  $participants
     * @return list<array{user_id: string, name: string, read_at: ?string}>
     */
    private function readersFromParticipants(ChatMessage $message, Collection $participants): array
    {
        $createdAt = $message->created_at;
        if ($createdAt === null) {
            return [];
        }

        return $participants
            ->filter(static function (ChatParticipant $p) use ($message, $createdAt): bool {
                if ((string) $p->user_id === (string) $message->user_id) {
                    return false;
                }
                if ($p->last_read_at === null) {
                    return false;
                }

                return $p->last_read_at->greaterThanOrEqualTo($createdAt);
            })
            ->map(static fn (ChatParticipant $p): array => [
                'user_id' => (string) $p->user_id,
                'name' => (string) ($p->user?->name ?? 'Usuario'),
                'read_at' => $p->last_read_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>|null  $deliveredUserIds  User IDs that already have a delivery row for this message (optional batch).
     * @param  Collection<int, ChatParticipant>|null  $participants
     * @param  Collection<string, User>|null  $mentionUsersById
     * @return array<string, mixed>
     */
    public function serializeMessage(
        ChatMessage $message,
        User $actor,
        ChatConversation $conversation,
        ?array $deliveredUserIds = null,
        ?Collection $participants = null,
        ?Collection $mentionUsersById = null,
    ): array {
        if (! $message->relationLoaded('user')) {
            $message->load('user:id,name');
        }
        if (! $message->relationLoaded('replyTo')) {
            $message->load('replyTo.user:id,name');
        }
        if (! $message->relationLoaded('attachments') && $this->schemaHasTable('chat_message_attachments')) {
            $message->load('attachments');
        }
        if (! $message->relationLoaded('reactions') && $this->schemaHasTable('chat_message_reactions')) {
            $this->loadReactionsSafely($message);
        }

        $isDeleted = $this->schemaHasColumn('chat_messages', 'deleted_at') && $message->deleted_at !== null;
        $attachments = $isDeleted ? [] : $this->serializeAttachments($message);
        $legacy = $attachments[0] ?? null;

        $mentionIds = collect($message->mentioned_user_ids ?? [])
            ->map(static fn ($id): string => (string) $id)
            ->filter()
            ->values();

        if ($mentionUsersById === null) {
            $mentionUsersById = $mentionIds->isEmpty()
                ? collect()
                : User::query()->whereIn('id', $mentionIds->all())->get(['id', 'name'])->keyBy(
                    static fn (User $u): string => (string) $u->id,
                );
        }

        $mentions = $mentionIds
            ->map(static fn (string $id): array => [
                'id' => $id,
                'name' => (string) ($mentionUsersById->get($id)?->name ?? 'Usuario'),
            ])
            ->all();

        $reply = $message->replyTo;
        $replyPreview = $reply === null ? null : [
            'id' => (string) $reply->id,
            'body' => $this->previewFromRow(
                $reply->body,
                $reply->attachment_name,
                $reply->attachment_mime,
            ),
            'user_id' => (string) $reply->user_id,
            'user_name' => (string) ($reply->user?->name ?? 'Usuario'),
        ];

        $participantBag = $participants;
        if ($participantBag === null) {
            if (! $conversation->relationLoaded('participants')) {
                $conversation->load(['participants.user:id,name']);
            }
            $participantBag = $conversation->participants;
        }

        $readBy = $this->readersFromParticipants($message, $participantBag);
        $mine = (string) $message->user_id === (string) $actor->id;

        $payload = [
            'id' => (string) $message->id,
            'body' => $isDeleted ? '' : ($message->body !== null ? (string) $message->body : ''),
            'user_id' => (string) $message->user_id,
            'user_name' => (string) ($message->user?->name ?? 'Usuario'),
            'created_at' => $message->created_at?->toIso8601String(),
            'edited_at' => $this->schemaHasColumn('chat_messages', 'edited_at')
                ? $message->edited_at?->toIso8601String()
                : null,
            'deleted' => $isDeleted,
            'is_deleted' => $isDeleted,
            'deleted_at' => $isDeleted ? $message->deleted_at?->toIso8601String() : null,
            'mine' => $mine,
            'reply_to_id' => $message->reply_to_id !== null ? (string) $message->reply_to_id : null,
            'reply_to' => $replyPreview,
            'mentioned_user_ids' => $mentionIds->all(),
            'mentions' => $mentions,
            'attachment' => $legacy,
            'attachments' => $attachments,
            'reactions' => $this->serializeReactions($message, $actor),
            'read_by' => $readBy,
        ];

        if ($mine) {
            $payload['delivery_status'] = $this->deliveryStatusForMessage(
                $message,
                $conversation,
                $readBy,
                $deliveredUserIds,
            );
        }

        return $payload;
    }

    /**
     * @param  list<array{user_id: string, name: string, read_at: ?string}>  $readBy
     * @param  list<string>|null  $deliveredUserIds
     */
    private function deliveryStatusForMessage(
        ChatMessage $message,
        ChatConversation $conversation,
        array $readBy,
        ?array $deliveredUserIds = null,
    ): string {
        $otherIds = $this->otherParticipantIds($conversation, (string) $message->user_id);
        $readerIds = collect($readBy)
            ->pluck('user_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        if ($otherIds !== [] && count(array_diff($otherIds, $readerIds)) === 0) {
            return 'read';
        }

        if ($readerIds !== []) {
            return 'delivered';
        }

        if ($deliveredUserIds === null && Schema::hasTable('chat_message_deliveries')) {
            $deliveredUserIds = ChatMessageDelivery::query()
                ->where('message_id', $message->id)
                ->pluck('user_id')
                ->map(static fn ($id): string => (string) $id)
                ->all();
        }

        $deliveredUserIds = $deliveredUserIds ?? [];
        $anyDelivery = count(array_intersect($otherIds, $deliveredUserIds)) > 0
            || ($otherIds === [] && $deliveredUserIds !== []);

        return $anyDelivery ? 'delivered' : 'sent';
    }

    /**
     * @return list<string>
     */
    private function otherParticipantIds(ChatConversation $conversation, string $authorId): array
    {
        if (! $conversation->relationLoaded('participants')) {
            $conversation->load('participants:id,conversation_id,user_id');
        }

        return $conversation->participants
            ->pluck('user_id')
            ->map(static fn ($id): string => (string) $id)
            ->filter(static fn (string $id): bool => $id !== '' && $id !== $authorId)
            ->values()
            ->all();
    }

    /**
     * @return list<array{emoji: string, count: int, mine: bool, user_ids: list<string>}>
     */
    private function serializeReactions(ChatMessage $message, User $actor): array
    {
        if (! Schema::hasTable('chat_message_reactions')) {
            return [];
        }

        try {
            $reactions = $message->relationLoaded('reactions')
                ? $message->reactions
                : $message->reactions()->get();
        } catch (QueryException) {
            return [];
        }

        /** @var Collection<string, Collection<int, ChatMessageReaction>> $grouped */
        $grouped = $reactions->groupBy('emoji');

        return $grouped
            ->map(function (Collection $group, string $emoji) use ($actor): array {
                $userIds = $group
                    ->pluck('user_id')
                    ->map(static fn ($id): string => (string) $id)
                    ->values()
                    ->all();

                return [
                    'emoji' => $emoji,
                    'count' => count($userIds),
                    'mine' => in_array((string) $actor->id, $userIds, true),
                    'reacted' => in_array((string) $actor->id, $userIds, true),
                    'user_ids' => $userIds,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Crea o reutiliza el grupo fijo «Soporte VetSaaS» y sincroniza participantes.
     *
     * @param  list<string>  $adminUserIds
     */
    public function ensureSupportGroup(User $supportUser, array $adminUserIds): ChatConversation
    {
        $adminUserIds = collect($adminUserIds)
            ->map(static fn ($id): string => (string) $id)
            ->filter(static fn (string $id): bool => $id !== '' && $id !== (string) $supportUser->id)
            ->unique()
            ->values()
            ->all();

        $conversation = ChatConversation::query()
            ->where('type', ChatConversation::TYPE_GROUP)
            ->where(function ($q): void {
                if ($this->schemaHasColumn('chat_conversations', 'kind')) {
                    $q->where('kind', ChatConversation::KIND_SUPPORT);
                }
                $q->orWhereRaw('lower(name) = lower(?)', [self::SUPPORT_GROUP_NAME]);
            })
            ->first();

        if ($conversation === null) {
            $conversation = $this->createGroup($supportUser, self::SUPPORT_GROUP_NAME, $adminUserIds);
            if ($this->schemaHasColumn('chat_conversations', 'kind')) {
                $conversation->forceFill(['kind' => ChatConversation::KIND_SUPPORT])->save();
            }
        } else {
            if ($this->schemaHasColumn('chat_conversations', 'kind')
                && $conversation->kind !== ChatConversation::KIND_SUPPORT) {
                $conversation->forceFill(['kind' => ChatConversation::KIND_SUPPORT])->save();
            }
            if ((string) ($conversation->name ?? '') !== self::SUPPORT_GROUP_NAME) {
                $conversation->forceFill(['name' => self::SUPPORT_GROUP_NAME])->save();
            }
        }

        $now = now();
        $existing = ChatParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->pluck('user_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        $want = array_values(array_unique(array_merge([(string) $supportUser->id], $adminUserIds)));

        foreach ($want as $uid) {
            if (in_array($uid, $existing, true)) {
                continue;
            }
            $row = [
                'conversation_id' => $conversation->id,
                'user_id' => $uid,
                'joined_at' => $now,
                'last_read_at' => $uid === (string) $supportUser->id ? $now : null,
            ];
            if ($this->schemaHasColumn('chat_participants', 'pinned_at')
                && $uid !== (string) $supportUser->id) {
                $row['pinned_at'] = $now;
            }
            ChatParticipant::query()->create($row);
        }

        if ($this->schemaHasColumn('chat_participants', 'pinned_at')) {
            ChatParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->whereIn('user_id', $adminUserIds)
                ->whereNull('pinned_at')
                ->update(['pinned_at' => $now]);
        }

        return $conversation->fresh(['participants.user:id,name,email']) ?? $conversation;
    }

    /**
     * En modo soporte (impersonación), el superadmin no es admin_clinica:
     * lo agregamos como participante del grupo Soporte para que pueda ver el hilo.
     */
    public function ensurePlatformViewerInSupportConversation(User $viewer): void
    {
        try {
            if (! $viewer->isPlatformSuperadmin()) {
                try {
                    if (! $viewer->can('plataforma-chat-soporte.view')) {
                        return;
                    }
                } catch (Throwable) {
                    return;
                }
            }
        } catch (Throwable) {
            return;
        }

        $conversation = ChatConversation::query()
            ->where('type', ChatConversation::TYPE_GROUP)
            ->where(function ($q): void {
                if ($this->schemaHasColumn('chat_conversations', 'kind')) {
                    $q->where('kind', ChatConversation::KIND_SUPPORT);
                }
                $q->orWhereRaw('lower(name) = lower(?)', [self::SUPPORT_GROUP_NAME]);
            })
            ->first();

        if ($conversation === null) {
            return;
        }

        $exists = ChatParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $viewer->id)
            ->exists();

        if ($exists) {
            return;
        }

        $row = [
            'conversation_id' => $conversation->id,
            'user_id' => $viewer->id,
            'joined_at' => now(),
            // null = mismo criterio que admin_clinica: verá no leídos del bot de soporte
            'last_read_at' => null,
        ];
        if ($this->schemaHasColumn('chat_participants', 'pinned_at')) {
            $row['pinned_at'] = now();
        }

        ChatParticipant::query()->create($row);
    }

    /**
     * Notifica al grupo (p. ej. "Caja"): encuentra o crea el grupo y envía el mensaje.
     */
    public function notifyTeam(User $actor, string $body, string $groupName = 'Caja'): ChatConversation
    {
        $body = trim($body);
        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => __('Escribe un mensaje.'),
            ]);
        }

        $groupName = trim($groupName) !== '' ? trim($groupName) : 'Caja';

        $conversation = ChatConversation::query()
            ->where('type', ChatConversation::TYPE_GROUP)
            ->whereRaw('lower(name) = lower(?)', [$groupName])
            ->whereHas('participants', static fn ($q) => $q->where('user_id', $actor->id))
            ->first();

        if ($conversation === null) {
            $memberIds = $this->cashTeamUserIds((string) $actor->id);
            $conversation = $this->createGroup($actor, $groupName, $memberIds);
        }

        $this->sendMessage($conversation, $actor, $body);

        return $conversation;
    }

    /**
     * Elimina mensajes más antiguos que N días (y archivos de adjuntos si es posible).
     */
    public function pruneOlderThan(int $days): int
    {
        if ($days < 1 || ! Schema::hasTable('chat_messages')) {
            return 0;
        }

        $cutoff = now()->subDays($days);
        $deleted = 0;

        ChatMessage::query()
            ->where('created_at', '<', $cutoff)
            ->orderBy('created_at')
            ->chunkById(100, function (Collection $messages) use (&$deleted): void {
                foreach ($messages as $message) {
                    /** @var ChatMessage $message */
                    $this->deleteMessageFiles($message);
                    $message->forceDelete();
                    $deleted++;
                }
            });

        return $deleted;
    }

    public function unreadTotalFor(User $actor): int
    {
        if (! Schema::hasTable('chat_messages') || ! Schema::hasTable('chat_participants')) {
            return 0;
        }

        return (int) DB::table('chat_messages as m')
            ->join('chat_participants as p', function ($join) use ($actor): void {
                $join->on('p.conversation_id', '=', 'm.conversation_id')
                    ->where('p.user_id', '=', $actor->id);
            })
            ->where('m.user_id', '!=', $actor->id)
            ->when(
                Schema::hasColumn('chat_messages', 'deleted_at'),
                static fn ($q) => $q->whereNull('m.deleted_at'),
            )
            ->where(function ($q): void {
                $q->whereNull('p.last_read_at')
                    ->orWhereColumn('m.created_at', '>', 'p.last_read_at');
            })
            ->count();
    }

    /**
     * Payload liviano para badge + toast global.
     *
     * @return array{
     *     unread_total: int,
     *     latest: ?array{
     *         message_id: string,
     *         conversation_id: string,
     *         user_name: string,
     *         preview: string,
     *         created_at: ?string,
     *         muted: bool,
     *         is_mention: bool
     *     }
     * }
     */
    public function inboxPing(User $actor): array
    {
        if (! Schema::hasTable('chat_messages')) {
            return ['unread_total' => 0, 'latest' => null];
        }

        $unread = $this->unreadTotalFor($actor);

        $mentionRow = null;
        if (Schema::hasColumn('chat_messages', 'mentioned_user_ids')) {
            $mentionRow = $this->latestUnreadInboxRow($actor, preferMention: true);
        }

        $isMention = $mentionRow !== null;
        $row = $mentionRow ?? $this->latestUnreadInboxRow($actor, preferMention: false);

        if ($row === null) {
            return ['unread_total' => $unread, 'latest' => null];
        }

        $conversation = ChatConversation::query()->find((string) $row->conversation_id);
        if ($conversation !== null) {
            try {
                $this->markDelivered($conversation, $actor);
            } catch (Throwable) {
                // Entrega opcional.
            }
        }

        $preview = $this->previewFromRow(
            isset($row->body) ? (string) $row->body : null,
            isset($row->attachment_name) ? (string) $row->attachment_name : null,
            isset($row->attachment_mime) ? (string) $row->attachment_mime : null,
        );

        $muted = Schema::hasColumn('chat_participants', 'muted_at')
            && isset($row->muted_at)
            && $row->muted_at !== null;

        return [
            'unread_total' => $unread,
            'latest' => [
                'message_id' => (string) $row->id,
                'conversation_id' => (string) $row->conversation_id,
                'user_name' => (string) ($row->user_name ?: 'Usuario'),
                'preview' => $preview,
                'created_at' => isset($row->created_at)
                    ? Carbon::parse($row->created_at)->toIso8601String()
                    : null,
                'muted' => $muted,
                'is_mention' => $isMention,
            ],
        ];
    }

    /**
     * @return object{
     *     id: mixed,
     *     conversation_id: mixed,
     *     body: mixed,
     *     created_at: mixed,
     *     user_name: mixed,
     *     attachment_name?: mixed,
     *     attachment_mime?: mixed,
     *     muted_at?: mixed
     * }|null
     */
    private function latestUnreadInboxRow(User $actor, bool $preferMention): ?object
    {
        $q = DB::table('chat_messages as m')
            ->join('chat_participants as p', function ($join) use ($actor): void {
                $join->on('p.conversation_id', '=', 'm.conversation_id')
                    ->where('p.user_id', '=', $actor->id);
            })
            ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.user_id', '!=', $actor->id)
            ->when(
                Schema::hasColumn('chat_messages', 'deleted_at'),
                static fn ($query) => $query->whereNull('m.deleted_at'),
            )
            ->where(function ($query): void {
                $query->whereNull('p.last_read_at')
                    ->orWhereColumn('m.created_at', '>', 'p.last_read_at');
            });

        if ($preferMention) {
            $q->whereNotNull('m.mentioned_user_ids')
                ->whereJsonContains('m.mentioned_user_ids', (string) $actor->id);
        } elseif (Schema::hasColumn('chat_participants', 'muted_at')) {
            // No-mención: excluir silenciados del candidato a toast.
            $q->whereNull('p.muted_at');
        }

        $select = [
            'm.id',
            'm.conversation_id',
            'm.body',
            'm.created_at',
            'u.name as user_name',
        ];

        if (Schema::hasColumn('chat_participants', 'muted_at')) {
            $select[] = 'p.muted_at';
        }

        if (Schema::hasColumn('chat_messages', 'attachment_name')) {
            $select[] = 'm.attachment_name';
            $select[] = 'm.attachment_mime';
        }

        return $q->orderByDesc('m.created_at')
            ->select($select)
            ->first();
    }

    public function previewFromRow(?string $body, ?string $attachmentName, ?string $attachmentMime): string
    {
        $text = trim((string) $body);
        if ($text !== '') {
            return Str::limit($text, 120);
        }

        $mime = (string) $attachmentMime;
        if (str_starts_with($mime, 'image/')) {
            return '📷 Foto';
        }
        if ($attachmentName) {
            return '📎 '.$attachmentName;
        }

        return 'Nuevo mensaje';
    }

    public function setViewing(User $user, ?string $conversationId): void
    {
        $key = $this->viewingCacheKey((string) $user->id);

        if ($conversationId === null || $conversationId === '') {
            Cache::forget($key);

            return;
        }

        Cache::put($key, $conversationId, self::VIEWING_TTL_SECONDS);
    }

    public function viewingConversationId(User $user): ?string
    {
        $raw = Cache::get($this->viewingCacheKey((string) $user->id));

        return is_string($raw) && $raw !== '' ? $raw : null;
    }

    /**
     * Marca como entregados los mensajes recientes de otros en la conversación.
     */
    public function markDelivered(ChatConversation $conversation, User $actor): void
    {
        if (! Schema::hasTable('chat_message_deliveries')) {
            return;
        }

        $this->assertCanAccessConversation($conversation, $actor);
        if ($this->participantOf($conversation, $actor) === null) {
            return;
        }

        $messageIds = ChatMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $actor->id)
            ->when(
                Schema::hasColumn('chat_messages', 'deleted_at'),
                static fn ($q) => $q->whereNull('deleted_at'),
            )
            ->orderByDesc('created_at')
            ->limit(100)
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        if ($messageIds === []) {
            return;
        }

        $existing = ChatMessageDelivery::query()
            ->where('user_id', $actor->id)
            ->whereIn('message_id', $messageIds)
            ->pluck('message_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        $missing = array_values(array_diff($messageIds, $existing));
        if ($missing === []) {
            return;
        }

        $now = now();
        $rows = array_map(static fn (string $messageId): array => [
            'id' => (string) Str::uuid(),
            'message_id' => $messageId,
            'user_id' => (string) $actor->id,
            'delivered_at' => $now,
        ], $missing);

        try {
            ChatMessageDelivery::query()->insert($rows);
        } catch (QueryException|Throwable) {
            // Carrera / drift de schema: no romper el chat.
        }
    }

    public function markRead(ChatConversation $conversation, User $actor): void
    {
        $this->assertCanAccessConversation($conversation, $actor);
        if ($this->participantOf($conversation, $actor) === null) {
            return;
        }

        $this->setViewing($actor, (string) $conversation->id);
        $this->markDelivered($conversation, $actor);

        $lastReadAt = now();

        ChatParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $actor->id)
            ->update(['last_read_at' => $lastReadAt]);

        $this->dispatchRead($conversation, $actor, $lastReadAt->toIso8601String());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listConversationsPayload(User $actor): array
    {
        $observeAll = $this->canObserveClinicChats();

        $conversationIds = $observeAll
            ? ChatConversation::query()->pluck('id')
            : ChatParticipant::query()
                ->where('user_id', $actor->id)
                ->pluck('conversation_id');

        if ($conversationIds->isEmpty()) {
            return [];
        }

        $conversations = ChatConversation::query()
            ->whereIn('id', $conversationIds)
            ->with([
                'participants.user:id,name,email',
                'messages' => static fn ($q) => $q->latest('created_at')->limit(1)->with('user:id,name'),
            ])
            ->get()
            ->sortByDesc(function (ChatConversation $c): int {
                $last = $c->messages->first()?->created_at ?? $c->updated_at;

                return $last instanceof Carbon ? $last->getTimestamp() : 0;
            })
            ->values();

        $myReads = ChatParticipant::query()
            ->where('user_id', $actor->id)
            ->whereIn('conversation_id', $conversationIds)
            ->get()
            ->keyBy('conversation_id');

        $unreadCounts = $this->unreadCountsFor($actor, $conversationIds->all(), $myReads);

        $peerIds = [];
        foreach ($conversations as $conversation) {
            if (! $conversation->isDirect()) {
                continue;
            }
            $peer = $conversation->participants
                ->first(static fn (ChatParticipant $p): bool => (string) $p->user_id !== (string) $actor->id);
            if ($peer !== null) {
                $peerIds[] = (string) $peer->user_id;
            }
        }
        $presenceMap = $peerIds === [] ? [] : $this->presenceForUsers(array_values(array_unique($peerIds)));

        $payload = [];
        foreach ($conversations as $conversation) {
            /** @var ?ChatParticipant $mine */
            $mine = $myReads->get($conversation->id);
            $muted = $this->schemaHasColumn('chat_participants', 'muted_at')
                && $mine?->muted_at !== null;
            $pinned = $this->schemaHasColumn('chat_participants', 'pinned_at')
                && $mine?->pinned_at !== null;

            $payload[] = $this->serializeConversationSummary(
                $conversation,
                $actor,
                (int) ($unreadCounts[$conversation->id] ?? 0),
                $muted,
                $pinned,
                $presenceMap,
            );
        }

        return $payload;
    }

    /**
     * @param  list<string>  $conversationIds
     * @param  Collection<string, ChatParticipant>  $myReads
     * @return array<string, int>
     */
    private function unreadCountsFor(User $actor, array $conversationIds, Collection $myReads): array
    {
        $counts = array_fill_keys($conversationIds, 0);
        if ($conversationIds === []) {
            return $counts;
        }

        $q = ChatMessage::query()
            ->from('chat_messages as m')
            ->join('chat_participants as p', function ($join) use ($actor): void {
                $join->on('p.conversation_id', '=', 'm.conversation_id')
                    ->where('p.user_id', '=', $actor->id);
            })
            ->whereIn('m.conversation_id', $conversationIds)
            ->where('m.user_id', '!=', $actor->id)
            ->where(function ($inner): void {
                $inner->whereNull('p.last_read_at')
                    ->orWhereColumn('m.created_at', '>', 'p.last_read_at');
            });

        if ($this->schemaHasColumn('chat_messages', 'deleted_at')) {
            $q->whereNull('m.deleted_at');
        }

        $rows = $q
            ->groupBy('m.conversation_id')
            ->selectRaw('m.conversation_id as conversation_id, COUNT(*) as c')
            ->pluck('c', 'conversation_id');

        foreach ($rows as $cid => $count) {
            $counts[(string) $cid] = (int) $count;
        }

        return $counts;
    }

    /**
     * @param  array<string, array{online: bool, last_seen_at: ?string}>|null  $presenceMap
     * @return array<string, mixed>
     */
    public function serializeConversationSummary(
        ChatConversation $conversation,
        User $actor,
        int $unread,
        ?bool $muted = null,
        ?bool $pinned = null,
        ?array $presenceMap = null,
    ): array {
        $last = $conversation->messages->first();
        $participants = $conversation->participants
            ->map(static fn (ChatParticipant $p): array => [
                'id' => (string) $p->user_id,
                'name' => (string) ($p->user?->name ?? 'Usuario'),
            ])
            ->values()
            ->all();

        $mine = $conversation->participants
            ->first(static fn (ChatParticipant $p): bool => (string) $p->user_id === (string) $actor->id);

        if ($muted === null) {
            $muted = $this->schemaHasColumn('chat_participants', 'muted_at')
                && $mine?->muted_at !== null;
        }

        if ($pinned === null) {
            $pinned = $this->schemaHasColumn('chat_participants', 'pinned_at')
                && $mine?->pinned_at !== null;
        }

        $presence = null;
        if ($conversation->isDirect()) {
            $peer = $conversation->participants
                ->first(static fn (ChatParticipant $p): bool => (string) $p->user_id !== (string) $actor->id);
            if ($peer !== null) {
                $pid = (string) $peer->user_id;
                if ($presenceMap !== null) {
                    $presence = $presenceMap[$pid] ?? null;
                } else {
                    $presence = $this->presenceForUsers([$pid])[$pid] ?? null;
                }
            }
        }

        return [
            'id' => (string) $conversation->id,
            'type' => $conversation->type,
            'kind' => $this->schemaHasColumn('chat_conversations', 'kind')
                ? (string) ($conversation->kind ?? ChatConversation::KIND_TEAM)
                : ($conversation->isSupport() ? ChatConversation::KIND_SUPPORT : ChatConversation::KIND_TEAM),
            'is_support' => $conversation->isSupport(),
            'title' => $this->titleFor($conversation, $actor),
            'name' => $conversation->name,
            'participants' => $participants,
            'participant_count' => count($participants),
            'unread' => $unread,
            'muted' => (bool) $muted,
            'pinned' => (bool) $pinned,
            'presence' => $presence,
            'peer_online' => $presence['online'] ?? null,
            'peer_last_seen_at' => $presence['last_seen_at'] ?? null,
            'can_write' => $mine !== null,
            'last_message' => $last === null ? null : [
                'body' => $this->previewFromRow(
                    $last->body,
                    $last->attachment_name,
                    $last->attachment_mime,
                ),
                'user_name' => (string) ($last->user?->name ?? ''),
                'created_at' => $last->created_at?->toIso8601String(),
                'mine' => (string) $last->user_id === (string) $actor->id,
                'has_attachment' => filled($last->attachment_path),
            ],
            'updated_at' => ($last?->created_at ?? $conversation->updated_at)?->toIso8601String(),
        ];
    }

    public function titleFor(ChatConversation $conversation, User $actor): string
    {
        if ($conversation->isGroup()) {
            return (string) ($conversation->name ?: 'Grupo');
        }

        $isMember = $conversation->participants
            ->contains(static fn (ChatParticipant $p): bool => (string) $p->user_id === (string) $actor->id);

        if (! $isMember) {
            $names = $conversation->participants
                ->map(static fn (ChatParticipant $p): string => trim((string) ($p->user?->name ?? '')))
                ->filter()
                ->unique()
                ->values();

            return $names->isNotEmpty() ? $names->implode(' · ') : 'Chat';
        }

        $other = $conversation->participants
            ->first(static fn (ChatParticipant $p): bool => (string) $p->user_id !== (string) $actor->id);

        return (string) ($other?->user?->name ?? 'Chat');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function messagesPayload(
        ChatConversation $conversation,
        User $actor,
        ?string $beforeId = null,
    ): array {
        if (! $conversation->relationLoaded('participants')) {
            $conversation->load(['participants.user:id,name']);
        }

        $with = ['user:id,name', 'replyTo.user:id,name'];
        if ($this->schemaHasTable('chat_message_attachments')) {
            $with[] = 'attachments';
        }

        $q = ChatMessage::query()
            ->where('conversation_id', $conversation->id)
            ->with($with)
            ->orderByDesc('created_at')
            ->limit(self::MESSAGES_PAGE);

        if ($beforeId !== null && $beforeId !== '') {
            $pivot = ChatMessage::query()->whereKey($beforeId)->first();
            if ($pivot !== null) {
                $q->where('created_at', '<', $pivot->created_at);
            }
        }

        $messages = $q->get();
        $this->loadReactionsSafely($messages);
        $deliveriesByMessage = $this->deliveriesByMessageIds(
            $messages->pluck('id')->map(static fn ($id): string => (string) $id)->all(),
        );

        $mentionIds = $messages
            ->flatMap(static fn (ChatMessage $m) => collect($m->mentioned_user_ids ?? []))
            ->map(static fn ($id): string => (string) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $mentionUsersById = $mentionIds === []
            ? collect()
            : User::query()->whereIn('id', $mentionIds)->get(['id', 'name'])->keyBy(
                static fn (User $u): string => (string) $u->id,
            );

        $participants = $conversation->participants;

        return $messages
            ->sortBy('created_at')
            ->values()
            ->map(fn (ChatMessage $m): array => $this->serializeMessage(
                $m,
                $actor,
                $conversation,
                $deliveriesByMessage[(string) $m->id] ?? [],
                $participants,
                $mentionUsersById,
            ))
            ->all();
    }

    /**
     * Contexto alrededor de un mensaje (p. ej. salto desde búsqueda).
     *
     * @return list<array<string, mixed>>
     */
    public function messageContext(
        ChatConversation $conversation,
        User $actor,
        ChatMessage $message,
    ): array {
        $this->assertCanAccessConversation($conversation, $actor);

        if ((string) $message->conversation_id !== (string) $conversation->id) {
            abort(404);
        }

        $with = ['user:id,name', 'replyTo.user:id,name'];
        if (Schema::hasTable('chat_message_attachments')) {
            $with[] = 'attachments';
        }

        $before = ChatMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('created_at', '<=', $message->created_at)
            ->when(
                Schema::hasColumn('chat_messages', 'deleted_at'),
                static fn ($q) => $q->where(function ($inner) use ($message): void {
                    $inner->whereNull('deleted_at')
                        ->orWhere('id', $message->id);
                }),
            )
            ->orderByDesc('created_at')
            ->limit(41)
            ->with($with)
            ->get();

        $after = ChatMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('created_at', '>', $message->created_at)
            ->when(
                Schema::hasColumn('chat_messages', 'deleted_at'),
                static fn ($q) => $q->whereNull('deleted_at'),
            )
            ->orderBy('created_at')
            ->limit(40)
            ->with($with)
            ->get();

        $messages = $before
            ->concat($after)
            ->unique('id')
            ->sortBy('created_at')
            ->values();

        $this->loadReactionsSafely($messages);
        $deliveriesByMessage = $this->deliveriesByMessageIds(
            $messages->pluck('id')->map(static fn ($id): string => (string) $id)->all(),
        );

        return $messages
            ->map(fn (ChatMessage $m): array => $this->serializeMessage(
                $m,
                $actor,
                $conversation,
                $deliveriesByMessage[(string) $m->id] ?? [],
            ))
            ->all();
    }

    /**
     * @param  list<string>  $messageIds
     * @return array<string, list<string>>
     */
    private function deliveriesByMessageIds(array $messageIds): array
    {
        if ($messageIds === [] || ! Schema::hasTable('chat_message_deliveries')) {
            return [];
        }

        $map = [];
        try {
            $rows = ChatMessageDelivery::query()
                ->whereIn('message_id', $messageIds)
                ->get(['message_id', 'user_id']);
        } catch (QueryException) {
            return [];
        }

        foreach ($rows as $row) {
            $mid = (string) $row->message_id;
            $map[$mid] ??= [];
            $map[$mid][] = (string) $row->user_id;
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    public function activePayload(ChatConversation $conversation, User $actor): array
    {
        $conversation->load(['participants.user:id,name,email']);

        $unread = 0;
        $summary = $this->serializeConversationSummary($conversation, $actor, $unread);

        $participantIds = $conversation->participants
            ->pluck('user_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        return [
            ...$summary,
            'messages' => $this->messagesPayload($conversation, $actor),
            'typing' => $this->typingPayload($conversation, $actor),
            'presence' => $this->presenceForUsers($participantIds),
        ];
    }

    /**
     * @param  UploadedFile|list<UploadedFile>|null  $attachment
     * @return list<UploadedFile>
     */
    private function normalizeAttachments(UploadedFile|array|null $attachment): array
    {
        if ($attachment === null) {
            return [];
        }

        if ($attachment instanceof UploadedFile) {
            return [$attachment];
        }

        $files = [];
        foreach ($attachment as $file) {
            if ($file instanceof UploadedFile) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<array{path: string, name: string, mime: string, size: int}>
     */
    private function storeAttachmentFiles(array $files, ChatConversation $conversation, ?string $tenantSlug): array
    {
        if ($files === [] || ! Schema::hasColumn('chat_messages', 'attachment_path')) {
            return [];
        }

        $slug = preg_replace('/[^a-z0-9\-_]/i', '', (string) ($tenantSlug ?: 'shared')) ?: 'shared';
        $dir = "tenants/{$slug}/chat/{$conversation->id}";
        $stored = [];

        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
            $filename = Str::uuid()->toString().'.'.$extension;
            Storage::disk('public')->putFileAs($dir, $file, $filename, 'public');

            $stored[] = [
                'path' => $dir.'/'.$filename,
                'name' => Str::limit((string) $file->getClientOriginalName(), 240, ''),
                'mime' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
                'size' => (int) $file->getSize(),
            ];
        }

        return $stored;
    }

    /**
     * @return list<array{url: ?string, name: string, mime: string, size: int, is_image: bool, path?: string}>
     */
    private function serializeAttachments(ChatMessage $message): array
    {
        $items = [];

        if ($message->relationLoaded('attachments') || Schema::hasTable('chat_message_attachments')) {
            foreach ($message->attachments as $att) {
                /** @var ChatMessageAttachment $att */
                $items[] = [
                    'url' => $att->url,
                    'name' => (string) ($att->name ?: 'archivo'),
                    'mime' => (string) ($att->mime ?? ''),
                    'size' => (int) ($att->size ?? 0),
                    'is_image' => $att->isImage(),
                ];
            }
        }

        if ($items === [] && filled($message->attachment_path)) {
            $items[] = [
                'url' => $message->attachment_url,
                'name' => (string) ($message->attachment_name ?? 'archivo'),
                'mime' => (string) ($message->attachment_mime ?? ''),
                'size' => (int) ($message->attachment_size ?? 0),
                'is_image' => $message->isImage(),
            ];
        }

        return $items;
    }

    private function typingCacheKey(string $conversationId, string $userId): string
    {
        return "chat:typing:{$conversationId}:{$userId}";
    }

    private function presenceCacheKey(string $userId): string
    {
        return "chat:presence:{$userId}";
    }

    private function viewingCacheKey(string $userId): string
    {
        return "chat:viewing:{$userId}";
    }

    private function loadMessageRelations(ChatMessage $message): void
    {
        $with = ['user:id,name'];
        if (Schema::hasColumn('chat_messages', 'reply_to_id')) {
            $with[] = 'replyTo.user:id,name';
        }
        if (Schema::hasTable('chat_message_attachments')) {
            $with[] = 'attachments';
        }
        $message->load($with);
        $this->loadReactionsSafely($message);
    }

    /**
     * @param  ChatMessage|\Illuminate\Database\Eloquent\Collection<int, ChatMessage>  $messages
     */
    private function loadReactionsSafely(ChatMessage|\Illuminate\Database\Eloquent\Collection $messages): void
    {
        if (! $this->schemaHasTable('chat_message_reactions')) {
            return;
        }

        try {
            $messages->load('reactions');
        } catch (QueryException) {
            // Tabla ausente / drift de schema en prod: no romper el chat.
        }
    }

    private function canBroadcast(): bool
    {
        $driver = config('broadcasting.default');
        if ($driver === null || $driver === '' || $driver === 'log') {
            return false;
        }

        if ($driver === 'reverb' && ! class_exists(Pusher::class)) {
            return false;
        }

        return true;
    }

    private function resolveTenantIdForBroadcast(): ?string
    {
        $tenantId = app(TenantManager::class)->id();
        if ($tenantId === null || $tenantId === '') {
            return null;
        }

        return (string) $tenantId;
    }

    private function dispatchTyping(ChatConversation $conversation, User $user, string $at): void
    {
        try {
            if (! $this->canBroadcast()) {
                return;
            }

            $tenantId = $this->resolveTenantIdForBroadcast();
            if ($tenantId === null) {
                return;
            }

            event(new ChatTyping(
                $tenantId,
                (string) $conversation->id,
                (string) $user->id,
                (string) ($user->name ?: 'Usuario'),
                $at,
            ));
        } catch (Throwable) {
            // Broadcasting opcional.
        }
    }

    private function dispatchRead(ChatConversation $conversation, User $actor, string $lastReadAt): void
    {
        try {
            if (! $this->canBroadcast()) {
                return;
            }

            $tenantId = $this->resolveTenantIdForBroadcast();
            if ($tenantId === null) {
                return;
            }

            event(new ChatRead(
                $tenantId,
                (string) $conversation->id,
                (string) $actor->id,
                $lastReadAt,
            ));
        } catch (Throwable) {
            // Broadcasting opcional.
        }
    }

    private function dispatchPresence(User $user, string $lastSeenAt, ?string $conversationId = null): void
    {
        try {
            if (! $this->canBroadcast()) {
                return;
            }

            $tenantId = $this->resolveTenantIdForBroadcast();
            if ($tenantId === null) {
                return;
            }

            event(new ChatPresence(
                $tenantId,
                (string) $user->id,
                true,
                $lastSeenAt,
                $conversationId !== null && $conversationId !== '' ? $conversationId : null,
            ));
        } catch (Throwable) {
            // Broadcasting opcional.
        }
    }

    private function dispatchMessageCreated(
        ChatConversation $conversation,
        User $actor,
        ChatMessage $message,
    ): void {
        try {
            if (! $this->canBroadcast()) {
                return;
            }

            $tenantId = $this->resolveTenantIdForBroadcast();
            if ($tenantId === null) {
                return;
            }

            $serialized = $this->serializeMessage($message, $actor, $conversation);
            $preview = $this->previewFromRow(
                $message->body,
                $message->attachment_name,
                $message->attachment_mime,
            );

            event(new ChatMessageCreated(
                $tenantId,
                (string) $conversation->id,
                $serialized,
                $preview,
                (string) ($actor->name ?: 'Usuario'),
            ));
        } catch (Throwable) {
            // Broadcasting opcional: no bloquear el envío del mensaje.
        }
    }

    private function dispatchMessageUpdated(
        ChatConversation $conversation,
        User $actor,
        ChatMessage $message,
        string $reason = 'updated',
    ): void {
        try {
            if (! $this->canBroadcast()) {
                return;
            }

            $tenantId = $this->resolveTenantIdForBroadcast();
            if ($tenantId === null) {
                return;
            }

            $serialized = $this->serializeMessage($message, $actor, $conversation);

            event(new ChatMessageUpdated(
                $tenantId,
                (string) $conversation->id,
                $serialized,
                $reason,
            ));
        } catch (Throwable) {
            // Broadcasting opcional.
        }
    }

    private function notifyParticipantsWebPush(
        ChatConversation $conversation,
        User $actor,
        ChatMessage $message,
    ): void {
        try {
            $participantCols = ['user_id'];
            if (Schema::hasColumn('chat_participants', 'muted_at')) {
                $participantCols[] = 'muted_at';
            }

            $participants = ChatParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', '!=', $actor->id)
                ->get($participantCols);

            if ($participants->isEmpty()) {
                return;
            }

            $preview = $this->previewFromRow(
                $message->body,
                $message->attachment_name,
                $message->attachment_mime,
            );

            $title = $this->titleFor(
                $conversation->loadMissing('participants.user:id,name'),
                $actor,
            );

            $mentionIds = collect($message->mentioned_user_ids ?? [])
                ->map(static fn ($id): string => (string) $id)
                ->filter()
                ->all();

            $actorName = (string) ($actor->name ?: 'Usuario');
            $chatUrl = '/comunicaciones/chat?c='.$conversation->id;
            $mentionUrl = $chatUrl.'&m='.$message->id;
            $sender = app(WebPushSender::class);

            $normalIds = [];
            $mentionPushIds = [];

            foreach ($participants as $participant) {
                $uid = (string) $participant->user_id;
                if ($uid === '') {
                    continue;
                }

                $viewing = Cache::get($this->viewingCacheKey($uid));
                if (is_string($viewing) && $viewing === (string) $conversation->id) {
                    continue;
                }

                $isMentioned = in_array($uid, $mentionIds, true);
                $isMuted = Schema::hasColumn('chat_participants', 'muted_at')
                    && $participant->muted_at !== null;

                if ($isMentioned) {
                    $mentionPushIds[] = $uid;

                    continue;
                }

                if ($isMuted) {
                    continue;
                }

                $normalIds[] = $uid;
            }

            if ($mentionPushIds !== []) {
                $sender->sendToUsers($mentionPushIds, [
                    'title' => __('Te mencionaron'),
                    'body' => $actorName.': '.$preview,
                    'url' => $mentionUrl,
                    'tag' => 'chat-mention-'.$message->id,
                ]);
            }

            if ($normalIds !== []) {
                $sender->sendToUsers($normalIds, [
                    'title' => $title,
                    'body' => $actorName.': '.$preview,
                    'url' => $chatUrl,
                    'tag' => 'chat-'.$conversation->id,
                ]);
            }
        } catch (Throwable) {
            // Push opcional.
        }
    }

    private function deleteMessageFiles(ChatMessage $message): void
    {
        $paths = [];

        if (filled($message->attachment_path)) {
            $paths[] = (string) $message->attachment_path;
        }

        if (Schema::hasTable('chat_message_attachments')) {
            if (! $message->relationLoaded('attachments')) {
                $message->load('attachments');
            }
            foreach ($message->attachments as $att) {
                if (filled($att->path)) {
                    $paths[] = (string) $att->path;
                }
            }
        }

        foreach (array_unique($paths) as $path) {
            try {
                Storage::disk('public')->delete($path);
            } catch (Throwable) {
                // Continuar con el resto.
            }
        }
    }
}
