<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente para consulta de DNI vía {@link https://apiperu.dev apiperu.dev}.
 *
 * Documentación: POST `{base_url}/dni` con header `Authorization: Bearer {token}`
 * y cuerpo JSON `{"dni":"12345678"}` (8 dígitos).
 */
final class ApiPeruDniService
{
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
        $token = trim((string) config('services.apiperu.token', ''));
        $base = rtrim((string) config('services.apiperu.base_url', 'https://apiperu.dev/api'), '/');

        if ($token === '') {
            throw new RuntimeException('Consulta DNI no disponible: configure APIPERU_TOKEN en el servidor.');
        }

        $response = Http::timeout(25)
            ->acceptJson()
            ->withToken($token)
            ->post($base.'/dni', ['dni' => $dni]);

        if (! $response->successful()) {
            throw new RuntimeException('La API de consulta DNI devolvió HTTP '.$response->status().'.');
        }

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
