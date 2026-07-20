<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validación de payloads del catálogo editable por clínica
 * (grooming_servicios / hotel_tipos_estancia / servicios_clinicos).
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
        $payload = self::normalizeCatalogInput($request, withDuracion: true, defaultDuracion: 60);

        $rules = [
            'nombre' => ['required', 'string', 'min:2', 'max:200'],
            'precio_lista' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'moneda' => ['nullable', 'string', Rule::in(['PEN', 'USD'])],
            'duracion_minutos' => ['required', 'integer', 'min:5', 'max:480'],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];

        if (Schema::hasTable('categorias_grooming')) {
            $rules['categoria_id'] = ['nullable', 'uuid', Rule::exists('categorias_grooming', 'id')];
        } else {
            $rules['categoria'] = ['nullable', 'string', 'max:80'];
        }

        return Validator::make($payload, $rules)->validate();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function hotel(Request $request): array
    {
        $payload = self::normalizeCatalogInput($request, withDuracion: false);

        $rules = [
            'nombre' => ['required', 'string', 'min:2', 'max:200'],
            'precio_lista' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'moneda' => ['nullable', 'string', Rule::in(['PEN', 'USD'])],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];

        if (Schema::hasTable('categorias_hotel')) {
            $rules['categoria_id'] = ['nullable', 'uuid', Rule::exists('categorias_hotel', 'id')];
        } else {
            $rules['categoria'] = ['nullable', 'string', 'max:80'];
        }

        return Validator::make($payload, $rules)->validate();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function clinica(Request $request): array
    {
        $payload = self::normalizeCatalogInput($request, withDuracion: true, defaultDuracion: null, duracionNullable: true);

        if (array_key_exists('precio_costo', $payload) && blank($payload['precio_costo'])) {
            $payload['precio_costo'] = null;
        }

        return Validator::make($payload, [
            'nombre' => ['required', 'string', 'min:2', 'max:200'],
            'categoria_id' => ['nullable', 'uuid', Rule::exists('categorias_servicio_clinico', 'id')],
            'precio_lista' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'precio_costo' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'moneda' => ['nullable', 'string', Rule::in(['PEN', 'USD'])],
            'duracion_minutos' => ['nullable', 'integer', 'min:5', 'max:480'],
            'activo' => ['sometimes', 'boolean'],
            'orden' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ])->validate();
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeCatalogInput(
        Request $request,
        bool $withDuracion,
        ?int $defaultDuracion = null,
        bool $duracionNullable = false,
    ): array {
        $payload = $request->all();

        if (is_string($payload['nombre'] ?? null)) {
            $payload['nombre'] = trim($payload['nombre']);
        }

        if (is_string($payload['categoria'] ?? null)) {
            $trim = trim($payload['categoria']);
            $payload['categoria'] = $trim === '' ? null : $trim;
        }

        $categoriaId = $payload['categoria_id'] ?? null;
        if (! is_string($categoriaId) || trim($categoriaId) === '') {
            $payload['categoria_id'] = null;
        }

        if ($request->has('activo')) {
            $payload['activo'] = $request->boolean('activo');
        }

        if ($withDuracion) {
            if ($request->filled('duracion_minutos')) {
                $payload['duracion_minutos'] = (int) $request->input('duracion_minutos');
            } elseif ($duracionNullable && array_key_exists('duracion_minutos', $payload) && blank($payload['duracion_minutos'])) {
                $payload['duracion_minutos'] = null;
            } elseif (! array_key_exists('duracion_minutos', $payload) && $defaultDuracion !== null) {
                $payload['duracion_minutos'] = $defaultDuracion;
            }
        }

        return $payload;
    }
}
