<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PortalPropietario;
use App\Models\PortalPushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PortalPushController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless(filled(config('webpush.vapid.public_key')), 503);

        $portal = $request->attributes->get('portal_propietario');
        abort_unless($portal instanceof PortalPropietario, 403);

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'max:32'],
        ]);

        PortalPushSubscription::query()->updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'portal_propietario_id' => $portal->id,
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): Response
    {
        $portal = $request->attributes->get('portal_propietario');
        abort_unless($portal instanceof PortalPropietario, 403);

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
        ]);

        PortalPushSubscription::query()
            ->where('portal_propietario_id', $portal->id)
            ->where('endpoint', $data['endpoint'])
            ->delete();

        return response()->noContent();
    }
}
