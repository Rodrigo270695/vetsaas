<?php

namespace App\Http\Controllers;

use App\Http\Requests\OfflineSyncPushRequest;
use App\Services\Offline\OfflineBootstrapService;
use App\Services\Offline\OfflineSyncPushService;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfflineSyncController extends Controller
{
    public function bootstrap(Request $request, OfflineBootstrapService $bootstrap, TenantManager $tenants): JsonResponse
    {
        abort_unless($request->user()?->can('ventas.create') ?? false, 403);
        abort_unless($request->string('scope')->toString() === 'caja', 404);

        $user = $request->user();
        abort_if($user === null, 403);

        return response()->json([
            'data' => $bootstrap->caja($user, $tenants->current()?->tenant),
        ]);
    }

    public function push(OfflineSyncPushRequest $request, OfflineSyncPushService $push): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $results = [];

        foreach ($request->validated('items') as $item) {
            $results[] = $push->process($user, $item);
        }

        return response()->json(['results' => $results]);
    }
}
