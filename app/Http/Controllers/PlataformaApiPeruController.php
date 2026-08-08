<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsToApiPeruConsulta;
use App\Http\Requests\PlataformaApiPeruConsultaRequest;
use App\Services\Integrations\ApiPeruConsultaService;
use App\Support\Integrations\ApiPeruEndpointCatalog;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlataformaApiPeruController extends Controller
{
    use RespondsToApiPeruConsulta;

    public function index(): Response
    {
        abort_unless(request()->user()?->can('plataforma-operaciones.view'), 403);

        $tokenConfigured = trim((string) config('services.apiperu.token', '')) !== '';
        $baseUrl = rtrim((string) config('services.apiperu.base_url', 'https://apiperu.dev/api'), '/');

        return Inertia::render('plataforma/apiperu/index', [
            'groups' => ApiPeruEndpointCatalog::groups(),
            'meta' => [
                'token_configured' => $tokenConfigured,
                'base_url' => $baseUrl,
                'docs_url' => 'https://docs.apiperu.dev/',
            ],
        ]);
    }

    public function consultar(
        PlataformaApiPeruConsultaRequest $request,
        ApiPeruConsultaService $service,
    ): JsonResponse {
        $endpoint = (string) $request->validated('endpoint');
        /** @var array<string, mixed> $payload */
        $payload = $request->validated('payload') ?? [];

        return $this->consultaApiPeruResponse(
            fn (): array => $service->consultar($endpoint, $payload),
        );
    }
}
