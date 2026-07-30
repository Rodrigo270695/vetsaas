<?php

declare(strict_types=1);

namespace App\Services\PetPass;

use App\Models\ClinicSetting;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\TenantWhatsAppSession;
use App\Services\OpenWa\TenantWhatsAppMessenger;
use App\Services\OpenWa\TenantWhatsAppSessionSync;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Cliente HTTP hacia AlmaPet ID: alta sin cobro + aviso WhatsApp del tenant.
 */
final class AlmaPetHandoffClient
{
    public function __construct(
        private readonly TenantWhatsAppMessenger $whatsApp,
        private readonly TenantWhatsAppSessionSync $sessionSync,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('petpass.enabled', false)
            && filled(config('petpass.base_url'))
            && filled(config('petpass.handoff_secret'));
    }

    /**
     * @return array{
     *     activate_url: string,
     *     public_code: string,
     *     registration_id: string|int|null,
     *     whatsapp_sent: bool,
     *     whatsapp_error: string|null
     * }
     */
    public function registerWithoutCharge(Paciente $paciente): array
    {
        if (! $this->isEnabled()) {
            throw ValidationException::withMessages([
                'petpass' => 'AlmaPet ID no está habilitado en esta clínica.',
            ]);
        }

        $microchip = preg_replace('/\D+/', '', (string) ($paciente->microchip ?? '')) ?? '';
        if (strlen($microchip) !== 15) {
            throw ValidationException::withMessages([
                'microchip' => 'El microchip debe tener exactamente 15 dígitos para registrarlo en AlmaPet ID.',
            ]);
        }

        if (in_array($paciente->petpass_status, ['pending', 'registered', 'lost'], true)) {
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
                ...$this->animalPhotoPayload($paciente),
            ],
        ];

        $registerPath = (string) config('petpass.register_path', '/api/v1/handoff/register');
        $url = rtrim((string) config('petpass.base_url'), '/').$registerPath;

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

        if (! $response->successful()) {
            $message = $this->extractErrorMessage($response)
                ?? match ($response->status()) {
                    401, 403 => 'AlmaPet ID rechazó la clave de handoff (PETPASS_HANDOFF_SECRET).',
                    404 => 'Endpoint de registro no encontrado: POST '.$url,
                    422 => 'AlmaPet ID rechazó los datos del paciente.',
                    503 => 'Handoff no configurado en AlmaPet.',
                    default => 'No se pudo registrar en AlmaPet ID (HTTP '.$response->status().').',
                };

            throw ValidationException::withMessages([
                'petpass' => $message,
            ]);
        }

        $data = $this->decodeJsonBody($response);
        if ($data === null) {
            throw ValidationException::withMessages([
                'petpass' => 'AlmaPet ID no devolvió JSON válido.',
            ]);
        }

        $activateUrl = (string) ($data['activate_url'] ?? '');
        $publicCode = (string) ($data['public_code'] ?? '');
        $registrationId = $data['registration_id'] ?? null;

        if ($activateUrl === '' || $publicCode === '') {
            throw ValidationException::withMessages([
                'petpass' => 'Respuesta incompleta de AlmaPet ID (sin activate_url/public_code).',
            ]);
        }

        $paciente->forceFill([
            'petpass_status' => 'pending',
            'petpass_registration_id' => $registrationId !== null ? (string) $registrationId : $paciente->petpass_registration_id,
            'petpass_public_code' => $publicCode,
            // activate_url va por WhatsApp; el certificado solo tras el pago.
            'petpass_certificate_url' => null,
        ])->save();

        $wa = $this->sendOwnerWhatsApp(
            $tenantModel ?? Tenant::query()->findOrFail($tenant->id()),
            $paciente,
            $clinicName,
            $activateUrl,
        );

        return [
            'activate_url' => $activateUrl,
            'public_code' => $publicCode,
            'registration_id' => $registrationId,
            'whatsapp_sent' => $wa['sent'],
            'whatsapp_error' => $wa['error'],
        ];
    }

    /**
     * Empuja la foto del paciente a AlmaPet (útil si el chip ya estaba registrado).
     */
    public function syncAnimalPhoto(Paciente $paciente): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $photo = $this->animalPhotoPayload($paciente);
        if ($photo === []) {
            return false;
        }

        $tenant = current_tenant();
        if ($tenant === null) {
            return false;
        }

        $path = (string) config('petpass.sync_photo_path', '/api/v1/handoff/sync-photo');
        $url = rtrim((string) config('petpass.base_url'), '/').$path;

        try {
            $response = Http::timeout((int) config('petpass.timeout_seconds', 15))
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'X-AlmaPet-Handoff-Secret' => (string) config('petpass.handoff_secret'),
                ])
                ->post($url, [
                    'vetsaas_tenant_id' => (string) $tenant->id(),
                    'vetsaas_paciente_id' => (string) $paciente->id,
                    'public_code' => (string) ($paciente->petpass_public_code ?? ''),
                    'animal' => $photo,
                ]);
        } catch (Throwable $e) {
            Log::warning('AlmaPet sync photo failed', ['error' => $e->getMessage()]);

            return false;
        }

        return $response->successful();
    }

    /**
     * @return array{photo_base64?: string, photo_mime?: string}
     */
    private function animalPhotoPayload(Paciente $paciente): array
    {
        $path = trim((string) ($paciente->foto_path ?? ''));
        if ($path === '') {
            return [];
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return [];
        }

        $binary = $disk->get($path);
        if ($binary === null || $binary === '') {
            return [];
        }

        // Evitar payloads enormes (> ~1.8MB base64 ≈ 1.3MB archivo)
        if (strlen($binary) > 1_300_000) {
            return [];
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };

        return [
            'photo_base64' => base64_encode($binary),
            'photo_mime' => $mime,
        ];
    }

    /**
     * @return array{sent: bool, error: string|null}
     */
    private function sendOwnerWhatsApp(
        Tenant $tenant,
        Paciente $paciente,
        string $clinicName,
        string $activateUrl,
    ): array {
        $phone = preg_replace('/\D+/', '', (string) ($paciente->propietario?->telefono ?? '')) ?? '';
        if (strlen($phone) < 9) {
            return ['sent' => false, 'error' => 'El titular no tiene un teléfono válido para WhatsApp.'];
        }

        if (! str_starts_with($phone, '51') && strlen($phone) === 9) {
            $phone = '51'.$phone;
        }

        $chatId = $phone.'@c.us';
        $petName = (string) ($paciente->nombre ?: 'tu mascota');
        $support = (string) config('petpass.support_phone_display', '976 809 804');

        $message = "🐾 *AlmaPet ID*\n"
            ."Tu mascota *{$petName}* ya fue registrada por *{$clinicName}*.\n\n"
            ."✅ Estado: pendiente de activación\n"
            ."💳 Carnet digital: *S/ 25*\n"
            ."🪪 Carnet físico (opcional): *+S/ 30*\n\n"
            ."👉 Activa aquí (crea tu cuenta o inicia sesión):\n"
            ."{$activateUrl}\n\n"
            ."🛟 Soporte AlmaPet: *{$support}*";

        try {
            $session = $this->resolveReadySession($tenant);
            if ($session === null) {
                return ['sent' => false, 'error' => 'WhatsApp de la clínica no está conectado.'];
            }

            $this->whatsApp->sendTextWithDeliveryFallback($session, $chatId, $message);

            return ['sent' => true, 'error' => null];
        } catch (Throwable $e) {
            Log::warning('AlmaPet WhatsApp notify failed', [
                'tenant' => $tenant->slug,
                'error' => $e->getMessage(),
            ]);

            return ['sent' => false, 'error' => $e->getMessage()];
        }
    }

    private function resolveReadySession(Tenant $tenant): ?TenantWhatsAppSession
    {
        $session = TenantWhatsAppSession::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($session === null) {
            $session = $this->sessionSync->ensureForTenant($tenant);
        } elseif (! $session->isReady()) {
            $session = $this->sessionSync->refresh($session);
        }

        return $session instanceof TenantWhatsAppSession && $session->isReady()
            ? $session
            : null;
    }

    /**
     * @deprecated Use registerWithoutCharge
     * @return array{token: string, url: string, expires_at: string}
     */
    public function createHandoff(Paciente $paciente): array
    {
        $result = $this->registerWithoutCharge($paciente);

        return [
            'token' => '',
            'url' => $result['activate_url'],
            'expires_at' => '',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonBody(\Illuminate\Http\Client\Response $response): ?array
    {
        $fromClient = $response->json();
        if (is_array($fromClient)) {
            return $fromClient;
        }

        $raw = $response->body();
        if ($raw === '') {
            return null;
        }

        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $raw = trim($raw);

        if ($raw !== '' && ($raw[0] ?? '') !== '{') {
            $pos = strpos($raw, '{');
            if ($pos !== false) {
                $raw = substr($raw, $pos);
            }
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function extractErrorMessage(\Illuminate\Http\Client\Response $response): ?string
    {
        $payload = $this->decodeJsonBody($response);
        $message = is_array($payload) ? ($payload['message'] ?? null) : null;
        if (is_string($message) && $message !== '' && $message !== 'The given data was invalid.') {
            return $message;
        }

        $errors = is_array($payload) ? ($payload['errors'] ?? null) : null;
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
