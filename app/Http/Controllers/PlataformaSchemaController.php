<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Platform\DatabaseSchemaInspector;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlataformaSchemaController extends Controller
{
    public function index(Request $request, DatabaseSchemaInspector $inspector): Response
    {
        $schemas = $inspector->allowedSchemas();
        $requested = trim((string) $request->query('schema', 'public'));
        $schema = $inspector->isAllowedSchema($requested) ? $requested : 'public';

        return Inertia::render('plataforma/esquema/index', [
            'schemas' => $schemas,
            'diagram' => $inspector->inspect($schema),
        ]);
    }
}
