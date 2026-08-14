<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Platform\PlataformaReportesSnapshotService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard de reportes de marketing / geo para el superadmin (host central).
 */
class PlataformaReportesController extends Controller
{
    public function index(PlataformaReportesSnapshotService $snapshot): Response
    {
        return Inertia::render('plataforma/reportes/index', [
            'snapshot' => $snapshot->build(),
        ]);
    }
}
