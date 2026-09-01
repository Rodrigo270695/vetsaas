<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Platform\WhatsAppHealthRadarService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlataformaWhatsAppHealthController extends Controller
{
    public function index(Request $request, WhatsAppHealthRadarService $radar): Response
    {
        $payload = $radar->paginate(
            trim((string) $request->string('search', '')),
            (string) $request->string('scope', 'problemas'),
            (int) $request->integer('per_page', 15),
        );

        return Inertia::render('plataforma/whatsapp-salud/index', $payload);
    }
}
