<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\Tenancy\TenantShowcaseService;
use Illuminate\Http\JsonResponse;

/**
 * Listado público de clínicas con plan de pago y logo (carrusel Orvae).
 */
class TenantShowcaseController extends Controller
{
    public function index(TenantShowcaseService $showcase): JsonResponse
    {
        return response()->json([
            'data' => $showcase->clientsForCarousel(),
        ]);
    }
}
