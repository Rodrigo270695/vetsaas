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

        return response()->json($result);
    }

    public function messages(
        Request $request,
        Tenant $tenant,
        PlatformSupportChatService $service,
    ): JsonResponse {
        $afterId = $request->query('after');
        $afterId = is_string($afterId) && $afterId !== '' ? $afterId : null;

        return response()->json(
            $service->messagesForTenant($tenant, $afterId),
        );
    }

    public function send(
        Request $request,
        Tenant $tenant,
        PlatformSupportChatService $service,
    ): JsonResponse {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $result = $service->sendToTenant(
            $tenant,
            $data['body'],
            $request->user(),
        );

        return response()->json($result);
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
