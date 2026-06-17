<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validación de payloads del catálogo editable por clínica (grooming_servicios / hotel_tipos_estancia).
 * Usado desde TarifaServiciosController; los permisos los aplican las rutas (tarifas.*).
 */
final class CatalogoClinicaValidator
{
    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function grooming(Request $request): array
    {
        $payload = self::normalizeGroomingInput($request);

        return Validator::make($payload, [
            'nombre' => ['required', 'string', 'min:2', 'max:200'],
            'categoria' => ['nullable', 'string', 'max:80'],
            'precio_lista' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'moneda' => ['nullable', 'string', Rule::in(['PEN', 'USD'])],
            'duracion_minutos' => ['required', 'integer', 'min:5', 'max:480'],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ])->validate();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function hotel(Request $request): array
    {
        $payload = self::normalizeHotelInput($request);

        return Validator::make($payload, [
            'nombre' => ['required', 'string', 'min:2', 'max:200'],
            'categoria' => ['nullable', 'string', 'max:80'],
            'precio_lista' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'moneda' => ['nullable', 'string', Rule::in(['PEN', 'USD'])],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ])->validate();
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeGroomingInput(Request $request): array
    {
        $payload = $request->all();

        if (is_string($payload['nombre'] ?? null)) {
            $payload['nombre'] = trim($payload['nombre']);
        }

        if (is_string($payload['categoria'] ?? null)) {
            $trim = trim($payload['categoria']);
            $payload['categoria'] = $trim === '' ? null : $trim;
        }

        if ($request->has('activo')) {
            $payload['activo'] = $request->boolean('activo');
        }

        if ($request->filled('duracion_minutos')) {
            $payload['duracion_minutos'] = (int) $request->input('duracion_minutos');
        } elseif (! array_key_exists('duracion_minutos', $payload)) {
            $payload['duracion_minutos'] = 60;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeHotelInput(Request $request): array
    {
        $payload = $request->all();

        if (is_string($payload['nombre'] ?? null)) {
            $payload['nombre'] = trim($payload['nombre']);
        }

        if (is_string($payload['categoria'] ?? null)) {
            $trim = trim($payload['categoria']);
            $payload['categoria'] = $trim === '' ? null : $trim;
        }

        if ($request->has('activo')) {
            $payload['activo'] = $request->boolean('activo');
        }

        return $payload;
    }
}
