<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhooks firmados desde AlmaPet ID.
 *
 * POST /api/webhooks/almapet
 * Header: X-AlmaPet-Signature o X-PetPass-Signature = hmac_sha256(rawBody, secret)
 */
final class AlmaPetWebhookController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $secret = (string) config('petpass.webhook_secret', '');
        if ($secret === '') {
            return response()->json(['message' => 'Webhook no configurado'], 503);
        }

        $raw = $request->getContent();
        $signature = (string) (
            $request->header('X-AlmaPet-Signature')
            ?: $request->header('X-PetPass-Signature')
            ?: ''
        );
        $expected = hash_hmac('sha256', $raw, $secret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid JSON'], 422);
        }

        $event = (string) ($payload['event'] ?? '');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        $tenantId = (string) ($data['vetsaas_tenant_id'] ?? '');
        $pacienteId = (string) ($data['vetsaas_paciente_id'] ?? '');

        if ($tenantId === '' || $pacienteId === '') {
            return response()->json(['ok' => true, 'skipped' => 'missing_ids']);
        }

        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null || blank($tenant->slug)) {
            Log::warning('AlmaPet webhook: tenant not found', ['tenant_id' => $tenantId]);

            return response()->json(['ok' => true, 'skipped' => 'tenant_missing']);
        }

        $this->tenants->runForSlug((string) $tenant->slug, function () use ($event, $data, $pacienteId): void {
            $paciente = Paciente::query()->find($pacienteId);
            if ($paciente === null) {
                Log::warning('AlmaPet webhook: paciente not found', ['paciente_id' => $pacienteId]);

                return;
            }

            match ($event) {
                'petpass.registered', 'almapet.registered' => $this->applyRegistered($paciente, $data),
                'petpass.lost', 'almapet.lost' => $this->applyLost($paciente, $data),
                'petpass.recovered', 'almapet.recovered' => $this->applyRecovered($paciente, $data),
                'petpass.paid', 'almapet.paid' => $this->applyRegistered($paciente, $data),
                default => Log::info('AlmaPet webhook ignored event', ['event' => $event]),
            };
        });

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyRegistered(Paciente $paciente, array $data): void
    {
        $paciente->forceFill([
            'petpass_status' => 'registered',
            'petpass_registration_id' => isset($data['registration_id']) ? (string) $data['registration_id'] : $paciente->petpass_registration_id,
            'petpass_public_code' => isset($data['public_code']) ? (string) $data['public_code'] : $paciente->petpass_public_code,
            'petpass_certificate_url' => isset($data['certificate_url']) ? (string) $data['certificate_url'] : $paciente->petpass_certificate_url,
            'petpass_registered_at' => now(),
            'petpass_lost_at' => null,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyLost(Paciente $paciente, array $data): void
    {
        $paciente->forceFill([
            'petpass_status' => 'lost',
            'petpass_lost_at' => now(),
            'petpass_public_code' => isset($data['public_code']) ? (string) $data['public_code'] : $paciente->petpass_public_code,
            'petpass_registration_id' => isset($data['registration_id']) ? (string) $data['registration_id'] : $paciente->petpass_registration_id,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyRecovered(Paciente $paciente, array $data): void
    {
        $paciente->forceFill([
            'petpass_status' => 'registered',
            'petpass_lost_at' => null,
            'petpass_public_code' => isset($data['public_code']) ? (string) $data['public_code'] : $paciente->petpass_public_code,
            'petpass_registration_id' => isset($data['registration_id']) ? (string) $data['registration_id'] : $paciente->petpass_registration_id,
        ])->save();
    }
}
