<?php

declare(strict_types=1);

namespace App\Services\PetPass;

use App\Models\ClinicSetting;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

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

        $clinicName = trim((string) ($clinic->nombre_comercial ?: $clinic->razon_social ?: $tenant->razonSocial()));
        if ($clinicName === '') {
            $clinicName = (string) ($tenant->slug ?: 'Clínica VetSaaS');
        }

        $payload = [
            'vetsaas_tenant_id' => (string) $tenant->id(),
            'vetsaas_slug' => (string) $tenant->slug,
            'vetsaas_paciente_id' => (string) $paciente->id,
            'microchip' => $microchip,
            'country_code' => 'PE',
            'clinic' => [
                'name' => $clinicName,
                'ruc' => $tenantModel?->ruc,
                'email' => $this->nullableEmail($clinic->email_institucional ?? $tenantModel?->email_admin),
                'phone' => $clinic->telefono_principal ?? $tenantModel?->telefono,
                'address' => $clinic->direccion_fiscal ?? $tenantModel?->direccion,
                'city' => null,
            ],
            'owner' => [
                'document_type' => $docType,
                'document_number' => $docNumber,
                'name' => $ownerName !== '' ? $ownerName : 'Titular',
                'lastname' => $ownerLast !== '' ? $ownerLast : '—',
                'email' => $this->nullableEmail($owner->email),
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

        try {
            $response = Http::timeout((int) config('petpass.timeout_seconds', 15))
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'X-AlmaPet-Handoff-Secret' => (string) config('petpass.handoff_secret'),
                ])
                ->withOptions(['allow_redirects' => false])
                ->post($url, $payload);
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'petpass' => 'No se pudo conectar con AlmaPet ID ('.$url.'). Verifica PETPASS_BASE_URL y que el servicio esté en línea.',
            ]);
        }

        if ($response->redirect()) {
            throw ValidationException::withMessages([
                'petpass' => 'AlmaPet ID redirigió el handoff (HTTP '.$response->status().' → '.($response->header('Location') ?: 'sin Location').'). Revisa PETPASS_BASE_URL='.$url,
            ]);
        }

        if (! $response->successful()) {
            $message = $this->extractErrorMessage($response)
                ?? match ($response->status()) {
                    401, 403 => 'AlmaPet ID rechazó la clave de handoff (PETPASS_HANDOFF_SECRET no coincide con ALMAPET_HANDOFF_SECRET).',
                    404 => 'Endpoint de handoff no encontrado. Debe ser POST '.$url,
                    422 => 'AlmaPet ID rechazó los datos del paciente (revisa microchip, email y documento del titular).',
                    503 => 'Handoff no configurado en AlmaPet (ALMAPET_HANDOFF_SECRET vacío en el VPS).',
                    default => 'No se pudo iniciar el registro en AlmaPet ID (HTTP '.$response->status().').',
                };

            throw ValidationException::withMessages([
                'petpass' => $message,
            ]);
        }

        $data = $response->json();
        if (! is_array($data)) {
            $preview = Str::limit(trim(strip_tags($response->body())), 160);

            throw ValidationException::withMessages([
                'petpass' => 'AlmaPet ID no devolvió JSON en '.$url.' (HTTP '.$response->status().'). '.$preview,
            ]);
        }

        $handoffUrl = $data['url'] ?? $data['data']['url'] ?? null;
        if (! is_string($handoffUrl) || blank($handoffUrl)) {
            throw ValidationException::withMessages([
                'petpass' => 'Respuesta inválida de AlmaPet ID (sin url). Keys: '.implode(', ', array_keys($data)).'. HTTP '.$response->status(),
            ]);
        }

        $paciente->forceFill([
            'petpass_status' => 'pending',
        ])->save();

        return [
            'token' => (string) ($data['token'] ?? $data['data']['token'] ?? ''),
            'url' => $handoffUrl,
            'expires_at' => (string) ($data['expires_at'] ?? $data['data']['expires_at'] ?? ''),
        ];
    }

    /**
     * @return string|null
     */
    private function extractErrorMessage(\Illuminate\Http\Client\Response $response): ?string
    {
        $message = $response->json('message');
        if (is_string($message) && $message !== '' && $message !== 'The given data was invalid.') {
            return $message;
        }

        $errors = $response->json('errors');
        if (! is_array($errors)) {
            return is_string($message) && $message !== '' ? $message : null;
        }

        foreach ($errors as $field => $messages) {
            if (is_string($messages) && $messages !== '') {
                return $field.': '.$messages;
            }
            if (is_array($messages) && isset($messages[0]) && is_string($messages[0])) {
                return $field.': '.$messages[0];
            }
        }

        return is_string($message) && $message !== '' ? $message : null;
    }

    private function nullableEmail(mixed $value): ?string
    {
        $email = trim((string) ($value ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }
}
