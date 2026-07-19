<?php

declare(strict_types=1);

namespace App\Services\PetPass;

use App\Models\ClinicSetting;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Cliente HTTP hacia AlmaPet ID (creación de handoff one-time).
 */
final class AlmaPetHandoffClient
{
    public function isEnabled(): bool
    {
        return (bool) config('petpass.enabled', false)
            && filled(config('petpass.base_url'))
            && filled(config('petpass.handoff_secret'));
    }

    /**
     * @return array{token: string, url: string, expires_at: string}
     */
    public function createHandoff(Paciente $paciente): array
    {
        if (! $this->isEnabled()) {
            throw ValidationException::withMessages([
                'petpass' => 'AlmaPet ID no está habilitado en esta clínica.',
            ]);
        }

        $microchip = preg_replace('/\D+/', '', (string) ($paciente->microchip ?? '')) ?? '';
        if (strlen($microchip) < 9) {
            throw ValidationException::withMessages([
                'microchip' => 'El paciente necesita un microchip válido para registrarlo en AlmaPet ID.',
            ]);
        }

        if (in_array($paciente->petpass_status, ['registered', 'lost'], true)) {
            throw ValidationException::withMessages([
                'petpass' => 'Este paciente ya está vinculado a AlmaPet ID.',
            ]);
        }

        $tenant = current_tenant();
        if ($tenant === null) {
            throw new RuntimeException('No hay tenant activo.');
        }

        $tenantModel = Tenant::query()->find($tenant->id());
        $clinic = ClinicSetting::current();

        $paciente->loadMissing('propietario');
        $owner = $paciente->propietario;

        if ($owner === null) {
            throw ValidationException::withMessages([
                'petpass' => 'El paciente no tiene titular. Asigna un propietario antes de registrar en AlmaPet ID.',
            ]);
        }

        $docType = trim((string) ($owner->tipo_documento ?? ''));
        $docNumber = preg_replace('/\s+/', '', (string) ($owner->numero_documento ?? '')) ?? '';

        if ($docType === '' || $docNumber === '') {
            throw ValidationException::withMessages([
                'petpass' => 'El titular debe tener tipo y número de documento (DNI u otro) para registrar en AlmaPet ID. Complétalo en la ficha del propietario y vuelve a intentar.',
            ]);
        }

        // DNI peruano: 8 dígitos
        if (in_array(strtolower($docType), ['dni', '1'], true) && ! preg_match('/^\d{8}$/', $docNumber)) {
            throw ValidationException::withMessages([
                'petpass' => 'El DNI del titular debe tener 8 dígitos. Corrígelo en la ficha del propietario.',
            ]);
        }

        $ownerName = trim((string) ($owner->nombres ?? ''));
        $ownerLast = trim((string) ($owner->apellidos ?? ''));
        $razon = trim((string) ($owner->razon_social ?? ''));
        if ($ownerName === '' && $razon !== '') {
            $ownerName = $razon;
        }

        $payload = [
            'vetsaas_tenant_id' => (string) $tenant->id(),
            'vetsaas_slug' => (string) $tenant->slug,
            'vetsaas_paciente_id' => (string) $paciente->id,
            'microchip' => $microchip,
            'country_code' => 'PE',
            'clinic' => [
                'name' => (string) ($clinic->nombre_comercial ?: $clinic->razon_social ?: $tenant->razonSocial()),
                'ruc' => $tenantModel?->ruc,
                'email' => $clinic->email_institucional ?? $tenantModel?->email_admin,
                'phone' => $clinic->telefono_principal ?? $tenantModel?->telefono,
                'address' => $clinic->direccion_fiscal ?? $tenantModel?->direccion,
                'city' => null,
            ],
            'owner' => [
                'document_type' => $docType,
                'document_number' => $docNumber,
                'name' => $ownerName !== '' ? $ownerName : 'Titular',
                'lastname' => $ownerLast !== '' ? $ownerLast : '—',
                'email' => $owner->email,
                'phone' => $owner->telefono,
            ],
            'animal' => [
                'name' => $paciente->nombre,
                'species' => $paciente->especie,
                'breed' => $paciente->raza,
                'sex' => $paciente->sexo,
                'color' => $paciente->color,
                'birth_date' => $paciente->fecha_nacimiento?->toDateString(),
                'notes' => $paciente->notas,
            ],
        ];

        $url = rtrim((string) config('petpass.base_url'), '/').(string) config('petpass.handoff_path');

        $response = Http::timeout((int) config('petpass.timeout_seconds', 15))
            ->withHeaders([
                'Accept' => 'application/json',
                'X-AlmaPet-Handoff-Secret' => (string) config('petpass.handoff_secret'),
            ])
            ->post($url, $payload);

        if (! $response->successful()) {
            $message = $response->json('message')
                ?? $response->json('errors.microchip.0')
                ?? 'No se pudo iniciar el registro en AlmaPet ID.';

            throw ValidationException::withMessages([
                'petpass' => is_string($message) ? $message : 'No se pudo iniciar el registro en AlmaPet ID.',
            ]);
        }

        $data = $response->json();
        if (! is_array($data) || blank($data['url'] ?? null)) {
            throw ValidationException::withMessages([
                'petpass' => 'Respuesta inválida de AlmaPet ID.',
            ]);
        }

        $paciente->forceFill([
            'petpass_status' => 'pending',
        ])->save();

        return [
            'token' => (string) ($data['token'] ?? ''),
            'url' => (string) $data['url'],
            'expires_at' => (string) ($data['expires_at'] ?? ''),
        ];
    }
}
