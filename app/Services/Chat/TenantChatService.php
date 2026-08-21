<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Models\User;
use App\Support\Tenancy\ClinicAdminScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TenantChatService
{
    public const MESSAGES_PAGE = 80;

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

    public function sendMessage(ChatConversation $conversation, User $actor, string $body): ChatMessage
    {
        $this->assertParticipant($conversation, $actor);

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

        $message = ChatMessage::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $actor->id,
            'body' => $body,
            'created_at' => now(),
        ]);

        ChatParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $actor->id)
            ->update(['last_read_at' => now()]);

        $conversation->touch();

        return $message;
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
            $payload[] = $this->serializeConversationSummary(
                $conversation,
                $actor,
                (int) ($unreadCounts[$conversation->id] ?? 0),
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
                ->where('user_id', '!=', $actor->id);

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
    ): array {
        $last = $conversation->messages->first();
        $participants = $conversation->participants
            ->map(static fn (ChatParticipant $p): array => [
                'id' => (string) $p->user_id,
                'name' => (string) ($p->user?->name ?? 'Usuario'),
            ])
            ->values()
            ->all();

        return [
            'id' => (string) $conversation->id,
            'type' => $conversation->type,
            'title' => $this->titleFor($conversation, $actor),
            'name' => $conversation->name,
            'participants' => $participants,
            'participant_count' => count($participants),
            'unread' => $unread,
            'last_message' => $last === null ? null : [
                'body' => (string) $last->body,
                'user_name' => (string) ($last->user?->name ?? ''),
                'created_at' => $last->created_at?->toIso8601String(),
                'mine' => (string) $last->user_id === (string) $actor->id,
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
    public function messagesPayload(ChatConversation $conversation, ?string $beforeId = null): array
    {
        $q = ChatMessage::query()
            ->where('conversation_id', $conversation->id)
            ->with('user:id,name')
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
            ->map(static fn (ChatMessage $m): array => [
                'id' => (string) $m->id,
                'body' => (string) $m->body,
                'user_id' => (string) $m->user_id,
                'user_name' => (string) ($m->user?->name ?? 'Usuario'),
                'created_at' => $m->created_at?->toIso8601String(),
            ])
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

        return [
            ...$summary,
            'messages' => $this->messagesPayload($conversation),
        ];
    }
}
