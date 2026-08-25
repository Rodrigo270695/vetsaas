<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\PlatformSupportChatBroadcastJob;
use App\Models\Tenant;
use App\Services\Chat\PlatformSupportChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformSupportChatController extends Controller
{
    public function index(Request $request, PlatformSupportChatService $service): Response
    {
        $plan = (string) $request->query('plan', 'all');
        $search = (string) $request->query('q', '');

        return Inertia::render('plataforma/chat-soporte/index', [
            'tenants' => $service->listTenants($plan, $search),
            'filters' => [
                'plan' => in_array($plan, ['all', 'free', 'paid'], true) ? $plan : 'all',
                'q' => $search,
            ],
            'agents' => $service->assignableAgents(),
            'templates' => $service->listTemplates(true),
            'broadcast' => [
                'enabled' => filled(config('broadcasting.connections.reverb.key'))
                    && config('broadcasting.default') === 'reverb',
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => config('broadcasting.connections.reverb.options.host') ?: 'localhost',
                'port' => (int) (config('broadcasting.connections.reverb.options.port') ?: 8080),
                'scheme' => (string) (config('broadcasting.connections.reverb.options.scheme') ?: 'http'),
            ],
            'poll_ms' => 8000,
        ]);
    }

    public function tenants(Request $request, PlatformSupportChatService $service): JsonResponse
    {
        $plan = (string) $request->query('plan', 'all');
        $search = (string) $request->query('q', '');

        return response()->json([
            'tenants' => $service->listTenants($plan, $search),
        ]);
    }

    public function ensure(Tenant $tenant, PlatformSupportChatService $service): JsonResponse
    {
        try {
            $result = $service->ensureThread($tenant);
            $service->markThreadRead($tenant);

            return response()->json($result);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function messages(
        Request $request,
        Tenant $tenant,
        PlatformSupportChatService $service,
    ): JsonResponse {
        $afterId = $request->query('after');
        $afterId = is_string($afterId) && $afterId !== '' ? $afterId : null;

        $service->markThreadRead($tenant);

        return response()->json(
            $service->messagesForTenant($tenant, $afterId),
        );
    }

    public function inbox(PlatformSupportChatService $service): JsonResponse
    {
        return response()->json($service->inboxPing());
    }

    public function send(
        Request $request,
        Tenant $tenant,
        PlatformSupportChatService $service,
    ): JsonResponse {
        $mimes = 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,csv,zip';

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:4000'],
            'reply_to_id' => ['nullable', 'uuid'],
            'attachment' => ['nullable', 'file', 'max:15360', "mimes:{$mimes}"],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:15360', "mimes:{$mimes}"],
        ]);

        $files = $request->file('attachments') ?? $request->file('attachment');
        $body = trim((string) ($data['body'] ?? ''));
        $hasFile = $request->hasFile('attachment') || $request->hasFile('attachments');

        if ($body === '' && ! $hasFile) {
            return response()->json([
                'message' => __('Escribe un mensaje o adjunta un archivo.'),
            ], 422);
        }

        $replyToId = isset($data['reply_to_id']) && is_string($data['reply_to_id'])
            ? $data['reply_to_id']
            : null;

        $result = $service->sendToTenant(
            $tenant,
            $body,
            $request->user(),
            $files,
            $replyToId,
        );

        return response()->json($result);
    }

    public function updateMessage(
        Request $request,
        Tenant $tenant,
        string $message,
        PlatformSupportChatService $service,
    ): JsonResponse {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        return response()->json(
            $service->editMessage($tenant, $message, (string) $data['body'], $request->user()),
        );
    }

    public function destroyMessage(
        Tenant $tenant,
        string $message,
        PlatformSupportChatService $service,
    ): JsonResponse {
        return response()->json(
            $service->deleteMessage($tenant, $message),
        );
    }

    public function react(
        Request $request,
        Tenant $tenant,
        string $message,
        PlatformSupportChatService $service,
    ): JsonResponse {
        $data = $request->validate([
            'emoji' => ['required', 'string', 'max:16'],
        ]);

        return response()->json(
            $service->react($tenant, $message, (string) $data['emoji']),
        );
    }

    public function forward(
        Request $request,
        Tenant $tenant,
        string $message,
        PlatformSupportChatService $service,
    ): JsonResponse {
        $data = $request->validate([
            'target_conversation_id' => ['required', 'uuid'],
        ]);

        return response()->json(
            $service->forwardMessage(
                $tenant,
                $message,
                (string) $data['target_conversation_id'],
            ),
        );
    }

    public function forwardTargets(
        Tenant $tenant,
        PlatformSupportChatService $service,
    ): JsonResponse {
        return response()->json([
            'conversations' => $service->forwardTargets($tenant),
        ]);
    }

    public function media(
        Tenant $tenant,
        PlatformSupportChatService $service,
    ): JsonResponse {
        return response()->json([
            'media' => $service->mediaForTenant($tenant),
        ]);
    }

    public function typing(
        Tenant $tenant,
        PlatformSupportChatService $service,
    ): JsonResponse {
        $service->touchTyping($tenant);

        return response()->json([
            'ok' => true,
            'typing' => $service->typingForTenant($tenant),
        ]);
    }

    public function messageContext(
        Tenant $tenant,
        string $message,
        PlatformSupportChatService $service,
    ): JsonResponse {
        return response()->json([
            'messages' => $service->messageContext($tenant, $message),
        ]);
    }

    public function assign(
        Request $request,
        Tenant $tenant,
        PlatformSupportChatService $service,
    ): JsonResponse {
        $data = $request->validate([
            'assigned_agent_id' => ['nullable', 'uuid'],
        ]);

        return response()->json(
            $service->assignAgent(
                $tenant,
                isset($data['assigned_agent_id']) ? (string) $data['assigned_agent_id'] : null,
            ),
        );
    }

    public function mute(
        Request $request,
        Tenant $tenant,
        PlatformSupportChatService $service,
    ): JsonResponse {
        $data = $request->validate([
            'muted' => ['required', 'boolean'],
        ]);

        return response()->json(
            $service->setMuted($tenant, (bool) $data['muted']),
        );
    }

    public function notes(
        Tenant $tenant,
        PlatformSupportChatService $service,
    ): JsonResponse {
        return response()->json([
            'notes' => $service->listNotes($tenant),
        ]);
    }

    public function storeNote(
        Request $request,
        Tenant $tenant,
        PlatformSupportChatService $service,
    ): JsonResponse {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $user = $request->user();
        abort_unless($user !== null, 401);

        return response()->json([
            'note' => $service->addNote($tenant, $user, (string) $data['body']),
        ], 201);
    }

    public function destroyNote(
        Request $request,
        string $note,
        PlatformSupportChatService $service,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $service->deleteNote($note, $user);

        return response()->json(['ok' => true]);
    }

    public function templates(PlatformSupportChatService $service): JsonResponse
    {
        return response()->json([
            'templates' => $service->listTemplates(false),
        ]);
    }

    public function storeTemplate(
        Request $request,
        PlatformSupportChatService $service,
    ): JsonResponse {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:4000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'template' => $service->upsertTemplate(
                null,
                (string) $data['label'],
                (string) $data['body'],
                $request->user(),
                isset($data['sort_order']) ? (int) $data['sort_order'] : null,
                (bool) ($data['is_active'] ?? true),
            ),
        ], 201);
    }

    public function updateTemplate(
        Request $request,
        string $template,
        PlatformSupportChatService $service,
    ): JsonResponse {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:4000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'template' => $service->upsertTemplate(
                $template,
                (string) $data['label'],
                (string) $data['body'],
                $request->user(),
                isset($data['sort_order']) ? (int) $data['sort_order'] : null,
                (bool) ($data['is_active'] ?? true),
            ),
        ]);
    }

    public function destroyTemplate(
        string $template,
        PlatformSupportChatService $service,
    ): JsonResponse {
        if (str_starts_with($template, 'builtin-')) {
            return response()->json(['message' => __('No se puede eliminar una plantilla integrada.')], 422);
        }
        $service->deleteTemplate($template);

        return response()->json(['ok' => true]);
    }

    public function broadcast(Request $request, PlatformSupportChatService $service): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
            'plan' => ['nullable', 'string', 'in:all,free,paid'],
            'async' => ['nullable', 'boolean'],
        ]);

        $plan = $data['plan'] ?? 'all';
        $body = $data['body'];
        $async = (bool) ($data['async'] ?? false);

        $tenants = $service->listTenants($plan);
        $count = count($tenants);

        if ($async || $count > 15) {
            PlatformSupportChatBroadcastJob::dispatch(
                $body,
                $plan,
                $request->user()?->id !== null ? (string) $request->user()->id : null,
            );

            return response()->json([
                'queued' => true,
                'target_count' => $count,
            ]);
        }

        $result = $service->broadcast($body, $plan, $request->user());

        return response()->json([
            'queued' => false,
            'sent' => $result['sent'],
            'failed' => $result['failed'],
            'target_count' => $count,
        ]);
    }
}
