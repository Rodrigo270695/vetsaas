<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Platform\ClosingQueueService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlataformaClosingQueueController extends Controller
{
    public function index(Request $request, ClosingQueueService $queue): Response
    {
        $payload = $queue->paginate(
            trim((string) $request->string('search', '')),
            (string) $request->string('scope', 'hoy'),
            (int) $request->integer('per_page', 15),
            max(1, (int) $request->integer('page', 1)),
        );

        return Inertia::render('plataforma/cola-cierre/index', $payload);
    }
}
