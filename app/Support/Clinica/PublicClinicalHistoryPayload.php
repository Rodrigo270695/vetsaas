<?php

declare(strict_types=1);

namespace App\Support\Clinica;

use App\Models\ClinicSetting;
use App\Models\HistoriaClinica;
use App\Models\Paciente;
use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioLinea;
use App\Models\Tenant;
use App\Models\VacunaAplicada;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

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
        $tz = (string) config('app.timezone');
        $tenantSlug = self::tenantSlug();

        $timeline = [];

        $hc = HistoriaClinica::query()->where('paciente_id', $paciente->id)->first();
        if ($hc !== null) {
            $consultas = $hc->consultas()
                ->with([
                    'veterinario:id,name',
                    'recetas' => fn ($q) => $q->withCount('lineas')->orderByDesc('emitida_at'),
                    'pedidosLaboratorio' => fn ($q) => $q
                        ->with(['lineas' => fn ($lq) => $lq->orderBy('orden')])
                        ->withCount('lineas')
                        ->orderByDesc('solicitado_at'),
                    'cirugias' => fn ($q) => $q->orderByDesc('programada_at'),
                    'internamientos' => fn ($q) => $q->orderByDesc('ingreso_at'),
                ])
                ->orderByDesc('atendido_at')
                ->limit(200)
                ->get();

            foreach ($consultas as $c) {
                $at = $c->atendido_at;
                $timeline[] = [
                    'kind' => 'consulta',
                    'id' => $c->id,
                    'ocurrido_at' => $at->toIso8601String(),
                    'titulo' => Str::limit(trim((string) ($c->motivo ?? '')), 120) ?: '—',
                    'cerrada' => $c->cerrada_at !== null,
                    'veterinario' => $c->veterinario?->name,
                    'historia_url' => '',
                    'pdf_url' => self::signed(
                        'tenant.public.clinical-history.consulta',
                        ['consulta' => (string) $c->getKey()],
                        $tenantSlug,
                        $expiresAt,
                    ),
                    'whatsapp_url' => '',
                    'detalle' => [
                        'peso_kg' => self::trimOrNull($c->peso_kg),
                        'temperatura_c' => self::trimOrNull($c->temperatura_c),
                        'fc_lpm' => $c->fc_lpm,
                        'fr_rpm' => $c->fr_rpm,
                        'subjetivo' => self::preview($c->subjetivo, 800),
                        'objetivo' => self::preview($c->objetivo, 800),
                        'analisis' => self::preview($c->analisis, 800),
                        'plan' => self::preview($c->plan, 800),
                        'vinculos' => [
                            'recetas' => self::recetas($c->recetas),
                            'laboratorio' => self::laboratorio($c->pedidosLaboratorio, $tenantSlug, $expiresAt, $tz),
                            'cirugias' => self::cirugias($c->cirugias),
                            'internamientos' => self::internamientos($c->internamientos),
                        ],
                    ],
                ];
            }
        }

        $vacunas = VacunaAplicada::query()
            ->where('paciente_id', $paciente->id)
            ->with(['veterinario:id,name', 'consulta:id', 'producto:id,nombre,sku'])
            ->orderByDesc('aplicada_at')
            ->limit(200)
            ->get();

        foreach ($vacunas as $v) {
            $timeline[] = [
                'kind' => 'aplicacion',
                'id' => $v->id,
                'ocurrido_at' => $v->aplicada_at->toIso8601String(),
                'titulo' => $v->nombre_vacuna,
                'categoria' => $v->categoria_registro,
                'consulta_id' => $v->consulta_id,
                'veterinario' => $v->veterinario?->name,
                'vacunaciones_url' => '',
                'pdf_url' => self::signed(
                    'tenant.public.clinical-history.aplicacion',
                    ['vacuna_aplicada' => (string) $v->getKey()],
                    $tenantSlug,
                    $expiresAt,
                ),
                'detalle' => [
                    'producto_nombre' => $v->producto?->nombre,
                    'producto_sku' => $v->producto?->sku,
                    'lote' => self::trimOrNull($v->lote),
                    'numero_dosis' => $v->numero_dosis,
                    'fecha_proxima_sugerida' => $v->fecha_proxima_sugerida?->toDateString(),
                    'esquema_antigenos' => self::preview($v->esquema_antigenos, 600),
                    'notas' => self::preview($v->notas, 600),
                ],
            ];
        }

        usort($timeline, static fn (array $a, array $b): int => strcmp((string) $b['ocurrido_at'], (string) $a['ocurrido_at']));

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

    private static function trimOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trim = trim((string) $value);

        return $trim === '' ? null : $trim;
    }

    private static function preview(?string $value, int $max): ?string
    {
        $trim = self::trimOrNull($value);
        if ($trim === null) {
            return null;
        }

        return Str::limit($trim, $max);
    }

    /**
     * @return list<array{id: string, estado: string, lineas_count: number, url: string, lineas: list<array<string, mixed>>}>
     */
    private static function laboratorio($pedidos, string $tenantSlug, \DateTimeInterface $expiresAt, string $tz): array
    {
        $out = [];
        foreach ($pedidos as $p) {
            /** @var PedidoLaboratorio $p */
            $lineas = [];
            foreach ($p->lineas ?? [] as $linea) {
                /** @var PedidoLaboratorioLinea $linea */
                $archivoUrl = null;
                if (filled($linea->resultado_archivo_path)) {
                    $archivoUrl = self::signed(
                        'tenant.public.clinical-history.laboratorio-archivo',
                        ['linea' => (string) $linea->getKey()],
                        $tenantSlug,
                        $expiresAt,
                    );
                }

                $lineas[] = [
                    'id' => $linea->id,
                    'nombre_examen' => $linea->nombre_examen,
                    'resultado' => self::trimOrNull($linea->resultado),
                    'resultado_at' => $linea->resultado_at?->timezone($tz)->toDateString(),
                    'resultado_archivo_url' => $archivoUrl,
                    'resultado_archivo_original_name' => $linea->resultado_archivo_original_name,
                ];
            }

            $out[] = [
                'id' => $p->id,
                'estado' => $p->estado,
                'lineas_count' => (int) ($p->lineas_count ?? count($lineas)),
                'url' => '',
                'lineas' => $lineas,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{id: string, estado: string, lineas_count: int, url: string}>
     */
    private static function recetas($recetas): array
    {
        $out = [];
        foreach ($recetas as $r) {
            $out[] = [
                'id' => $r->id,
                'estado' => $r->estado,
                'lineas_count' => (int) ($r->lineas_count ?? 0),
                'url' => '',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{id: string, estado: string, titulo: string, url: string}>
     */
    private static function cirugias($cirugias): array
    {
        $out = [];
        foreach ($cirugias as $c) {
            $out[] = [
                'id' => $c->id,
                'estado' => $c->estado,
                'titulo' => Str::limit(trim((string) $c->nombre_procedimiento), 160) ?: '—',
                'url' => '',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{id: string, estado: string, titulo: string, url: string}>
     */
    private static function internamientos($internamientos): array
    {
        $out = [];
        foreach ($internamientos as $h) {
            $out[] = [
                'id' => $h->id,
                'estado' => $h->estado,
                'titulo' => Str::limit(trim((string) $h->motivo_ingreso), 160) ?: '—',
                'url' => '',
            ];
        }

        return $out;
    }
}
