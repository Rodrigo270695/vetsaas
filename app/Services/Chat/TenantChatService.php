<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Events\Chat\ChatMessageCreated;
use App\Events\Chat\ChatMessageUpdated;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\ChatMessageReaction;
use App\Models\ChatParticipant;
use App\Models\User;
use App\Services\Push\WebPushSender;
use App\Support\Tenancy\ClinicAdminScope;
use App\Tenancy\TenantManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class TenantChatService
{
    public const MESSAGES_PAGE = 80;

    public const MAX_ATTACHMENTS = 5;

    /** @var list<string> */
    public const ALLOWED_REACTION_EMOJIS = ['👍', '✅', '❤️', '😂', '🎉'];

    public const PRESENCE_TTL_SECONDS = 90;

    public const PRESENCE_ONLINE_SECONDS = 60;

    /**
     * @return Collection<int, User>
     */
    public function directoryUsers(?string $exceptUserId = null): Collection
    {
        $q = ClinicAdminScope::usersQuery()
            ->where('is_active', true)
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

    public function assertParticipant(ChatConversation $conversation, User $user): ChatParticipant
    {
        $participant = ChatParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->first();

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

        $message->load([
            'user:id,name',
            'replyTo.user:id,name',
            'attachments',
            'reactions',
        ]);

        $this->dispatchMessageCreated($conversation, $actor, $message);
        $this->notifyParticipantsWebPush($conversation, $actor, $message);

        return $message;
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

    public function touchPresence(User $user): void
    {
        Cache::put(
            $this->presenceCacheKey((string) $user->id),
            now()->toIso8601String(),
            self::PRESENCE_TTL_SECONDS,
        );
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
        $this->touchPresence($user);

        Cache::put(
            $this->typingCacheKey((string) $conversation->id, (string) $user->id),
            [
                'name' => (string) $user->name,
                'at' => now()->toIso8601String(),
            ],
            5,
        );
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

        $message->load(['user:id,name', 'replyTo.user:id,name', 'attachments', 'reactions']);
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

        $message->load(['user:id,name', 'replyTo.user:id,name', 'attachments', 'reactions']);
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

        $message->load(['user:id,name', 'replyTo.user:id,name', 'attachments', 'reactions']);
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
        $this->assertParticipant($conversation, $actor);

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
        $this->assertParticipant($conversation, $actor);

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
        $this->assertParticipant($conversation, $actor);

        $q = trim($q);
        if ($q === '') {
            return [];
        }

        $like = '%'.addcslashes($q, '%_\\').'%';

        $messages = ChatMessage::query()
            ->where('conversation_id', $conversation->id)
            ->whereNotNull('body')
            ->where('body', 'ilike', $like)
            ->when(
                Schema::hasColumn('chat_messages', 'deleted_at'),
                static fn ($q) => $q->whereNull('deleted_at'),
            )
            ->with([
                'user:id,name',
                'replyTo.user:id,name',
                'attachments',
                'reactions',
            ])
            ->orderByDesc('created_at')
            ->limit(40)
            ->get()
            ->sortBy('created_at')
            ->values();

        return $messages
            ->map(fn (ChatMessage $m): array => $this->serializeMessage($m, $actor, $conversation))
            ->all();
    }

    /**
     * @return list<array{user_id: string, name: string, read_at: ?string}>
     */
    public function readersForMessage(ChatMessage $message, ChatConversation $conversation): array
    {
        $readers = ChatParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $message->user_id)
            ->whereNotNull('last_read_at')
            ->where('last_read_at', '>=', $message->created_at)
            ->with('user:id,name')
            ->get();

        return $readers
            ->map(static fn (ChatParticipant $p): array => [
                'user_id' => (string) $p->user_id,
                'name' => (string) ($p->user?->name ?? 'Usuario'),
                'read_at' => $p->last_read_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeMessage(ChatMessage $message, User $actor, ChatConversation $conversation): array
    {
        if (! $message->relationLoaded('user')) {
            $message->load('user:id,name');
        }
        if (! $message->relationLoaded('replyTo')) {
            $message->load('replyTo.user:id,name');
        }
        if (! $message->relationLoaded('attachments') && Schema::hasTable('chat_message_attachments')) {
            $message->load('attachments');
        }
        if (! $message->relationLoaded('reactions') && Schema::hasTable('chat_message_reactions')) {
            $message->load('reactions');
        }

        $isDeleted = Schema::hasColumn('chat_messages', 'deleted_at') && $message->deleted_at !== null;
        $attachments = $isDeleted ? [] : $this->serializeAttachments($message);
        $legacy = $attachments[0] ?? null;

        $mentionIds = collect($message->mentioned_user_ids ?? [])
            ->map(static fn ($id): string => (string) $id)
            ->filter()
            ->values();

        $mentionUsers = $mentionIds->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $mentionIds->all())->get(['id', 'name'])->keyBy('id');

        $mentions = $mentionIds
            ->map(static fn (string $id): array => [
                'id' => $id,
                'name' => (string) ($mentionUsers->get($id)?->name ?? 'Usuario'),
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

        return [
            'id' => (string) $message->id,
            'body' => $isDeleted ? '' : ($message->body !== null ? (string) $message->body : ''),
            'user_id' => (string) $message->user_id,
            'user_name' => (string) ($message->user?->name ?? 'Usuario'),
            'created_at' => $message->created_at?->toIso8601String(),
            'edited_at' => Schema::hasColumn('chat_messages', 'edited_at')
                ? $message->edited_at?->toIso8601String()
                : null,
            'deleted' => $isDeleted,
            'is_deleted' => $isDeleted,
            'deleted_at' => $isDeleted ? $message->deleted_at?->toIso8601String() : null,
            'mine' => (string) $message->user_id === (string) $actor->id,
            'reply_to_id' => $message->reply_to_id !== null ? (string) $message->reply_to_id : null,
            'reply_to' => $replyPreview,
            'mentioned_user_ids' => $mentionIds->all(),
            'mentions' => $mentions,
            'attachment' => $legacy,
            'attachments' => $attachments,
            'reactions' => $this->serializeReactions($message, $actor),
            'read_by' => $this->readersForMessage($message, $conversation),
        ];
    }

    /**
     * @return list<array{emoji: string, count: int, mine: bool, user_ids: list<string>}>
     */
    private function serializeReactions(ChatMessage $message, User $actor): array
    {
        if (! Schema::hasTable('chat_message_reactions')) {
            return [];
        }

        $reactions = $message->relationLoaded('reactions')
            ? $message->reactions
            : $message->reactions()->get();

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
                    'user_ids' => $userIds,
                ];
            })
            ->values()
            ->all();
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
     *         created_at: ?string
     *     }
     * }
     */
    public function inboxPing(User $actor): array
    {
        if (! Schema::hasTable('chat_messages')) {
            return ['unread_total' => 0, 'latest' => null];
        }

        $unread = $this->unreadTotalFor($actor);

        $row = DB::table('chat_messages as m')
            ->join('chat_participants as p', function ($join) use ($actor): void {
                $join->on('p.conversation_id', '=', 'm.conversation_id')
                    ->where('p.user_id', '=', $actor->id);
            })
            ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.user_id', '!=', $actor->id)
            ->when(
                Schema::hasColumn('chat_messages', 'deleted_at'),
                static fn ($q) => $q->whereNull('m.deleted_at'),
            )
            ->where(function ($q): void {
                $q->whereNull('p.last_read_at')
                    ->orWhereColumn('m.created_at', '>', 'p.last_read_at');
            })
            ->when(
                Schema::hasColumn('chat_participants', 'muted_at'),
                static fn ($q) => $q->whereNull('p.muted_at'),
            )
            ->orderByDesc('m.created_at')
            ->select([
                'm.id',
                'm.conversation_id',
                'm.body',
                'm.created_at',
                'u.name as user_name',
            ])
            ->when(
                Schema::hasColumn('chat_messages', 'attachment_name'),
                static fn ($q) => $q->addSelect([
                    'm.attachment_name',
                    'm.attachment_mime',
                ]),
            )
            ->first();

        if ($row === null) {
            return ['unread_total' => $unread, 'latest' => null];
        }

        $preview = $this->previewFromRow(
            isset($row->body) ? (string) $row->body : null,
            isset($row->attachment_name) ? (string) $row->attachment_name : null,
            isset($row->attachment_mime) ? (string) $row->attachment_mime : null,
        );

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
            ],
        ];
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

    public function markRead(ChatConversation $conversation, User $actor): void
    {
        $this->assertParticipant($conversation, $actor);

        ChatParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $actor->id)
            ->update(['last_read_at' => now()]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listConversationsPayload(User $actor): array
    {
        $conversationIds = ChatParticipant::query()
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

        $payload = [];
        foreach ($conversations as $conversation) {
            /** @var ?ChatParticipant $mine */
            $mine = $myReads->get($conversation->id);
            $muted = Schema::hasColumn('chat_participants', 'muted_at')
                && $mine?->muted_at !== null;
            $pinned = Schema::hasColumn('chat_participants', 'pinned_at')
                && $mine?->pinned_at !== null;

            $payload[] = $this->serializeConversationSummary(
                $conversation,
                $actor,
                (int) ($unreadCounts[$conversation->id] ?? 0),
                $muted,
                $pinned,
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

        foreach ($conversationIds as $cid) {
            /** @var ?ChatParticipant $part */
            $part = $myReads->get($cid);
            $since = $part?->last_read_at;

            $q = ChatMessage::query()
                ->where('conversation_id', $cid)
                ->where('user_id', '!=', $actor->id)
                ->when(
                    Schema::hasColumn('chat_messages', 'deleted_at'),
                    static fn ($query) => $query->whereNull('deleted_at'),
                );

            if ($since !== null) {
                $q->where('created_at', '>', $since);
            }

            $counts[$cid] = (int) $q->count();
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeConversationSummary(
        ChatConversation $conversation,
        User $actor,
        int $unread,
        ?bool $muted = null,
        ?bool $pinned = null,
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
            $muted = Schema::hasColumn('chat_participants', 'muted_at')
                && $mine?->muted_at !== null;
        }

        if ($pinned === null) {
            $pinned = Schema::hasColumn('chat_participants', 'pinned_at')
                && $mine?->pinned_at !== null;
        }

        $presence = null;
        if ($conversation->isDirect()) {
            $peer = $conversation->participants
                ->first(static fn (ChatParticipant $p): bool => (string) $p->user_id !== (string) $actor->id);
            if ($peer !== null) {
                $presenceMap = $this->presenceForUsers([(string) $peer->user_id]);
                $presence = $presenceMap[(string) $peer->user_id] ?? null;
            }
        }

        return [
            'id' => (string) $conversation->id,
            'type' => $conversation->type,
            'title' => $this->titleFor($conversation, $actor),
            'name' => $conversation->name,
            'participants' => $participants,
            'participant_count' => count($participants),
            'unread' => $unread,
            'muted' => (bool) $muted,
            'pinned' => (bool) $pinned,
            'presence' => $presence,
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
        $with = ['user:id,name', 'replyTo.user:id,name'];
        if (Schema::hasTable('chat_message_attachments')) {
            $with[] = 'attachments';
        }
        if (Schema::hasTable('chat_message_reactions')) {
            $with[] = 'reactions';
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

        return $q->get()
            ->sortBy('created_at')
            ->values()
            ->map(fn (ChatMessage $m): array => $this->serializeMessage($m, $actor, $conversation))
            ->all();
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

    private function dispatchMessageCreated(
        ChatConversation $conversation,
        User $actor,
        ChatMessage $message,
    ): void {
        try {
            $tenantId = app(TenantManager::class)->id();
            if ($tenantId === null || $tenantId === '') {
                return;
            }

            $serialized = $this->serializeMessage($message, $actor, $conversation);
            $preview = $this->previewFromRow(
                $message->body,
                $message->attachment_name,
                $message->attachment_mime,
            );

            event(new ChatMessageCreated(
                (string) $tenantId,
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
            $tenantId = app(TenantManager::class)->id();
            if ($tenantId === null || $tenantId === '') {
                return;
            }

            $serialized = $this->serializeMessage($message, $actor, $conversation);

            event(new ChatMessageUpdated(
                (string) $tenantId,
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
            $participantsQuery = ChatParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', '!=', $actor->id);

            if (Schema::hasColumn('chat_participants', 'muted_at')) {
                $participantsQuery->whereNull('muted_at');
            }

            $userIds = $participantsQuery->pluck('user_id')->all();
            if ($userIds === []) {
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

            app(WebPushSender::class)->sendToUsers($userIds, [
                'title' => $title,
                'body' => ((string) ($actor->name ?: 'Usuario')).': '.$preview,
                'url' => '/comunicaciones/chat?c='.$conversation->id,
                'tag' => 'chat-'.$conversation->id,
            ]);
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
