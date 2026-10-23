<?php

declare(strict_types=1);

namespace App\Support\Clinica;

use App\Models\Antipulga;
use App\Models\Cita;
use App\Models\Cirugia;
use App\Models\ControlClinico;
use App\Models\Defuncion;
use App\Models\Desparasitacion;
use App\Models\DocumentoAutorizacionEnvio;
use App\Models\GroomingTurno;
use App\Models\HistoriaClinica;
use App\Models\HotelEstancia;
use App\Models\Internamiento;
use App\Models\Paciente;
use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioLinea;
use App\Models\Receta;
use App\Models\RecetaLinea;
use App\Models\Triaje;
use App\Models\VacunaAplicada;
use App\Support\Tenancy\TenantModuleAccess;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

/**
 * Línea de tiempo del historial clínico para dueños (enlace firmado y portal).
 * Debe reflejar los mismos tipos de evento que ve la clínica en el paciente.
 */
final class PublicPacienteTimelineBuilder
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function build(Paciente $paciente, string $tenantSlug, \DateTimeInterface $expiresAt): array
    {
        $tz = (string) config('app.timezone');
        $timeline = [];

        self::appendConsultas($timeline, $paciente, $tenantSlug, $expiresAt, $tz);
        self::appendVacunas($timeline, $paciente, $tenantSlug, $expiresAt);
        self::appendControles($timeline, $paciente);
        self::appendDesparasitaciones($timeline, $paciente);
        self::appendAntipulgas($timeline, $paciente);
        self::appendDefunciones($timeline, $paciente);
        self::appendTriajes($timeline, $paciente);
        self::appendStandaloneEvents($timeline, $paciente, $tenantSlug, $expiresAt, $tz);
        self::appendRecetas($timeline, $paciente, $tenantSlug, $expiresAt);
        self::attachArchivos($timeline, $paciente, $tenantSlug, $expiresAt, $tz);

        usort($timeline, static fn (array $a, array $b): int => strcmp((string) $b['ocurrido_at'], (string) $a['ocurrido_at']));

        return $timeline;
    }

    /**
     * @param  list<array<string, mixed>>  $timeline
     */
    private static function appendConsultas(
        array &$timeline,
        Paciente $paciente,
        string $tenantSlug,
        \DateTimeInterface $expiresAt,
        string $tz,
    ): void {
        $hc = HistoriaClinica::query()->where('paciente_id', $paciente->id)->first();
        if ($hc === null) {
            return;
        }

        $autorizacionListo = Schema::hasTable('documento_autorizacion_envios');
        $consultaWith = [
            'veterinario:id,name',
            'examenes',
            'terapiaLineas',
            ...(Schema::hasTable('consulta_resultados') ? ['resultados'] : []),
            'recetas' => fn ($q) => $q->withCount('lineas')->orderByDesc('emitida_at'),
            'pedidosLaboratorio' => fn ($q) => $q
                ->with(['lineas' => fn ($lq) => $lq->orderBy('orden')])
                ->withCount('lineas')
                ->orderByDesc('solicitado_at'),
            'cirugias' => fn ($q) => $q->orderByDesc('programada_at'),
            'internamientos' => fn ($q) => $q->orderByDesc('ingreso_at'),
        ];
        if ($autorizacionListo) {
            $consultaWith[] = 'documentosAutorizacion';
        }
        if (Schema::hasTable('consulta_planes_tratamiento')) {
            $consultaWith[] = 'planTratamiento.lineas';
            $consultaWith[] = 'planTratamiento.seguimientos.creadoPor:id,name';
        }

        $consultas = $hc->consultas()
            ->with($consultaWith)
            ->orderByDesc('atendido_at')
            ->limit(200)
            ->get();

        foreach ($consultas as $c) {
            $at = $c->atendido_at;
            $planMedicacion = self::planMedicacion($c, $tz);
            $documentos = $autorizacionListo
                ? $c->documentosAutorizacion
                    ->filter(fn ($d) => $d->estado === DocumentoAutorizacionEnvio::ESTADO_FIRMADO)
                    ->values()
                    ->map(fn (DocumentoAutorizacionEnvio $d): array => [
                        'id' => $d->id,
                        'nombre_examen' => $d->titulo,
                        'resultado_at' => $d->firmado_at?->toIso8601String(),
                        'resultado_archivo_url' => $d->pdfUrl(),
                        'resultado_archivo_original_name' => $d->titulo.'.pdf',
                        'archivo_kind' => 'pdf',
                    ])
                    ->all()
                : [];

            $timeline[] = [
                'kind' => 'consulta',
                'id' => $c->id,
                'ocurrido_at' => $at->toIso8601String(),
                'titulo' => Str::limit(trim((string) ($c->motivo ?? '')), 120) ?: '—',
                'cerrada' => $c->cerrada_at !== null,
                'veterinario' => trim((string) ($c->medico_tratante ?? '')) !== ''
                    ? trim((string) $c->medico_tratante)
                    : $c->veterinario?->name,
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
                    'motivo' => self::preview($c->motivo, 800),
                    'subjetivo' => self::preview($c->subjetivo, 800),
                    'objetivo' => self::preview($c->objetivo, 800),
                    'analisis' => self::preview($c->analisis, 800),
                    'plan' => self::preview(
                        $c->terapiaLineas
                            ->sortBy('orden')
                            ->map(function ($linea): string {
                                $nombre = trim((string) ($linea->farmaco_nombre ?? ''));
                                $dosis = trim((string) ($linea->dosis_volumen ?? ''));
                                if ($nombre === '') {
                                    return '';
                                }

                                return $dosis !== '' ? $nombre.' — '.$dosis : $nombre;
                            })
                            ->filter()
                            ->implode("\n") ?: $c->plan,
                        800,
                    ),
                    'examenes' => $c->examenes
                        ->sortBy('orden')
                        ->pluck('nombre')
                        ->filter(fn ($n) => trim((string) $n) !== '')
                        ->values()
                        ->all(),
                    'resultados' => $c->relationLoaded('resultados')
                        ? $c->resultados
                            ->sortBy('orden')
                            ->map(fn ($resultado): array => [
                                'id' => $resultado->id,
                                'original_name' => $resultado->original_name,
                                'mime' => $resultado->mime,
                                'url' => self::signed(
                                    'tenant.public.clinical-history.consulta-resultado',
                                    [
                                        'consulta' => (string) $c->getKey(),
                                        'resultado' => (string) $resultado->getKey(),
                                    ],
                                    $tenantSlug,
                                    $expiresAt,
                                ),
                            ])
                            ->values()
                            ->all()
                        : [],
                    'anotaciones' => self::preview($c->anotaciones ?? null, 800),
                    'medico_tratante' => self::trimOrNull($c->medico_tratante),
                    'plan_medicacion' => $planMedicacion,
                    'vinculos' => [
                        'recetas' => self::recetasVinculo($c->recetas, $tenantSlug, $expiresAt),
                        'laboratorio' => self::laboratorioVinculo($c->pedidosLaboratorio, $tenantSlug, $expiresAt, $tz),
                        'cirugias' => self::cirugiasVinculo($c->cirugias),
                        'internamientos' => self::internamientosVinculo($c->internamientos),
                        'documentos_autorizacion' => $documentos,
                    ],
                ],
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $timeline
     */
    private static function appendVacunas(
        array &$timeline,
        Paciente $paciente,
        string $tenantSlug,
        \DateTimeInterface $expiresAt,
    ): void {
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
    }

    /**
     * @param  list<array<string, mixed>>  $timeline
     */
    private static function appendControles(array &$timeline, Paciente $paciente): void
    {
        if (! Schema::hasTable('controles')) {
            return;
        }

        $rows = ControlClinico::query()
            ->where('paciente_id', $paciente->id)
            ->with('veterinario:id,name')
            ->orderByDesc('atendido_at')
            ->limit(200)
            ->get();

        foreach ($rows as $row) {
            $diagnosticos = is_array($row->diagnosticos) ? $row->diagnosticos : [];
            $primero = $diagnosticos[0] ?? null;
            $corto = trim(implode(' · ', array_filter([
                is_string($row->motivo) && $row->motivo !== '' && $row->motivo !== 'Control' ? $row->motivo : null,
                is_string($primero) ? $primero : null,
            ])));

            $timeline[] = [
                'kind' => 'control',
                'id' => $row->id,
                'ocurrido_at' => $row->atendido_at->toIso8601String(),
                'titulo' => 'Control',
                'estado' => 'registrada',
                'href' => '',
                'detalle_corto' => $corto !== '' ? $corto : null,
                'resumen' => HistorialTimelineResumen::control($row),
                'veterinario' => $row->veterinario?->name,
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $timeline
     */
    private static function appendDesparasitaciones(array &$timeline, Paciente $paciente): void
    {
        if (! Schema::hasTable('desparasitaciones')) {
            return;
        }

        $rows = Desparasitacion::query()
            ->where('paciente_id', $paciente->id)
            ->with('veterinario:id,name')
            ->orderByDesc('atendido_at')
            ->limit(200)
            ->get();

        foreach ($rows as $row) {
            $aplicados = is_array($row->aplicados) ? $row->aplicados : [];
            $primero = $aplicados[0]['nombre'] ?? null;
            $proxima = $aplicados[0]['proxima_at'] ?? null;
            $corto = trim(implode(' · ', array_filter([
                is_string($primero) ? $primero : null,
                is_string($proxima) && $proxima !== '' ? 'Próxima '.$proxima : null,
            ])));

            $timeline[] = [
                'kind' => 'desparasitacion',
                'id' => $row->id,
                'ocurrido_at' => $row->atendido_at->toIso8601String(),
                'titulo' => 'Desparasitación',
                'estado' => 'registrada',
                'href' => '',
                'detalle_corto' => $corto !== '' ? $corto : null,
                'resumen' => HistorialTimelineResumen::antiparasitario($row),
                'veterinario' => $row->veterinario?->name,
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $timeline
     */
    private static function appendAntipulgas(array &$timeline, Paciente $paciente): void
    {
        if (! Schema::hasTable('antipulgas')) {
            return;
        }

        $rows = Antipulga::query()
            ->where('paciente_id', $paciente->id)
            ->with('veterinario:id,name')
            ->orderByDesc('atendido_at')
            ->limit(200)
            ->get();

        foreach ($rows as $row) {
            $aplicados = is_array($row->aplicados) ? $row->aplicados : [];
            $primero = $aplicados[0]['nombre'] ?? null;
            $proxima = $aplicados[0]['proxima_at'] ?? null;
            $corto = trim(implode(' · ', array_filter([
                is_string($primero) ? $primero : null,
                is_string($proxima) && $proxima !== '' ? 'Próxima '.$proxima : null,
            ])));

            $timeline[] = [
                'kind' => 'antipulga',
                'id' => $row->id,
                'ocurrido_at' => $row->atendido_at->toIso8601String(),
                'titulo' => 'Antipulgas',
                'estado' => 'registrada',
                'href' => '',
                'detalle_corto' => $corto !== '' ? $corto : null,
                'resumen' => HistorialTimelineResumen::antiparasitario($row),
                'veterinario' => $row->veterinario?->name,
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $timeline
     */
    private static function appendDefunciones(array &$timeline, Paciente $paciente): void
    {
        if (! Schema::hasTable('defunciones')) {
            return;
        }

        $rows = Defuncion::query()
            ->where('paciente_id', $paciente->id)
            ->with('veterinario:id,name')
            ->orderByDesc('ocurrido_at')
            ->limit(50)
            ->get();

        foreach ($rows as $row) {
            $corto = trim(implode(' · ', array_filter([$row->motivo, $row->sitio])));

            $timeline[] = [
                'kind' => 'defuncion',
                'id' => $row->id,
                'ocurrido_at' => $row->ocurrido_at->toIso8601String(),
                'titulo' => 'Defunción',
                'estado' => 'registrada',
                'href' => '',
                'detalle_corto' => $corto !== '' ? $corto : null,
                'resumen' => HistorialTimelineResumen::defuncion($row),
                'veterinario' => $row->veterinario?->name,
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $timeline
     */
    private static function appendTriajes(array &$timeline, Paciente $paciente): void
    {
        if (! Schema::hasTable('triajes')) {
            return;
        }

        $rows = Triaje::query()
            ->where('paciente_id', $paciente->id)
            ->with('veterinario:id,name')
            ->orderByDesc('atendido_at')
            ->limit(200)
            ->get();

        foreach ($rows as $row) {
            $hallazgos = is_array($row->hallazgos) ? $row->hallazgos : [];

            $timeline[] = [
                'kind' => 'triaje',
                'id' => $row->id,
                'ocurrido_at' => $row->atendido_at->toIso8601String(),
                'titulo' => 'Triaje',
                'estado' => 'registrada',
                'href' => '',
                'detalle_corto' => TriajeCatalogo::resumen($hallazgos),
                'resumen' => HistorialTimelineResumen::triaje(
                    $hallazgos,
                    is_array($row->recomendaciones) ? $row->recomendaciones : [],
                    $row,
                ),
                'veterinario' => $row->veterinario?->name,
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $timeline
     */
    private static function appendStandaloneEvents(
        array &$timeline,
        Paciente $paciente,
        string $tenantSlug,
        \DateTimeInterface $expiresAt,
        string $tz,
    ): void {
        try {
            self::appendStandaloneEventsUnsafe($timeline, $paciente, $tenantSlug, $expiresAt, $tz);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $timeline
     */
    private static function appendStandaloneEventsUnsafe(
        array &$timeline,
        Paciente $paciente,
        string $tenantSlug,
        \DateTimeInterface $expiresAt,
        string $tz,
    ): void {
        $tenant = app(TenantManager::class)->current()?->tenant;

        if (Schema::hasTable('pedidos_laboratorio') && TenantModuleAccess::isEnabled($tenant, 'laboratorio')) {
            $pedidos = PedidoLaboratorio::query()
                ->where('paciente_id', $paciente->id)
                ->whereNull('consulta_id')
                ->when(
                    Schema::hasColumn('pedidos_laboratorio', 'origen_id'),
                    fn ($q) => $q->whereNull('origen_id'),
                )
                ->where('estado', '!=', PedidoLaboratorio::ESTADO_CANCELADO)
                ->with([
                    'lineas' => fn ($q) => $q->orderBy('orden'),
                    'veterinario:id,name',
                ])
                ->withCount('lineas')
                ->orderByDesc('solicitado_at')
                ->limit(100)
                ->get();

            foreach ($pedidos as $p) {
                $at = $p->solicitado_at;
                if ($at === null) {
                    continue;
                }

                $event = self::eventRow(
                    'laboratorio',
                    (string) $p->id,
                    $at->toIso8601String(),
                    $p->laboratorio_destino
                        ? Str::limit(trim((string) $p->laboratorio_destino), 120)
                        : 'Laboratorio',
                    (string) $p->estado,
                    $p->lineas_count > 0 ? ((int) $p->lineas_count).' examen(es)' : null,
                );
                $event['resumen'] = HistorialTimelineResumen::laboratorio($p);
                $event['veterinario'] = $p->veterinario?->name;
                $lineas = self::laboratorioVinculo(collect([$p]), $tenantSlug, $expiresAt, $tz);
                $archivos = self::archivosFromLabLineas($lineas[0]['lineas'] ?? []);
                if ($archivos !== []) {
                    $event['archivos'] = $archivos;
                }
                $timeline[] = $event;
            }
        }

        if (Schema::hasTable('cirugias') && TenantModuleAccess::isEnabled($tenant, 'cirugias')) {
            $cirugias = Cirugia::query()
                ->where('paciente_id', $paciente->id)
                ->whereNull('consulta_id')
                ->where('estado', '!=', Cirugia::ESTADO_CANCELADA)
                ->with('veterinario:id,name')
                ->orderByDesc('programada_at')
                ->limit(100)
                ->get();

            foreach ($cirugias as $c) {
                $event = self::eventRow(
                    'cirugia',
                    (string) $c->id,
                    $c->programada_at->toIso8601String(),
                    Str::limit(trim((string) $c->nombre_procedimiento), 120) ?: 'Cirugía',
                    (string) $c->estado,
                    self::preview($c->observaciones, 160),
                );
                $event['resumen'] = HistorialTimelineResumen::cirugia($c);
                $event['veterinario'] = $c->veterinario?->name;
                $timeline[] = $event;
            }
        }

        if (Schema::hasTable('internamientos') && TenantModuleAccess::isEnabled($tenant, 'hospitalizacion')) {
            $internamientos = Internamiento::query()
                ->where('paciente_id', $paciente->id)
                ->whereNull('consulta_id')
                ->where('estado', '!=', Internamiento::ESTADO_CANCELADO)
                ->with('veterinario:id,name')
                ->orderByDesc('ingreso_at')
                ->limit(100)
                ->get();

            foreach ($internamientos as $i) {
                if ($i->ingreso_at === null) {
                    continue;
                }
                $event = self::eventRow(
                    'internamiento',
                    (string) $i->id,
                    $i->ingreso_at->toIso8601String(),
                    Str::limit(trim((string) $i->motivo_ingreso), 120) ?: 'Hospitalización',
                    (string) $i->estado,
                    self::preview($i->ubicacion, 120),
                );
                $event['resumen'] = HistorialTimelineResumen::internamiento($i, $tz);
                $event['veterinario'] = $i->veterinario?->name;
                $timeline[] = $event;
            }
        }

        if (Schema::hasTable('grooming_turnos') && TenantModuleAccess::isEnabled($tenant, 'grooming')) {
            $turnos = GroomingTurno::query()
                ->where('paciente_id', $paciente->id)
                ->whereNotIn('estado', [GroomingTurno::ESTADO_CANCELADA, GroomingTurno::ESTADO_NO_ASISTIO])
                ->with(['groomingServicio', 'responsable:id,name'])
                ->orderByDesc('inicio_at')
                ->limit(100)
                ->get();

            foreach ($turnos as $t) {
                if ($t->inicio_at === null) {
                    continue;
                }
                $titulo = trim((string) $t->servicio_label);
                $event = self::eventRow(
                    'grooming',
                    (string) $t->id,
                    $t->inicio_at->toIso8601String(),
                    $titulo !== '' ? Str::limit($titulo, 120) : 'Grooming',
                    (string) $t->estado,
                    self::preview($t->notas, 160),
                );
                $event['resumen'] = HistorialTimelineResumen::grooming($t);
                $event['veterinario'] = $t->responsable?->name;
                $timeline[] = $event;
            }
        }

        if (Schema::hasTable('hotel_estancias') && TenantModuleAccess::isEnabled($tenant, 'hotel')) {
            $estancias = HotelEstancia::query()
                ->where('paciente_id', $paciente->id)
                ->whereNotIn('estado', [HotelEstancia::ESTADO_CANCELADA, HotelEstancia::ESTADO_NO_PRESENTO])
                ->with('responsable:id,name')
                ->orderByDesc('ingreso_at')
                ->limit(100)
                ->get();

            foreach ($estancias as $e) {
                if ($e->ingreso_at === null) {
                    continue;
                }
                $titulo = trim((string) ($e->tipo_detalle ?: $e->tipo_estancia));
                $event = self::eventRow(
                    'hotel',
                    (string) $e->id,
                    $e->ingreso_at->toIso8601String(),
                    $titulo !== '' ? Str::limit($titulo, 120) : 'Hotel',
                    (string) $e->estado,
                    self::preview($e->notas, 160),
                );
                $event['resumen'] = HistorialTimelineResumen::hotel($e, $tz);
                $event['veterinario'] = $e->responsable?->name;
                $timeline[] = $event;
            }
        }

        if (Schema::hasTable('citas') && TenantModuleAccess::isEnabled($tenant, 'citas')) {
            $citas = Cita::query()
                ->where('paciente_id', $paciente->id)
                ->with('veterinario:id,name')
                ->orderByDesc('inicio_at')
                ->limit(100)
                ->get();

            foreach ($citas as $cita) {
                if ($cita->inicio_at === null) {
                    continue;
                }
                $titulo = trim((string) ($cita->motivo ?? ''));
                $event = self::eventRow(
                    'cita',
                    (string) $cita->id,
                    $cita->inicio_at->toIso8601String(),
                    $titulo !== '' ? Str::limit($titulo, 120) : 'Cita',
                    (string) $cita->estado,
                    self::preview($cita->notas, 160),
                );
                $event['resumen'] = HistorialTimelineResumen::cita($cita);
                $event['veterinario'] = $cita->veterinario?->name;
                $timeline[] = $event;
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $timeline
     */
    private static function appendRecetas(
        array &$timeline,
        Paciente $paciente,
        string $tenantSlug,
        \DateTimeInterface $expiresAt,
    ): void {
        if (! Schema::hasTable('recetas')) {
            return;
        }

        $tenant = app(TenantManager::class)->current()?->tenant;
        if (! TenantModuleAccess::isEnabled($tenant, 'recetas')) {
            return;
        }

        $recetas = Receta::query()
            ->where('paciente_id', $paciente->id)
            ->with([
                'lineas' => fn ($q) => $q->orderBy('orden'),
                'veterinario:id,name',
            ])
            ->orderByDesc('emitida_at')
            ->limit(200)
            ->get();

        foreach ($recetas as $r) {
            $nombres = $r->lineas
                ->pluck('nombre_medicamento')
                ->map(fn ($n) => trim((string) $n))
                ->filter()
                ->values();
            $titulo = $nombres->isNotEmpty()
                ? Str::limit($nombres->implode(', '), 140)
                : 'Receta';

            $timeline[] = [
                'kind' => 'receta',
                'id' => $r->id,
                'ocurrido_at' => $r->emitida_at->toIso8601String(),
                'titulo' => $titulo,
                'estado' => $r->estado,
                'veterinario' => $r->veterinario?->name,
                'consulta_id' => $r->consulta_id,
                'pdf_url' => self::signed(
                    'tenant.public.clinical-history.receta',
                    ['receta' => (string) $r->getKey()],
                    $tenantSlug,
                    $expiresAt,
                ),
                'puede_editar' => false,
                'lineas' => $r->lineas->map(fn (RecetaLinea $ln): array => [
                    'id' => $ln->id,
                    'nombre_medicamento' => $ln->nombre_medicamento,
                    'posologia' => $ln->posologia,
                    'duracion_dias' => $ln->duracion_dias,
                    'instrucciones' => $ln->instrucciones,
                ])->all(),
                'observaciones' => self::preview($r->observaciones, 600),
                'examenes_complementarios' => self::preview($r->examenes_complementarios, 400),
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $timeline
     */
    private static function attachArchivos(
        array &$timeline,
        Paciente $paciente,
        string $tenantSlug,
        \DateTimeInterface $expiresAt,
        string $tz,
    ): void {
        if (! Schema::hasColumn('pedidos_laboratorio', 'origen_id')) {
            return;
        }

        $pedidos = PedidoLaboratorio::query()
            ->where('paciente_id', $paciente->id)
            ->whereNotNull('origen_id')
            ->with(['lineas' => fn ($q) => $q->orderBy('orden')])
            ->get();

        $mapa = [];
        foreach ($pedidos as $pedido) {
            $key = $pedido->origen_kind.':'.$pedido->origen_id;
            foreach ($pedido->lineas as $linea) {
                if (! filled($linea->resultado_archivo_path)) {
                    continue;
                }

                $mapa[$key][] = [
                    'id' => $linea->id,
                    'nombre_examen' => $linea->nombre_examen,
                    'resultado' => self::trimOrNull($linea->resultado),
                    'resultado_at' => $linea->resultado_at?->timezone($tz)->toDateString(),
                    'resultado_archivo_url' => self::signed(
                        'tenant.public.clinical-history.laboratorio-archivo',
                        ['linea' => (string) $linea->getKey()],
                        $tenantSlug,
                        $expiresAt,
                    ),
                    'resultado_archivo_original_name' => $linea->resultado_archivo_original_name,
                    'archivo_kind' => self::archivoKind(
                        $linea->resultado_archivo_original_name,
                        $linea->resultado_archivo_path,
                    ),
                ];
            }
        }

        foreach ($timeline as &$row) {
            $key = ($row['kind'] ?? '').':'.($row['id'] ?? '');
            if (isset($mapa[$key])) {
                $row['archivos'] = $mapa[$key];
            }
        }
        unset($row);
    }

    /**
     * @return array{kind: string, id: string, ocurrido_at: string, titulo: string, estado: string, href: string, detalle_corto: ?string}
     */
    private static function eventRow(
        string $kind,
        string $id,
        string $ocurridoAt,
        string $titulo,
        string $estado,
        ?string $detalleCorto,
    ): array {
        return [
            'kind' => $kind,
            'id' => $id,
            'ocurrido_at' => $ocurridoAt,
            'titulo' => $titulo,
            'estado' => $estado,
            'href' => '',
            'detalle_corto' => $detalleCorto,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private static function archivosFromLabLineas(array $lineas): array
    {
        return array_values(array_filter(
            $lineas,
            static fn (array $linea): bool => filled($linea['resultado_archivo_url'] ?? null),
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function planMedicacion($consulta, string $tz): ?array
    {
        if (! $consulta->relationLoaded('planTratamiento') || $consulta->planTratamiento === null) {
            return null;
        }

        $plan = $consulta->planTratamiento;
        $lineas = $plan->relationLoaded('lineas') ? $plan->lineas : collect();
        $seguimientos = $plan->relationLoaded('seguimientos') ? $plan->seguimientos : collect();

        return [
            'fecha_inicio' => $plan->fecha_inicio?->format('d/m/Y'),
            'fecha_fin' => $plan->fecha_fin?->format('d/m/Y'),
            'estado' => (string) $plan->estado,
            'indicaciones' => self::preview($plan->indicaciones, 800),
            'lineas' => $lineas
                ->map(function ($ln): array {
                    $medicamento = trim((string) ($ln->medicamento ?? ''));
                    $cantidad = $ln->cantidad !== null ? trim((string) $ln->cantidad) : '';

                    return [
                        'id' => (string) $ln->id,
                        'medicamento' => $medicamento,
                        'dosis' => self::preview(isset($ln->dosis) ? (string) $ln->dosis : null, 80),
                        'unidad' => self::preview(isset($ln->unidad) ? (string) $ln->unidad : null, 40),
                        'via' => self::preview(isset($ln->via) ? (string) $ln->via : null, 40),
                        'frecuencia' => self::preview(isset($ln->frecuencia) ? (string) $ln->frecuencia : null, 80),
                        'cantidad' => $cantidad !== '' ? $cantidad : null,
                        'notas' => self::preview(isset($ln->notas) ? (string) $ln->notas : null, 300),
                    ];
                })
                ->filter(fn (array $ln): bool => $ln['medicamento'] !== '')
                ->values()
                ->all(),
            'seguimientos' => $seguimientos
                ->map(function ($seg) use ($tz): array {
                    return [
                        'id' => (string) $seg->id,
                        'registrado_at' => $seg->registrado_at?->timezone($tz)->format('d/m/Y H:i'),
                        'nota' => trim((string) $seg->nota),
                        'autor' => trim((string) ($seg->creadoPor?->name ?? '')) ?: null,
                    ];
                })
                ->filter(fn (array $seg): bool => $seg['nota'] !== '')
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array{id: string, estado: string, lineas_count: int, url: string, pdf_url?: string}>
     */
    private static function recetasVinculo($recetas, string $tenantSlug, \DateTimeInterface $expiresAt): array
    {
        $out = [];
        foreach ($recetas as $r) {
            $out[] = [
                'id' => $r->id,
                'estado' => $r->estado,
                'lineas_count' => (int) ($r->lineas_count ?? 0),
                'url' => '',
                'pdf_url' => self::signed(
                    'tenant.public.clinical-history.receta',
                    ['receta' => (string) $r->getKey()],
                    $tenantSlug,
                    $expiresAt,
                ),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function laboratorioVinculo($pedidos, string $tenantSlug, \DateTimeInterface $expiresAt, string $tz): array
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
                    'archivo_kind' => self::archivoKind(
                        $linea->resultado_archivo_original_name,
                        $linea->resultado_archivo_path,
                    ),
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
     * @return list<array{id: string, estado: string, titulo: string, url: string}>
     */
    private static function cirugiasVinculo($cirugias): array
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
    private static function internamientosVinculo($internamientos): array
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

    /**
     * @return 'pdf'|'image'|'other'
     */
    private static function archivoKind(?string $originalName, ?string $path): string
    {
        $candidate = strtolower((string) ($originalName ?: $path ?: ''));
        $ext = pathinfo($candidate, PATHINFO_EXTENSION);

        if ($ext === 'pdf') {
            return 'pdf';
        }

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return 'image';
        }

        return 'other';
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
}
