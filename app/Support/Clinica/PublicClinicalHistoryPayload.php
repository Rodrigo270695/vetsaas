<?php

declare(strict_types=1);

namespace App\Support\Clinica;

use App\Models\ClinicSetting;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Support\Facades\URL;

/**
 * Payload Inertia para la vista pública read-only del historial clínico.
 */
final class PublicClinicalHistoryPayload
{
    /**
     * @return array{
     *     clinic: array{nombre: string, logo_url: string|null},
     *     paciente: Paciente,
     *     propietario_nombre: string,
     *     timeline: list<array<string, mixed>>,
     *     links: array{historial_pdf: string},
     *     expires_at: string,
     *     permisos: array{consultas_ver: bool, vacunas_ver: bool, consultas_crear: bool, vacunas_crear: bool, laboratorio_crear: bool}
     * }
     */
    public static function forPaciente(Paciente $paciente): array
    {
        $paciente->loadMissing(['propietario:id,nombres,apellidos,razon_social,telefono']);

        $ttlMinutes = max(5, (int) config('clinic-documents.public_link_ttl_minutes', 10080));
        $expiresAt = now()->addMinutes($ttlMinutes);
        $tenantSlug = self::tenantSlug();

        $timeline = PublicPacienteTimelineBuilder::build($paciente, $tenantSlug, $expiresAt);

        $clinic = ClinicSetting::current();
        $clinicName = trim((string) ($clinic->nombre_comercial ?: $clinic->razon_social))
            ?: (string) config('app.name', 'Clínica veterinaria');

        return [
            'clinic' => [
                'nombre' => $clinicName,
                'logo_url' => $clinic->logo_url ?? null,
            ],
            'paciente' => $paciente,
            'propietario_nombre' => self::propietarioNombre($paciente),
            'timeline' => $timeline,
            'links' => [
                'historial_pdf' => self::signed(
                    'tenant.public.clinical-history.historial',
                    ['paciente' => (string) $paciente->getKey()],
                    $tenantSlug,
                    $expiresAt,
                ),
            ],
            'expires_at' => $expiresAt->toIso8601String(),
            'permisos' => [
                'consultas_ver' => true,
                'vacunas_ver' => true,
                'consultas_crear' => false,
                'vacunas_crear' => false,
                'laboratorio_crear' => false,
            ],
        ];
    }

    private static function tenantSlug(): string
    {
        $tenantId = tenant_id();
        $tenant = $tenantId !== null ? Tenant::query()->find($tenantId) : null;
        $slug = $tenant?->slug;

        abort_unless(is_string($slug) && $slug !== '', 404);

        return $slug;
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private static function signed(string $routeName, array $parameters, string $tenantSlug, \DateTimeInterface $expiresAt): string
    {
        return URL::temporarySignedRoute(
            $routeName,
            $expiresAt,
            [
                'tenant_subdomain' => $tenantSlug,
                ...$parameters,
            ],
        );
    }

    private static function propietarioNombre(Paciente $paciente): string
    {
        $p = $paciente->propietario;
        if ($p === null) {
            return '—';
        }

        if (filled($p->razon_social)) {
            return (string) $p->razon_social;
        }

        $name = trim(implode(' ', array_filter([(string) $p->nombres, (string) $p->apellidos])));

        return $name !== '' ? $name : '—';
    }
}
