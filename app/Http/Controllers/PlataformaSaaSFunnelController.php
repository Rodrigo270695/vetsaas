<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Platform\SaaSFunnelService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlataformaSaaSFunnelController extends Controller
{
    public function index(Request $request, SaaSFunnelService $funnel): Response
    {
        $payload = $funnel->paginate(
            trim((string) $request->string('search', '')),
            (string) $request->string('scope', 'atencion'),
            (int) $request->integer('per_page', 15),
        );

        return Inertia::render('plataforma/embudo/index', $payload);
    }
}
