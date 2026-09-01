<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Platform\TenantModulesMatrixService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlataformaTenantModulesController extends Controller
{
    public function index(Request $request, TenantModulesMatrixService $matrix): Response
    {
        $payload = $matrix->paginate(
            trim((string) $request->string('search', '')),
            (string) $request->string('scope', 'todos'),
            (int) $request->integer('per_page', 15),
        );

        return Inertia::render('plataforma/modulos-clinicas/index', $payload);
    }
}
