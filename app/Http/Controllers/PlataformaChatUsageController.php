<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Chat\TenantChatUsagePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Actividad de chat interno por clínica (panel SaaS / Sistema).
 */
class PlataformaChatUsageController extends Controller
{
    public function index(Request $request, TenantChatUsagePresenter $presenter): Response
    {
        $search = trim((string) $request->string('search', ''));
        $scope = (string) $request->string('scope', 'activos');
        $perPage = (int) $request->integer('per_page', 15);

        $payload = $presenter->paginate($search, $scope, $perPage);

        return Inertia::render('plataforma/uso-chat/index', [
            'items' => $payload['items'],
            'filters' => $payload['filters'],
            'stats' => $payload['stats'],
        ]);
    }
}
