<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Support\Sales\SalesKnowledgeExport;
use Illuminate\Http\JsonResponse;

class SalesKnowledgeController extends Controller
{
    public function __invoke(SalesKnowledgeExport $export): JsonResponse
    {
        return response()->json($export->payload());
    }
}
