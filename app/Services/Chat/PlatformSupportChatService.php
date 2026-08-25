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

            $rows[] = [
                'id' => (string) $tenant->id,
                'slug' => (string) $tenant->slug,
                'nombre' => $nombre,
                'estado' => (string) $tenant->estado,
                'plan_codigo' => $planCodigo,
                'plan_nombre' => is_string($plan?->nombre) ? $plan->nombre : null,
                'is_free' => $planCodigo !== null ? $isFree : null,
                'thread' => $thread === null ? null : [
                    'conversation_id' => (string) $thread->conversation_id,
                    'last_message_at' => $thread->last_message_at?->toIso8601String(),
                    'last_preview' => $thread->last_preview,
                    'unread' => $thread->isUnreadForPlatform(),
                    'from_clinic' => (bool) $thread->from_clinic,
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
            return ['conversation_id' => null, 'messages' => []];
        }

        return $this->tenants->runForTenant($tenant, function () use ($thread, $afterId, $limit): array {
            $supportUser = User::query()->find($thread->support_user_id);
            $conversation = ChatConversation::query()->find($thread->conversation_id);

            if ($conversation === null || $supportUser === null) {
                return ['conversation_id' => null, 'messages' => []];
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
                'messages' => $messages
                    ->map(fn (ChatMessage $m): array => $this->chat->serializeMessage($m, $supportUser, $conversation))
                    ->values()
                    ->all(),
            ];
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
