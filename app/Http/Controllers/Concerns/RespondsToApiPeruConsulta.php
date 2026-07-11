<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Integrations\ApiPeruConsultaException;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

trait RespondsToApiPeruConsulta
{
    /**
     * @param  callable(): array<string, mixed>  $callback
     */
    private function consultaApiPeruResponse(callable $callback): JsonResponse
    {
        try {
            $data = $callback();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (ApiPeruConsultaException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
            ], $e->httpStatus);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => __('propietarios.consulta.no_disponible'),
            ], 503);
        }
    }
}
