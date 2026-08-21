<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatDirectRequest;
use App\Http\Requests\StoreChatGroupRequest;
use App\Http\Requests\StoreChatMessageRequest;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ClinicSetting;
use App\Services\Chat\TenantChatService;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class TenantChatController extends Controller
{
    public function __construct(
        private readonly TenantChatService $chat,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $conversationId = trim((string) $request->query('c', ''));
        $focusMessageId = trim((string) $request->query('m', ''));
        $draft = trim((string) $request->query('draft', ''));
        $notify = trim((string) $request->query('notify', ''));

        if ($notify === 'caja' && $draft !== '') {
            $conversation = $this->chat->notifyTeam($user, $draft, 'Caja');

            return redirect()->route('comunicaciones.chat', ['c' => $conversation->id]);
        }

        $conversations = $this->chat->listConversationsPayload($user);
        $users = $this->chat->directoryUsers((string) $user->id)
            ->map(static fn ($u): array => [
                'id' => (string) $u->id,
                'name' => (string) $u->name,
                'email' => (string) ($u->email ?? ''),
            ])
            ->values()
            ->all();

        $active = null;
        if ($conversationId !== '') {
            $conversation = ChatConversation::query()->find($conversationId);
            if ($conversation !== null) {
                $this->chat->assertParticipant($conversation, $user);
                $this->chat->setViewing($user, (string) $conversation->id);
                $this->chat->markRead($conversation, $user);
                $active = $this->chat->activePayload($conversation, $user);
                // Refrescar solo unread/muted del listado tras markRead (sin second full scan costoso).
                foreach ($conversations as $i => $row) {
                    if ((string) ($row['id'] ?? '') === (string) $conversation->id) {
                        $conversations[$i]['unread'] = 0;
                        break;
                    }
                }
            }
        }

        $retentionDays = null;
        if (Schema::hasColumn('cfg_clinic_settings', 'chat_retention_days')) {
            $retentionDays = ClinicSetting::query()->value('chat_retention_days');
            $retentionDays = $retentionDays !== null ? (int) $retentionDays : null;
        }

        return Inertia::render('comunicaciones/chat/index', [
            'conversations' => $conversations,
            'users' => $users,
            'active' => $active,
            'focus_message_id' => $focusMessageId !== '' ? $focusMessageId : null,
            'unread_total' => $this->chat->unreadTotalFor($user),
            'can_manage' => $user->can('comunicaciones-chat.manage'),
            'can_create_groups' => $user->can('comunicaciones-chat.manage'),
            'draft' => $draft !== '' ? $draft : null,
            'retention_days' => $retentionDays,
            'poll_ms' => 4_000,
            'broadcast' => [
                'enabled' => filled(config('broadcasting.connections.reverb.key'))
                    && config('broadcasting.default') === 'reverb',
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => config('broadcasting.connections.reverb.options.host') ?: 'localhost',
                'port' => (int) (config('broadcasting.connections.reverb.options.port') ?: 8080),
                'scheme' => (string) (config('broadcasting.connections.reverb.options.scheme') ?: 'http'),
            ],
        ]);
    }

    public function inbox(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->can('comunicaciones-chat.view'), 403);

        return response()->json($this->chat->inboxPing($user));
    }

    public function storeDirect(StoreChatDirectRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $conversation = $this->chat->findOrCreateDirect(
            $user,
            (string) $request->validated('user_id'),
        );

        $draft = trim((string) $request->input('draft', ''));
        $params = ['c' => $conversation->id];
        if ($draft !== '') {
            $params['draft'] = $draft;
        }

        return redirect()->route('comunicaciones.chat', $params);
    }

    public function storeGroup(StoreChatGroupRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->can('comunicaciones-chat.manage'), 403);

        $data = $request->validated();
        $conversation = $this->chat->createGroup(
            $user,
            (string) $data['name'],
            array_values($data['user_ids'] ?? []),
        );

        return redirect()->route('comunicaciones.chat', ['c' => $conversation->id]);
    }

    public function notifyTeam(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->can('comunicaciones-chat.view'), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
            'group' => ['nullable', 'string', 'max:80'],
        ]);

        $conversation = $this->chat->notifyTeam(
            $user,
            (string) $data['body'],
            (string) ($data['group'] ?? 'Caja'),
        );

        return redirect()->route('comunicaciones.chat', ['c' => $conversation->id]);
    }

    public function storeMessage(
        StoreChatMessageRequest $request,
        ChatConversation $chatConversation,
        TenantManager $tenants,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $files = $request->file('attachments') ?? $request->file('attachment');
        $mentions = $request->input('mentioned_user_ids', []);
        if (! is_array($mentions)) {
            $mentions = [];
        }

        $this->chat->sendMessage(
            $chatConversation,
            $user,
            $request->input('body'),
            $files,
            $tenants->current()?->slug,
            $request->input('reply_to_id'),
            $mentions,
        );

        return redirect()->route('comunicaciones.chat', ['c' => $chatConversation->id]);
    }

    public function poll(Request $request, ChatConversation $chatConversation): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $this->chat->assertParticipant($chatConversation, $user);
        $this->chat->touchPresence($user, (string) $chatConversation->id);
        $this->chat->setViewing($user, (string) $chatConversation->id);
        $this->chat->markDelivered($chatConversation, $user);
        $this->chat->markRead($chatConversation, $user);

        return response()->json([
            'active' => $this->chat->activePayload($chatConversation, $user),
            'conversations' => $this->chat->listConversationsPayload($user),
            'unread_total' => $this->chat->unreadTotalFor($user),
            'typing' => $this->chat->typingPayload($chatConversation, $user),
        ]);
    }

    public function presence(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $this->chat->touchPresence($user);

        $ids = $request->input('user_ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_filter(array_map('strval', $ids)));

        return response()->json([
            'ok' => true,
            'presence' => $ids === [] ? [] : $this->chat->presenceForUsers($ids),
        ]);
    }

    public function typing(Request $request, ChatConversation $chatConversation): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $this->chat->touchTyping($chatConversation, $user);

        return response()->json(['ok' => true]);
    }

    public function updateMessage(Request $request, ChatMessage $chatMessage): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $message = $this->chat->editMessage($chatMessage, $user, (string) $data['body']);
        $conversation = $message->conversation
            ?? ChatConversation::query()->findOrFail($message->conversation_id);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $this->chat->serializeMessage($message, $user, $conversation),
            ]);
        }

        return redirect()->route('comunicaciones.chat', ['c' => $conversation->id]);
    }

    public function destroyMessage(Request $request, ChatMessage $chatMessage): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $message = $this->chat->softDeleteMessage($chatMessage, $user);
        $conversation = $message->conversation
            ?? ChatConversation::query()->findOrFail($message->conversation_id);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $this->chat->serializeMessage($message, $user, $conversation),
            ]);
        }

        return redirect()->route('comunicaciones.chat', ['c' => $conversation->id]);
    }

    public function react(Request $request, ChatMessage $chatMessage): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $data = $request->validate([
            'emoji' => ['required', 'string', 'max:16'],
        ]);

        $result = $this->chat->toggleReaction($chatMessage, $user, (string) $data['emoji']);

        return response()->json($result);
    }

    public function pin(Request $request, ChatConversation $chatConversation): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $data = $request->validate([
            'pinned' => ['required', 'boolean'],
        ]);

        $this->chat->setPinned($chatConversation, $user, (bool) $data['pinned']);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'pinned' => (bool) $data['pinned']]);
        }

        return redirect()->route('comunicaciones.chat', ['c' => $chatConversation->id]);
    }

    public function forward(Request $request, ChatMessage $chatMessage): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $data = $request->validate([
            'target_conversation_id' => ['required', 'uuid'],
        ]);

        $target = ChatConversation::query()->findOrFail((string) $data['target_conversation_id']);
        $message = $this->chat->forwardMessage($chatMessage, $user, $target);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $this->chat->serializeMessage($message, $user, $target),
                'conversation_id' => (string) $target->id,
            ]);
        }

        return redirect()->route('comunicaciones.chat', ['c' => $target->id]);
    }

    public function media(Request $request, ChatConversation $chatConversation): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return response()->json([
            'media' => $this->chat->mediaGallery($chatConversation, $user),
        ]);
    }

    public function mute(Request $request, ChatConversation $chatConversation): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $data = $request->validate([
            'muted' => ['required', 'boolean'],
        ]);

        $this->chat->setMuted($chatConversation, $user, (bool) $data['muted']);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'muted' => (bool) $data['muted']]);
        }

        return redirect()->route('comunicaciones.chat', ['c' => $chatConversation->id]);
    }

    public function search(Request $request, ChatConversation $chatConversation): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $q = trim((string) $request->query('q', ''));

        return response()->json([
            'results' => $this->chat->searchInConversation($chatConversation, $user, $q),
        ]);
    }

    public function messageContext(
        Request $request,
        ChatConversation $chatConversation,
        ChatMessage $chatMessage,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return response()->json([
            'messages' => $this->chat->messageContext($chatConversation, $user, $chatMessage),
        ]);
    }

    /**
     * Retención de historial: valores permitidos 30 / 90 / 180 días.
     * Plan no expone max retention; se documenta solo en la UI.
     */
    public function updateRetention(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->can('comunicaciones-chat.manage'), 403);
        abort_unless(Schema::hasColumn('cfg_clinic_settings', 'chat_retention_days'), 404);

        $data = $request->validate([
            'chat_retention_days' => ['nullable', 'integer', 'in:30,90,180'],
        ]);

        $settings = ClinicSetting::query()->first();
        if ($settings === null) {
            $settings = new ClinicSetting;
        }
        $settings->chat_retention_days = $data['chat_retention_days'] ?? null;
        $settings->save();

        return redirect()->route('comunicaciones.chat');
    }
}
