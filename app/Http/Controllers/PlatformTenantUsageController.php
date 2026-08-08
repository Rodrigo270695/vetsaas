<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Plan\TenantPlanUsagePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformTenantUsageController extends Controller
{
    public function index(Request $request, TenantPlanUsagePresenter $presenter): Response
    {
        abort_unless($request->user()?->can('plataforma-suscripciones.view'), 403);

        $search = trim((string) $request->string('search', ''));
        $semaphore = (string) $request->string('semaphore', 'todos');
        $perPage = (int) $request->integer('per_page', 15);

        $payload = $presenter->paginate($search, $semaphore, $perPage);

        return Inertia::render('plataforma/uso-planes/index', [
            'items' => $payload['items'],
            'filters' => $payload['filters'],
            'stats' => $payload['stats'],
        ]);
    }
}
