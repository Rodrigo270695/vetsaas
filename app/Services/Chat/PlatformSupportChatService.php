<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Plan;
use App\Models\PlatformSupportThread;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class PlatformSupportChatService
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly TenantChatService $chat,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listTenants(?string $planFilter = 'all', ?string $search = null): array
    {
        $planFilter = in_array($planFilter, ['all', 'free', 'paid'], true) ? $planFilter : 'all';
        $search = trim((string) $search);

        $threads = PlatformSupportThread::query()
            ->with(['assignedAgent:id,name'])
            ->get()
            ->keyBy(static fn (PlatformSupportThread $t): string => (string) $t->tenant_id);

        $tenants = Tenant::query()
            ->whereIn('estado', ['trial', 'active', 'grace', 'suspended'])
            ->with(['subscriptions' => static function ($q): void {
                $q->whereIn('estado', ['trial', 'active', 'grace'])
                    ->latest()
                    ->with('plan:id,codigo,nombre');
            }])
            ->orderBy('nombre_comercial')
            ->orderBy('razon_social')
            ->get();

        $rows = [];
        foreach ($tenants as $tenant) {
            $sub = $tenant->subscriptions->first();
            $plan = $sub?->plan;
            $planCodigo = is_string($plan?->codigo) ? $plan->codigo : null;
            $isFree = $planCodigo === Plan::CODIGO_FREE;

            if ($planFilter === 'free' && ! $isFree) {
                continue;
            }
            if ($planFilter === 'paid' && ($planCodigo === null || $isFree)) {
                continue;
            }

            $nombre = trim((string) ($tenant->nombre_comercial ?: $tenant->razon_social ?: $tenant->slug));
            if ($search !== '') {
                $hay = mb_strtolower($nombre.' '.$tenant->slug.' '.(string) $tenant->ruc);
                if (! str_contains($hay, mb_strtolower($search))) {
                    continue;
                }
            }

            $thread = $threads->get((string) $tenant->id);
            $waiting = $thread?->waitingMinutes();
            $assigned = $thread?->assignedAgent;

            $rows[] = [
                'id' => (string) $tenant->id,
                'slug' => (string) $tenant->slug,
                'nombre' => $nombre,
                'estado' => (string) $tenant->estado,
                'plan_codigo' => $planCodigo,
                'plan_nombre' => is_string($plan?->nombre) ? $plan->nombre : null,
                'is_free' => $planCodigo !== null ? $isFree : null,
                'is_vip' => $planCodigo !== null && ! $isFree,
                'thread' => $thread === null ? null : [
                    'conversation_id' => (string) $thread->conversation_id,
                    'last_message_at' => $thread->last_message_at?->toIso8601String(),
                    'last_preview' => $thread->last_preview,
                    'unread' => $thread->isUnreadForPlatform(),
                    'from_clinic' => (bool) $thread->from_clinic,
                    'needs_response' => (bool) $thread->from_clinic,
                    'muted' => $thread->isMuted(),
                    'waiting_minutes' => $waiting,
                    'sla_label' => $waiting === null
                        ? null
                        : ($waiting >= 60
                            ? intdiv($waiting, 60).'h '.($waiting % 60).'m'
                            : $waiting.'m'),
                    'assigned_agent_id' => $thread->assigned_agent_id !== null
                        ? (string) $thread->assigned_agent_id
                        : null,
                    'assigned_agent_name' => $assigned !== null
                        ? (string) $assigned->name
                        : null,
                ],
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $ua = ($a['thread']['unread'] ?? false) ? 1 : 0;
            $ub = ($b['thread']['unread'] ?? false) ? 1 : 0;
            if ($ua !== $ub) {
                return $ub <=> $ua;
            }

            $ta = $a['thread']['last_message_at'] ?? null;
            $tb = $b['thread']['last_message_at'] ?? null;
            if ($ta !== null || $tb !== null) {
                return strcmp((string) $tb, (string) $ta);
            }

            return strcmp((string) $a['nombre'], (string) $b['nombre']);
        });

        return $rows;
    }

    public function unreadTotal(): int
    {
        if (! Schema::hasColumn('platform_support_threads', 'from_clinic')) {
            return 0;
        }

        return PlatformSupportThread::query()
            ->where('from_clinic', true)
            ->whereNotNull('last_message_at')
            ->when(
                Schema::hasColumn('platform_support_threads', 'muted_at'),
                static fn ($q) => $q->whereNull('muted_at'),
            )
            ->where(function ($q): void {
                $q->whereNull('platform_last_read_at')
                    ->orWhereColumn('last_message_at', '>', 'platform_last_read_at');
            })
            ->count();
    }

    /**
     * @return array{
     *     unread_total: int,
     *     latest: ?array{
     *         tenant_id: string,
     *         tenant_nombre: string,
     *         preview: string,
     *         last_message_at: ?string
     *     }
     * }
     */
    public function inboxPing(): array
    {
        $unread = $this->unreadTotal();

        if (! Schema::hasColumn('platform_support_threads', 'from_clinic')) {
            return ['unread_total' => 0, 'latest' => null];
        }

        $latest = PlatformSupportThread::query()
            ->where('from_clinic', true)
            ->whereNotNull('last_message_at')
            ->when(
                Schema::hasColumn('platform_support_threads', 'muted_at'),
                static fn ($q) => $q->whereNull('muted_at'),
            )
            ->where(function ($q): void {
                $q->whereNull('platform_last_read_at')
                    ->orWhereColumn('last_message_at', '>', 'platform_last_read_at');
            })
            ->with(['tenant:id,nombre_comercial,razon_social,slug'])
            ->orderByDesc('last_message_at')
            ->first();

        if ($latest === null) {
            return ['unread_total' => $unread, 'latest' => null];
        }

        $tenant = $latest->tenant;
        $nombre = trim((string) ($tenant?->nombre_comercial ?: $tenant?->razon_social ?: $tenant?->slug ?: 'Clínica'));

        return [
            'unread_total' => $unread,
            'latest' => [
                'tenant_id' => (string) $latest->tenant_id,
                'tenant_nombre' => $nombre,
                'preview' => (string) ($latest->last_preview ?: 'Nuevo mensaje'),
                'last_message_at' => $latest->last_message_at?->toIso8601String(),
                'fingerprint' => (string) $latest->tenant_id.'|'.($latest->last_message_at?->timestamp ?? 0),
            ],
        ];
    }

    public function markThreadRead(Tenant $tenant): void
    {
        if (! Schema::hasColumn('platform_support_threads', 'platform_last_read_at')) {
            return;
        }

        PlatformSupportThread::query()
            ->where('tenant_id', $tenant->id)
            ->update([
                'platform_last_read_at' => now(),
            ]);
    }

    /**
     * @return array{
     *     tenant_id: string,
     *     conversation_id: string,
     *     support_user_id: string,
     *     created: bool
     * }
     */
    public function ensureThread(Tenant $tenant): array
    {
        $created = ! PlatformSupportThread::query()
            ->where('tenant_id', $tenant->id)
            ->exists();

        $result = $this->tenants->runForTenant($tenant, function () use ($tenant): array {
            $supportUser = $this->ensureSupportUser($tenant);
            $adminIds = $this->adminUserIds($tenant);

            if ($adminIds === []) {
                throw new RuntimeException(
                    'La clínica no tiene un admin_clinica activo. Crea uno antes de abrir Soporte VetSaaS.',
                );
            }

            $conversation = $this->chat->ensureSupportGroup($supportUser, $adminIds);

            return [
                'conversation_id' => (string) $conversation->id,
                'support_user_id' => (string) $supportUser->id,
            ];
        }, enforceAccess: false);

        PlatformSupportThread::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'conversation_id' => $result['conversation_id'],
                'support_user_id' => $result['support_user_id'],
            ],
        );

        return [
            'tenant_id' => (string) $tenant->id,
            'conversation_id' => $result['conversation_id'],
            'support_user_id' => $result['support_user_id'],
            'created' => $created,
        ];
    }

    /**
     * @param  UploadedFile|list<UploadedFile>|null  $attachment
     * @return array{message: array<string, mixed>, conversation_id: string}
     */
    public function sendToTenant(
        Tenant $tenant,
        ?string $body,
        ?User $platformActor = null,
        UploadedFile|array|null $attachment = null,
        ?string $replyToId = null,
    ): array {
        $body = trim((string) $body);
        $files = is_array($attachment)
            ? array_values(array_filter($attachment, static fn ($f): bool => $f instanceof UploadedFile))
            : ($attachment instanceof UploadedFile ? [$attachment] : []);

        if ($body === '' && $files === []) {
            throw ValidationException::withMessages([
                'body' => __('Escribe un mensaje o adjunta un archivo.'),
            ]);
        }

        if (mb_strlen($body) > 4000) {
            throw ValidationException::withMessages([
                'body' => __('El mensaje es demasiado largo.'),
            ]);
        }

        $this->ensureThread($tenant);

        $payload = $this->tenants->runForTenant($tenant, function () use ($tenant, $body, $platformActor, $files, $replyToId): array {
            $supportUser = $this->ensureSupportUser($tenant);
            $adminIds = $this->adminUserIds($tenant);
            $conversation = $this->chat->ensureSupportGroup($supportUser, $adminIds);

            $prefix = '';
            if ($platformActor !== null) {
                $actorName = trim((string) $platformActor->name);
                if ($actorName !== '') {
                    $prefix = '['.$actorName.'] ';
                }
            }

            $message = $this->chat->sendMessage(
                $conversation,
                $supportUser,
                $body === '' ? '' : $prefix.$body,
                $files === [] ? null : $files,
                (string) $tenant->slug,
                $replyToId,
            );

            $serialized = $this->chat->serializeMessage($message, $supportUser, $conversation);
            $previewBody = $body === ''
                ? (string) ($serialized['body'] ?? '📎')
                : mb_substr($prefix.$body, 0, 280);

            return [
                'conversation_id' => (string) $conversation->id,
                'message' => $serialized,
                'preview' => mb_substr($previewBody, 0, 280),
                'at' => $message->created_at,
            ];
        }, enforceAccess: false);

        $update = [
            'conversation_id' => $payload['conversation_id'],
            'last_message_at' => $payload['at'],
            'last_preview' => $payload['preview'],
        ];
        if (Schema::hasColumn('platform_support_threads', 'from_clinic')) {
            $update['from_clinic'] = false;
        }
        if (Schema::hasColumn('platform_support_threads', 'platform_last_read_at')) {
            $update['platform_last_read_at'] = now();
        }
        if (Schema::hasColumn('platform_support_threads', 'clinic_waiting_since')) {
            $update['clinic_waiting_since'] = null;
        }
        if (Schema::hasColumn('platform_support_threads', 'first_response_at')) {
            $existing = PlatformSupportThread::query()->where('tenant_id', $tenant->id)->first();
            if ($existing !== null && $existing->first_response_at === null && $existing->clinic_waiting_since !== null) {
                $update['first_response_at'] = now();
            }
        }

        PlatformSupportThread::query()
            ->where('tenant_id', $tenant->id)
            ->update($update);

        return [
            'message' => $payload['message'],
            'conversation_id' => $payload['conversation_id'],
        ];
    }

    /**
     * @return array{sent: int, failed: list<array{tenant_id: string, slug: string, error: string}>}
     */
    public function broadcast(string $body, string $planFilter = 'all', ?User $platformActor = null): array
    {
        $rows = $this->listTenants($planFilter);
        $sent = 0;
        $failed = [];

        foreach ($rows as $row) {
            $tenant = Tenant::query()->find($row['id']);
            if (! $tenant instanceof Tenant) {
                continue;
            }

            try {
                $this->sendToTenant($tenant, $body, $platformActor);
                $sent++;
            } catch (\Throwable $e) {
                $failed[] = [
                    'tenant_id' => (string) $tenant->id,
                    'slug' => (string) $tenant->slug,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * @return array{conversation_id: string|null, messages: list<array<string, mixed>>}
     */
    public function messagesForTenant(Tenant $tenant, ?string $afterId = null, int $limit = 80): array
    {
        $thread = PlatformSupportThread::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($thread === null) {
            return [
                'conversation_id' => null,
                'support_user_id' => null,
                'tenant_id' => (string) $tenant->id,
                'typing' => [],
                'messages' => [],
            ];
        }

        return $this->tenants->runForTenant($tenant, function () use ($tenant, $thread, $afterId, $limit): array {
            $supportUser = User::query()->find($thread->support_user_id);
            $conversation = ChatConversation::query()->find($thread->conversation_id);

            if ($conversation === null || $supportUser === null) {
                return [
                    'conversation_id' => null,
                    'support_user_id' => null,
                    'tenant_id' => (string) $tenant->id,
                    'typing' => [],
                    'messages' => [],
                ];
            }

            $limit = max(1, min(200, $limit));

            if (is_string($afterId) && $afterId !== '') {
                $anchor = ChatMessage::query()->find($afterId);
                $q = ChatMessage::query()
                    ->where('conversation_id', $conversation->id)
                    ->with(['user:id,name', 'replyTo.user:id,name'])
                    ->orderBy('created_at')
                    ->orderBy('id');

                if ($anchor !== null) {
                    $q->where(function ($inner) use ($anchor): void {
                        $inner->where('created_at', '>', $anchor->created_at)
                            ->orWhere(function ($same) use ($anchor): void {
                                $same->where('created_at', '=', $anchor->created_at)
                                    ->where('id', '>', $anchor->id);
                            });
                    });
                }

                $messages = $q->limit($limit)->get();
            } else {
                $messages = ChatMessage::query()
                    ->where('conversation_id', $conversation->id)
                    ->with(['user:id,name', 'replyTo.user:id,name'])
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit($limit)
                    ->get()
                    ->reverse()
                    ->values();
            }

            return [
                'conversation_id' => (string) $conversation->id,
                'support_user_id' => (string) $supportUser->id,
                'tenant_id' => (string) $tenant->id,
                'typing' => $this->chat->typingPayload($conversation, $supportUser),
                'messages' => $messages
                    ->map(fn (ChatMessage $m): array => $this->chat->serializeMessage($m, $supportUser, $conversation))
                    ->values()
                    ->all(),
            ];
        }, enforceAccess: false);
    }

    /**
     * @return list<array{message_id: string, url: ?string, name: string, mime: string, size: int, created_at: ?string}>
     */
    public function mediaForTenant(Tenant $tenant): array
    {
        $this->ensureThread($tenant);

        return $this->tenants->runForTenant($tenant, function () use ($tenant): array {
            $supportUser = $this->ensureSupportUser($tenant);
            $adminIds = $this->adminUserIds($tenant);
            $conversation = $this->chat->ensureSupportGroup($supportUser, $adminIds);

            return $this->chat->mediaGallery($conversation, $supportUser);
        }, enforceAccess: false);
    }

    /**
     * @return list<array{user_id: string, name: string}>
     */
    public function typingForTenant(Tenant $tenant): array
    {
        $thread = PlatformSupportThread::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($thread === null) {
            return [];
        }

        return $this->tenants->runForTenant($tenant, function () use ($thread): array {
            $supportUser = User::query()->find($thread->support_user_id);
            $conversation = ChatConversation::query()->find($thread->conversation_id);
            if ($conversation === null || $supportUser === null) {
                return [];
            }

            return $this->chat->typingPayload($conversation, $supportUser);
        }, enforceAccess: false);
    }

    public function touchTyping(Tenant $tenant): void
    {
        $this->ensureThread($tenant);

        $this->tenants->runForTenant($tenant, function () use ($tenant): void {
            $supportUser = $this->ensureSupportUser($tenant);
            $adminIds = $this->adminUserIds($tenant);
            $conversation = $this->chat->ensureSupportGroup($supportUser, $adminIds);
            $this->chat->touchTyping($conversation, $supportUser);
        }, enforceAccess: false);
    }

    /**
     * @return array{message: array<string, mixed>, reactions: list<array<string, mixed>>, removed: bool}
     */
    public function react(Tenant $tenant, string $messageId, string $emoji): array
    {
        $this->ensureThread($tenant);

        return $this->tenants->runForTenant($tenant, function () use ($tenant, $messageId, $emoji): array {
            [$supportUser, , $message] = $this->resolveSupportMessage($tenant, $messageId);

            return $this->chat->toggleReaction($message, $supportUser, $emoji);
        }, enforceAccess: false);
    }

    /**
     * @return array{message: array<string, mixed>}
     */
    public function editMessage(Tenant $tenant, string $messageId, string $body, ?User $platformActor = null): array
    {
        $this->ensureThread($tenant);

        return $this->tenants->runForTenant($tenant, function () use ($tenant, $messageId, $body, $platformActor): array {
            [$supportUser, $conversation, $message] = $this->resolveSupportMessage($tenant, $messageId);

            $prefix = '';
            if ($platformActor !== null) {
                $actorName = trim((string) $platformActor->name);
                if ($actorName !== '') {
                    $prefix = '['.$actorName.'] ';
                }
            }

            $edited = $this->chat->editMessage($message, $supportUser, $prefix.trim($body));

            return [
                'message' => $this->chat->serializeMessage($edited, $supportUser, $conversation),
            ];
        }, enforceAccess: false);
    }

    /**
     * @return array{message: array<string, mixed>}
     */
    public function deleteMessage(Tenant $tenant, string $messageId): array
    {
        $this->ensureThread($tenant);

        return $this->tenants->runForTenant($tenant, function () use ($tenant, $messageId): array {
            [$supportUser, $conversation, $message] = $this->resolveSupportMessage($tenant, $messageId);

            $deleted = $this->chat->softDeleteMessage($message, $supportUser);

            return [
                'message' => $this->chat->serializeMessage($deleted, $supportUser, $conversation),
            ];
        }, enforceAccess: false);
    }

    /**
     * @return array{message: array<string, mixed>, conversation_id: string}
     */
    public function forwardMessage(Tenant $tenant, string $messageId, string $targetConversationId): array
    {
        $this->ensureThread($tenant);

        return $this->tenants->runForTenant($tenant, function () use ($tenant, $messageId, $targetConversationId): array {
            [$supportUser, $supportConversation, $message] = $this->resolveSupportMessage($tenant, $messageId);

            $target = ChatConversation::query()->findOrFail($targetConversationId);
            if ((string) $target->id === (string) $supportConversation->id) {
                throw ValidationException::withMessages([
                    'target_conversation_id' => __('Elige otro chat para reenviar.'),
                ]);
            }

            $exists = $target->participants()
                ->where('user_id', $supportUser->id)
                ->exists();
            if (! $exists) {
                $target->participants()->create([
                    'user_id' => $supportUser->id,
                    'last_read_at' => now(),
                ]);
            }

            $forwarded = $this->chat->forwardMessage($message, $supportUser, $target);

            return [
                'message' => $this->chat->serializeMessage($forwarded, $supportUser, $target),
                'conversation_id' => (string) $target->id,
            ];
        }, enforceAccess: false);
    }

    /**
     * @return list<array{id: string, title: string, type: string}>
     */
    public function forwardTargets(Tenant $tenant): array
    {
        $this->ensureThread($tenant);

        return $this->tenants->runForTenant($tenant, function () use ($tenant): array {
            $supportUser = $this->ensureSupportUser($tenant);
            $adminIds = $this->adminUserIds($tenant);
            $supportConversation = $this->chat->ensureSupportGroup($supportUser, $adminIds);

            $admin = $adminIds[0] ?? null;
            $actor = $admin !== null ? User::query()->find($admin) : null;
            if (! $actor instanceof User) {
                return [];
            }

            $conversations = $this->chat->listConversationsPayload($actor);

            $rows = [];
            foreach ($conversations as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = (string) ($row['id'] ?? '');
                if ($id === '' || $id === (string) $supportConversation->id) {
                    continue;
                }
                $rows[] = [
                    'id' => $id,
                    'title' => (string) ($row['title'] ?? $row['name'] ?? 'Chat'),
                    'type' => (string) ($row['type'] ?? 'group'),
                ];
            }

            return $rows;
        }, enforceAccess: false);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function messageContext(Tenant $tenant, string $messageId): array
    {
        $this->ensureThread($tenant);

        return $this->tenants->runForTenant($tenant, function () use ($tenant, $messageId): array {
            [$supportUser, $conversation, $message] = $this->resolveSupportMessage($tenant, $messageId);

            return $this->chat->messageContext($conversation, $supportUser, $message);
        }, enforceAccess: false);
    }

    public function assignAgent(Tenant $tenant, ?string $agentUserId): array
    {
        $this->ensureThread($tenant);

        if ($agentUserId !== null && $agentUserId !== '') {
            $agent = User::query()->find($agentUserId);
            if (! $agent instanceof User || (! $agent->isPlatformSuperadmin() && ! $agent->can('plataforma-chat-soporte.manage'))) {
                throw ValidationException::withMessages([
                    'assigned_agent_id' => __('Agente no válido.'),
                ]);
            }
        } else {
            $agentUserId = null;
        }

        PlatformSupportThread::query()
            ->where('tenant_id', $tenant->id)
            ->update(['assigned_agent_id' => $agentUserId]);

        $thread = PlatformSupportThread::query()
            ->with('assignedAgent:id,name')
            ->where('tenant_id', $tenant->id)
            ->first();

        return [
            'assigned_agent_id' => $thread?->assigned_agent_id !== null
                ? (string) $thread->assigned_agent_id
                : null,
            'assigned_agent_name' => $thread?->assignedAgent?->name,
        ];
    }

    public function setMuted(Tenant $tenant, bool $muted): array
    {
        $this->ensureThread($tenant);

        if (! Schema::hasColumn('platform_support_threads', 'muted_at')) {
            return ['muted' => false];
        }

        PlatformSupportThread::query()
            ->where('tenant_id', $tenant->id)
            ->update(['muted_at' => $muted ? now() : null]);

        return ['muted' => $muted];
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function assignableAgents(): array
    {
        return User::query()
            ->whereNull('tenant_id')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->filter(static function (User $u): bool {
                try {
                    return $u->isPlatformSuperadmin()
                        || $u->can('plataforma-chat-soporte.view');
                } catch (\Throwable) {
                    return false;
                }
            })
            ->map(static fn (User $u): array => [
                'id' => (string) $u->id,
                'name' => (string) $u->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, body: string, user_id: string, user_name: string, created_at: ?string}>
     */
    public function listNotes(Tenant $tenant): array
    {
        if (! Schema::hasTable('platform_support_notes')) {
            return [];
        }

        return \App\Models\PlatformSupportNote::query()
            ->where('tenant_id', $tenant->id)
            ->with('author:id,name')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(static fn (\App\Models\PlatformSupportNote $n): array => [
                'id' => (string) $n->id,
                'body' => (string) $n->body,
                'user_id' => (string) $n->user_id,
                'user_name' => (string) ($n->author?->name ?? 'Usuario'),
                'created_at' => $n->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array{id: string, body: string, user_id: string, user_name: string, created_at: ?string}
     */
    public function addNote(Tenant $tenant, User $actor, string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => __('Escribe una nota.'),
            ]);
        }

        $note = \App\Models\PlatformSupportNote::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $actor->id,
            'body' => mb_substr($body, 0, 4000),
        ]);
        $note->load('author:id,name');

        return [
            'id' => (string) $note->id,
            'body' => (string) $note->body,
            'user_id' => (string) $note->user_id,
            'user_name' => (string) ($note->author?->name ?? $actor->name),
            'created_at' => $note->created_at?->toIso8601String(),
        ];
    }

    public function deleteNote(string $noteId, User $actor): void
    {
        $note = \App\Models\PlatformSupportNote::query()->findOrFail($noteId);
        if ((string) $note->user_id !== (string) $actor->id && ! $actor->isPlatformSuperadmin()) {
            abort(403);
        }
        $note->delete();
    }

    /**
     * @return list<array{id: string, label: string, body: string, sort_order: int}>
     */
    public function listTemplates(bool $activeOnly = true): array
    {
        if (! Schema::hasTable('platform_support_templates')) {
            return $this->builtinTemplates();
        }

        $q = \App\Models\PlatformSupportTemplate::query()->orderBy('sort_order')->orderBy('label');
        if ($activeOnly) {
            $q->where('is_active', true);
        }

        $rows = $q->get();
        if ($rows->isEmpty()) {
            return $this->builtinTemplates();
        }

        return $rows->map(static fn (\App\Models\PlatformSupportTemplate $t): array => [
            'id' => (string) $t->id,
            'label' => (string) $t->label,
            'body' => (string) $t->body,
            'sort_order' => (int) $t->sort_order,
            'is_active' => (bool) $t->is_active,
        ])->all();
    }

    /**
     * @return array{id: string, label: string, body: string, sort_order: int, is_active: bool}
     */
    public function upsertTemplate(?string $id, string $label, string $body, ?User $actor = null, ?int $sortOrder = null, bool $isActive = true): array
    {
        $label = trim($label);
        $body = trim($body);
        if ($label === '' || $body === '') {
            throw ValidationException::withMessages([
                'label' => __('Completa etiqueta y texto.'),
            ]);
        }

        $template = $id
            ? \App\Models\PlatformSupportTemplate::query()->findOrFail($id)
            : new \App\Models\PlatformSupportTemplate;

        $template->fill([
            'label' => mb_substr($label, 0, 120),
            'body' => mb_substr($body, 0, 4000),
            'sort_order' => $sortOrder ?? (int) ($template->sort_order ?? 0),
            'is_active' => $isActive,
            'created_by' => $template->exists ? $template->created_by : $actor?->id,
        ]);
        $template->save();

        return [
            'id' => (string) $template->id,
            'label' => (string) $template->label,
            'body' => (string) $template->body,
            'sort_order' => (int) $template->sort_order,
            'is_active' => (bool) $template->is_active,
        ];
    }

    public function deleteTemplate(string $id): void
    {
        \App\Models\PlatformSupportTemplate::query()->whereKey($id)->delete();
    }

    /**
     * @return list<array{id: string, label: string, body: string, sort_order: int, is_active: bool}>
     */
    private function builtinTemplates(): array
    {
        return [
            [
                'id' => 'builtin-whatsapp',
                'label' => 'Reconectar WhatsApp',
                'body' => 'Hola. Actualizamos WhatsApp. Entra a Cola saliente, desvincula y vuelve a escanear el QR para reactivar el envío.',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'id' => 'builtin-maintenance',
                'label' => 'Mantenimiento',
                'body' => 'Aviso: tendremos una ventana de mantenimiento breve. El servicio puede interrumpirse unos minutos. Gracias por tu paciencia.',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'id' => 'builtin-billing',
                'label' => 'Facturación / plan',
                'body' => 'Hola. Revisamos tu plan/suscripción. Si tienes dudas de facturación o activación de módulos, responde aquí y te ayudamos.',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'id' => 'builtin-greeting',
                'label' => 'Saludo de soporte',
                'body' => 'Hola, soy del equipo de Soporte VetSaaS. ¿En qué podemos ayudarte hoy?',
                'sort_order' => 4,
                'is_active' => true,
            ],
        ];
    }

    /**
     * @return array{0: User, 1: ChatConversation, 2: ChatMessage}
     */
    private function resolveSupportMessage(Tenant $tenant, string $messageId): array
    {
        $supportUser = $this->ensureSupportUser($tenant);
        $adminIds = $this->adminUserIds($tenant);
        $conversation = $this->chat->ensureSupportGroup($supportUser, $adminIds);

        $message = ChatMessage::query()->find($messageId);
        if (! $message instanceof ChatMessage) {
            abort(404);
        }

        if ((string) $message->conversation_id !== (string) $conversation->id) {
            abort(404);
        }

        return [$supportUser, $conversation, $message];
    }

    private function ensureSupportUser(Tenant $tenant): User
    {
        $email = 'soporte+'.$tenant->slug.'@'.TenantChatService::SUPPORT_EMAIL_DOMAIN;

        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->first();

        if ($user instanceof User) {
            if (! $user->is_active) {
                $user->forceFill(['is_active' => true])->save();
            }

            return $user;
        }

        return User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => TenantChatService::SUPPORT_GROUP_NAME,
            'email' => $email,
            'password' => Hash::make(Str::password(48)),
            'is_active' => true,
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function adminUserIds(Tenant $tenant): array
    {
        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId((string) $tenant->id);

        try {
            /** @var Collection<int, User> $admins */
            $admins = User::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->where('email', 'not like', '%@'.TenantChatService::SUPPORT_EMAIL_DOMAIN)
                ->role('admin_clinica')
                ->orderBy('created_at')
                ->get(['id']);

            return $admins->map(static fn (User $u): string => (string) $u->id)->values()->all();
        } finally {
            setPermissionsTeamId($previousTeam);
        }
    }
}
