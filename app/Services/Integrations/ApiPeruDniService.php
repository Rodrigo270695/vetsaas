<?php

namespace App\Services\Integrations;

use App\Services\Integrations\Concerns\FallsBackToApisunatLookup;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Cliente para consulta de DNI vía {@link https://apiperu.dev apiperu.dev}.
 * Si apiperu falla por cuota o indisponibilidad, usa APISUNAT (Lucode) como respaldo.
 *
 * Documentación: POST `{base_url}/dni` con header `Authorization: Bearer {token}`
 * y cuerpo JSON `{"dni":"12345678"}` (8 dígitos).
 */
final class ApiPeruDniService
{
    use FallsBackToApisunatLookup;

    public function __construct(
        private readonly ApisunatLookupService $apisunatLookup,
    ) {}

    /**
     * @return array{
     *     dni: string,
     *     nombres: string,
     *     apellidos: string,
     *     nombre_completo: string,
     * }
     */
    public function consultar(string $dni): array
    {
        $dni = preg_replace('/\D+/', '', $dni) ?? '';

        if (strlen($dni) !== 8) {
            throw new RuntimeException('El DNI debe tener 8 dígitos.');
        }

        $cacheKey = "documento:dni:{$dni}";

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($dni): array {
            return $this->consultarConFallbackApisunat(
                fn (): array => $this->fetchFromApiPeru($dni),
                fn (): array => $this->apisunatLookup->consultarDni($dni),
            );
        });
    }

    /**
     * @return array{
     *     dni: string,
     *     nombres: string,
     *     apellidos: string,
     *     nombre_completo: string,
     * }
     */
    private function fetchFromApiPeru(string $dni): array
    {
        $response = ApiPeruHttp::client()->post('/dni', ['dni' => $dni]);

        ApiPeruHttp::assertSuccessful($response);

        $json = $response->json();
        if (! is_array($json) || ! ($json['success'] ?? false)) {
            $msg = is_string($json['message'] ?? null) ? $json['message'] : 'No se encontraron datos para el DNI indicado.';
            throw new RuntimeException($msg);
        }

        $data = $json['data'] ?? null;
        if (! is_array($data)) {
            throw new RuntimeException('Respuesta de API DNI inválida (sin data).');
        }

        $nombres = trim((string) ($data['nombres'] ?? ''));
        $paterno = trim((string) ($data['apellido_paterno'] ?? ''));
        $materno = trim((string) ($data['apellido_materno'] ?? ''));
        $apellidos = trim($paterno.' '.$materno);
        $nombreCompleto = trim((string) ($data['nombre_completo'] ?? ''));

        if ($nombres === '' && $nombreCompleto !== '') {
            $nombres = $nombreCompleto;
        }

        if ($nombres === '' && $apellidos === '' && $nombreCompleto === '') {
            throw new RuntimeException('La API no devolvió nombres para este DNI.');
        }

        return [
            'dni' => $dni,
            'nombres' => mb_substr($nombres !== '' ? $nombres : $nombreCompleto, 0, 150),
            'apellidos' => mb_substr($apellidos, 0, 150),
            'nombre_completo' => mb_substr($nombreCompleto !== '' ? $nombreCompleto : trim($nombres.' '.$apellidos), 0, 255),
        ];
    }
}
