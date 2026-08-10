<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Suscripción Web Push (solo panel central / usuarios sin tenant).
 */
final class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless(filled(config('webpush.vapid.public_key')), 503);

        $user = $request->user();
        abort_unless($user !== null && $user->tenant_id === null, 403);

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'max:32'],
        ]);

        PushSubscription::query()->updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id' => $user->id,
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null && $user->tenant_id === null, 403);

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
        ]);

        PushSubscription::query()
            ->where('user_id', $user->id)
            ->where('endpoint', $data['endpoint'])
            ->delete();

        return response()->noContent();
    }
}
