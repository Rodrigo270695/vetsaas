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
        $result = $service->ensureThread($tenant);
        $service->markThreadRead($tenant);

        return response()->json($result);
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
