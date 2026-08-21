<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatDirectRequest;
use App\Http\Requests\StoreChatGroupRequest;
use App\Http\Requests\StoreChatMessageRequest;
use App\Models\ChatConversation;
use App\Services\Chat\TenantChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                $this->chat->markRead($conversation, $user);
                $active = $this->chat->activePayload($conversation, $user);
                // Refrescar unread tras marcar leído
                $conversations = $this->chat->listConversationsPayload($user);
            }
        }

        $unreadTotal = collect($conversations)->sum(static fn (array $c): int => (int) ($c['unread'] ?? 0));

        return Inertia::render('comunicaciones/chat/index', [
            'conversations' => $conversations,
            'users' => $users,
            'active' => $active,
            'unread_total' => $unreadTotal,
            'can_manage' => $user->can('comunicaciones-chat.manage'),
            'poll_ms' => 8_000,
        ]);
    }

    public function storeDirect(StoreChatDirectRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $conversation = $this->chat->findOrCreateDirect(
            $user,
            (string) $request->validated('user_id'),
        );

        return redirect()->route('comunicaciones.chat', ['c' => $conversation->id]);
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

    public function storeMessage(
        StoreChatMessageRequest $request,
        ChatConversation $chatConversation,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $this->chat->sendMessage(
            $chatConversation,
            $user,
            (string) $request->validated('body'),
        );

        return redirect()->route('comunicaciones.chat', ['c' => $chatConversation->id]);
    }

    public function poll(Request $request, ChatConversation $chatConversation): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $this->chat->assertParticipant($chatConversation, $user);
        $this->chat->markRead($chatConversation, $user);

        return response()->json([
            'active' => $this->chat->activePayload($chatConversation, $user),
            'conversations' => $this->chat->listConversationsPayload($user),
        ]);
    }
}
