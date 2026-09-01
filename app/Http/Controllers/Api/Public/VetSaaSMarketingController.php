<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\Marketing\VetSaaSPublicMarketingService;
use App\Services\Tenancy\TenantShowcaseService;
use Illuminate\Http\JsonResponse;

/**
 * Marketing público VetSaaS para Orvae (planes, features, conteo clínicas).
 */
class VetSaaSMarketingController extends Controller
{
    public function __invoke(
        VetSaaSPublicMarketingService $marketing,
        TenantShowcaseService $showcase,
    ): JsonResponse {
        $payload = $marketing->payload();

        return response()->json([
            ...$payload,
            'clients' => $showcase->clientsForCarousel(),
            'reviews' => $payload['reviews'] ?? [],
        ]);
    }
}
