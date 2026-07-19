<?php

declare(strict_types=1);

namespace App\Services\InAppAssistant;

use App\Models\CajaSesion;
use App\Models\Cita;
use App\Models\Consulta;
use App\Models\ExistenciaSede;
use App\Models\HistoriaClinica;
use App\Models\Paciente;
use App\Models\PedidoLaboratorio;
use App\Models\Producto;
use App\Models\Propietario;
use App\Models\Sede;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VacunaAplicada;
use App\Models\Venta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

final class InAppAssistantToolExecutor
{
    /** @var array{url?: string, component?: string, paciente_id?: string, scope?: string}|null */
    private ?array $pageContext = null;

    private string $scope = 'clinic';

    /** @var list<array{type: string, url: string, label: string}> */
    private array $pendingUiActions = [];

    /**
     * @param  array{url?: string, component?: string, paciente_id?: string, scope?: string}|null  $pageContext
     */
    public function setPageContext(?array $pageContext): void
    {
        $this->pageContext = $pageContext;
        $this->pendingUiActions = [];
        $scope = is_string($pageContext['scope'] ?? null) ? (string) $pageContext['scope'] : 'clinic';
        $this->scope = $scope === 'platform' ? 'platform' : 'clinic';
    }

    public function scope(): string
    {
        return $this->scope;
    }

    /**
     * @return list<array{type: string, url: string, label: string}>
     */
    public function pullUiActions(): array
    {
        $actions = $this->pendingUiActions;
        $this->pendingUiActions = [];

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function execute(string $name, array $args): string
    {
        if ($this->scope === 'platform') {
            $result = match ($name) {
                'cobros_pendientes' => $this->cobrosPendientes((int) ($args['limite'] ?? 20)),
                'cobros_fallidos' => $this->cobrosFallidos(
                    (int) ($args['dias'] ?? 14),
                    (int) ($args['limite'] ?? 20),
                ),
                'suscripciones_en_riesgo' => $this->suscripcionesEnRiesgo((int) ($args['dias_proximo_cobro'] ?? 7)),
                'resumen_plataforma' => $this->resumenPlataforma(),
                'buscar_clinicas' => $this->buscarClinicas((string) ($args['q'] ?? '')),
                'resolver_navegacion_plataforma' => $this->resolverNavegacionPlataforma((string) ($args['destino'] ?? '')),
                default => ['ok' => false, 'error' => 'Herramienta no disponible en el portal de plataforma.'],
            };

            return (string) json_encode($result, JSON_UNESCAPED_UNICODE);
        }

        $result = match ($name) {
            'buscar_pacientes' => $this->buscarPacientes((string) ($args['q'] ?? '')),
            'buscar_propietarios' => $this->buscarPropietarios((string) ($args['q'] ?? '')),
            'buscar_productos' => $this->buscarProductos((string) ($args['q'] ?? '')),
            'resumen_operativo' => $this->resumenOperativo(),
            'alertas_operativas' => $this->alertasOperativas((int) ($args['dias'] ?? 14)),
            'paciente_en_contexto' => $this->pacienteEnContexto(),
            'resolver_navegacion' => $this->resolverNavegacion((string) ($args['destino'] ?? '')),
            'resumen_historia_paciente' => $this->resumenHistoriaPaciente(
                isset($args['paciente_id']) ? (string) $args['paciente_id'] : null,
                (int) ($args['limite'] ?? 5),
            ),
            'agenda_citas' => $this->agendaCitas(
                isset($args['fecha']) ? (string) $args['fecha'] : 'hoy',
                isset($args['veterinario']) ? (string) $args['veterinario'] : null,
                isset($args['sede']) ? (string) $args['sede'] : null,
            ),
            default => ['ok' => false, 'error' => 'Herramienta no disponible.'],
        };

        return (string) json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    private function buscarPacientes(string $q): array
    {
        $q = trim($q);
        if ($q === '' || mb_strlen($q) < 2) {
            return ['ok' => false, 'error' => 'Indica al menos 2 caracteres para buscar.'];
        }

        if (! Schema::hasTable('pacientes')) {
            return ['ok' => false, 'error' => 'Módulo de pacientes no disponible.'];
        }

        $like = '%'.addcslashes($q, '%_\\').'%';
        $rows = Paciente::query()
            ->with('propietario:id,nombres,apellidos,razon_social,telefono')
            ->where(function ($query) use ($like): void {
                $query->where('nombre', 'ILIKE', $like)
                    ->orWhere('microchip', 'ILIKE', $like)
                    ->orWhereHas('propietario', function ($p) use ($like): void {
                        $p->where('nombres', 'ILIKE', $like)
                            ->orWhere('apellidos', 'ILIKE', $like)
                            ->orWhere('razon_social', 'ILIKE', $like)
                            ->orWhere('telefono', 'ILIKE', $like);
                    });
            })
            ->orderBy('nombre')
            ->limit(8)
            ->get(['id', 'nombre', 'especie', 'raza', 'propietario_id', 'activo']);

        return [
            'ok' => true,
            'count' => $rows->count(),
            'pacientes' => $rows->map(static function (Paciente $p): array {
                $titular = $p->propietario?->razon_social
                    ?: trim(implode(' ', array_filter([(string) $p->propietario?->nombres, (string) $p->propietario?->apellidos])));

                return [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'especie' => $p->especie,
                    'raza' => $p->raza,
                    'activo' => $p->activo,
                    'titular' => $titular !== '' ? $titular : null,
                    'telefono_titular' => $p->propietario?->telefono,
                    'url' => '/clinica/pacientes/'.$p->id,
                ];
            })->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buscarPropietarios(string $q): array
    {
        $q = trim($q);
        if ($q === '' || mb_strlen($q) < 2) {
            return ['ok' => false, 'error' => 'Indica al menos 2 caracteres para buscar.'];
        }

        if (! Schema::hasTable('propietarios')) {
            return ['ok' => false, 'error' => 'Módulo de propietarios no disponible.'];
        }

        $like = '%'.addcslashes($q, '%_\\').'%';
        $rows = Propietario::query()
            ->where(function ($query) use ($like): void {
                $query->where('nombres', 'ILIKE', $like)
                    ->orWhere('apellidos', 'ILIKE', $like)
                    ->orWhere('razon_social', 'ILIKE', $like)
                    ->orWhere('documento', 'ILIKE', $like)
                    ->orWhere('telefono', 'ILIKE', $like)
                    ->orWhere('email', 'ILIKE', $like);
            })
            ->orderBy('nombres')
            ->limit(8)
            ->get(['id', 'nombres', 'apellidos', 'razon_social', 'telefono', 'documento']);

        return [
            'ok' => true,
            'count' => $rows->count(),
            'propietarios' => $rows->map(static fn (Propietario $p): array => [
                'id' => $p->id,
                'nombre' => $p->razon_social
                    ?: trim(implode(' ', array_filter([(string) $p->nombres, (string) $p->apellidos]))),
                'telefono' => $p->telefono,
                'documento' => $p->documento,
                'url' => '/clinica/propietarios/'.$p->id,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buscarProductos(string $q): array
    {
        $q = trim($q);
        if ($q === '' || mb_strlen($q) < 2) {
            return ['ok' => false, 'error' => 'Indica al menos 2 caracteres para buscar.'];
        }

        if (! Schema::hasTable('productos')) {
            return ['ok' => false, 'error' => 'Inventario no disponible.'];
        }

        $like = '%'.addcslashes($q, '%_\\').'%';
        $rows = Producto::query()
            ->where(function ($query) use ($like): void {
                $query->where('nombre', 'ILIKE', $like)
                    ->orWhere('sku', 'ILIKE', $like)
                    ->orWhere('slug', 'ILIKE', $like);
            })
            ->orderBy('nombre')
            ->limit(8)
            ->get(['id', 'nombre', 'sku', 'precio_venta', 'unidad', 'activo']);

        return [
            'ok' => true,
            'count' => $rows->count(),
            'productos' => $rows->map(static fn (Producto $p): array => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'sku' => $p->sku,
                'precio_venta' => $p->precio_venta !== null ? (string) $p->precio_venta : null,
                'unidad' => $p->unidad,
                'activo' => $p->activo,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resumenOperativo(): array
    {
        $tz = (string) config('app.timezone', 'America/Lima');
        $hoy = Carbon::now($tz)->toDateString();
        $out = [
            'ok' => true,
            'fecha' => $hoy,
            'zona_horaria' => $tz,
        ];

        if (Schema::hasTable('citas')) {
            $out['citas_hoy'] = Cita::query()
                ->whereDate('inicio_at', $hoy)
                ->count();
        }

        if (Schema::hasTable('ventas')) {
            $ventasHoy = Venta::query()
                ->where(function ($q) use ($hoy): void {
                    $q->whereDate('fecha_pago', $hoy)
                        ->orWhere(function ($inner) use ($hoy): void {
                            $inner->whereNull('fecha_pago')->whereDate('created_at', $hoy);
                        });
                })
                ->whereNull('anulado_at');

            $out['ventas_hoy'] = [
                'cantidad' => (clone $ventasHoy)->count(),
                'total' => (string) ((clone $ventasHoy)->sum('total') ?? 0),
            ];
        }

        $alertas = $this->alertasOperativas(14);
        if (($alertas['ok'] ?? false) === true) {
            $out['alertas'] = [
                'stock_bajo_count' => $alertas['stock_bajo']['count'] ?? 0,
                'vacunas_proximas_count' => $alertas['vacunas_proximas']['count'] ?? 0,
                'cajas_abiertas_count' => $alertas['caja']['abiertas_count'] ?? 0,
                'usuario_tiene_caja_abierta' => $alertas['caja']['usuario_tiene_abierta'] ?? false,
            ];
        }

        if (Schema::hasTable('pacientes')) {
            $out['pacientes_activos'] = Paciente::query()->where('activo', true)->count();
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function alertasOperativas(int $dias): array
    {
        $dias = max(1, min(60, $dias));
        $tz = (string) config('app.timezone', 'America/Lima');
        $hoy = Carbon::now($tz)->startOfDay();
        $hasta = $hoy->copy()->addDays($dias);

        $out = [
            'ok' => true,
            'ventana_dias' => $dias,
            'desde' => $hoy->toDateString(),
            'hasta' => $hasta->toDateString(),
        ];

        if (Schema::hasTable('vacunas_aplicadas')) {
            $rows = VacunaAplicada::query()
                ->with('paciente:id,nombre')
                ->whereNotNull('fecha_proxima_sugerida')
                ->whereDate('fecha_proxima_sugerida', '>=', $hoy->toDateString())
                ->whereDate('fecha_proxima_sugerida', '<=', $hasta->toDateString())
                ->orderBy('fecha_proxima_sugerida')
                ->limit(12)
                ->get(['id', 'paciente_id', 'nombre_vacuna', 'categoria_registro', 'fecha_proxima_sugerida', 'numero_dosis']);

            $out['vacunas_proximas'] = [
                'count' => $rows->count(),
                'items' => $rows->map(static fn (VacunaAplicada $v): array => [
                    'paciente' => $v->paciente?->nombre,
                    'paciente_id' => $v->paciente_id,
                    'vacuna' => $v->nombre_vacuna,
                    'categoria' => $v->categoria_registro,
                    'proxima' => optional($v->fecha_proxima_sugerida)?->toDateString(),
                    'dosis' => $v->numero_dosis,
                    'url' => $v->paciente_id ? '/clinica/pacientes/'.$v->paciente_id : null,
                ])->all(),
            ];
        }

        if (Schema::hasTable('existencias_sede') && Schema::hasTable('productos')) {
            $alertas = ExistenciaSede::query()
                ->with('producto:id,nombre,sku,stock_minimo')
                ->whereHas('producto', fn ($q) => $q->where('activo', true)->where('stock_minimo', '>', 0))
                ->get()
                ->filter(function (ExistenciaSede $e): bool {
                    $min = (float) ($e->producto?->stock_minimo ?? 0);

                    return $min > 0 && (float) $e->cantidad <= $min;
                })
                ->take(8)
                ->map(static fn (ExistenciaSede $e): array => [
                    'producto' => $e->producto?->nombre,
                    'sku' => $e->producto?->sku,
                    'cantidad' => (string) $e->cantidad,
                    'stock_minimo' => (string) ($e->producto?->stock_minimo ?? 0),
                ])
                ->values()
                ->all();

            $out['stock_bajo'] = [
                'count' => count($alertas),
                'items' => $alertas,
            ];
        }

        if (Schema::hasTable('caja_sesiones')) {
            $abiertas = CajaSesion::query()
                ->where('estado', CajaSesion::ESTADO_ABIERTA)
                ->orderByDesc('opened_at')
                ->limit(8)
                ->get(['id', 'sede_id', 'opened_at', 'opened_by_id', 'moneda', 'saldo_apertura']);

            $sedeIds = $abiertas->pluck('sede_id')->filter()->unique()->values()->all();
            $sedes = Schema::hasTable('sedes') && $sedeIds !== []
                ? Sede::query()->whereIn('id', $sedeIds)->pluck('nombre', 'id')
                : collect();

            $userId = Auth::id();
            $mias = $abiertas->first(static fn (CajaSesion $s): bool => (string) $s->opened_by_id === (string) $userId);

            $out['caja'] = [
                'abiertas_count' => $abiertas->count(),
                'usuario_tiene_abierta' => $mias !== null,
                'mi_sesion' => $mias === null ? null : [
                    'sede' => $sedes[$mias->sede_id] ?? null,
                    'opened_at' => optional($mias->opened_at)?->toDateTimeString(),
                    'saldo_apertura' => (string) $mias->saldo_apertura,
                    'moneda' => $mias->moneda,
                ],
                'sesiones' => $abiertas->map(static fn (CajaSesion $s): array => [
                    'sede' => $sedes[$s->sede_id] ?? null,
                    'opened_at' => optional($s->opened_at)?->toDateTimeString(),
                    'es_mia' => (string) $s->opened_by_id === (string) $userId,
                ])->all(),
                'url' => '/caja/sesiones',
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function pacienteEnContexto(): array
    {
        $pacienteId = trim((string) ($this->pageContext['paciente_id'] ?? ''));
        if ($pacienteId === '') {
            return [
                'ok' => false,
                'error' => 'No hay un paciente en la pantalla actual. Abre el historial de un paciente o indícame el nombre.',
            ];
        }

        if (! Schema::hasTable('pacientes')) {
            return ['ok' => false, 'error' => 'Módulo de pacientes no disponible.'];
        }

        $p = Paciente::query()
            ->with('propietario:id,nombres,apellidos,razon_social,telefono,documento')
            ->find($pacienteId, ['id', 'nombre', 'especie', 'raza', 'sexo', 'activo', 'microchip', 'propietario_id', 'fecha_nacimiento']);

        if ($p === null) {
            return ['ok' => false, 'error' => 'No encontré ese paciente.'];
        }

        $titular = $p->propietario?->razon_social
            ?: trim(implode(' ', array_filter([(string) $p->propietario?->nombres, (string) $p->propietario?->apellidos])));

        $vacunasProximas = [];
        if (Schema::hasTable('vacunas_aplicadas')) {
            $tz = (string) config('app.timezone', 'America/Lima');
            $hoy = Carbon::now($tz)->toDateString();
            $vacunasProximas = VacunaAplicada::query()
                ->where('paciente_id', $p->id)
                ->whereNotNull('fecha_proxima_sugerida')
                ->whereDate('fecha_proxima_sugerida', '>=', $hoy)
                ->orderBy('fecha_proxima_sugerida')
                ->limit(5)
                ->get(['nombre_vacuna', 'fecha_proxima_sugerida', 'categoria_registro', 'numero_dosis'])
                ->map(static fn (VacunaAplicada $v): array => [
                    'vacuna' => $v->nombre_vacuna,
                    'proxima' => optional($v->fecha_proxima_sugerida)?->toDateString(),
                    'categoria' => $v->categoria_registro,
                    'dosis' => $v->numero_dosis,
                ])
                ->all();
        }

        return [
            'ok' => true,
            'paciente' => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'especie' => $p->especie,
                'raza' => $p->raza,
                'sexo' => $p->sexo ?? null,
                'activo' => $p->activo,
                'microchip' => $p->microchip,
                'fecha_nacimiento' => optional($p->fecha_nacimiento)?->toDateString(),
                'titular' => $titular !== '' ? $titular : null,
                'telefono_titular' => $p->propietario?->telefono,
                'documento_titular' => $p->propietario?->documento,
                'url' => '/clinica/pacientes/'.$p->id,
            ],
            'vacunas_proximas' => $vacunasProximas,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverNavegacion(string $destino): array
    {
        $resolved = InAppAssistantNavigation::resolve($destino);
        if ($resolved === null) {
            $opciones = array_map(
                static fn (array $d): string => $d['label'],
                InAppAssistantNavigation::destinations(),
            );

            return [
                'ok' => false,
                'error' => 'No reconocí ese destino.',
                'opciones' => array_values($opciones),
            ];
        }

        $this->pendingUiActions[] = [
            'type' => 'navigate',
            'url' => $resolved['url'],
            'label' => $resolved['label'],
        ];

        return [
            'ok' => true,
            'destino' => $resolved['id'],
            'label' => $resolved['label'],
            'url' => $resolved['url'],
            'instruccion' => 'Confirma al usuario y ofrece el botón para ir a '.$resolved['label'].'.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resumenHistoriaPaciente(?string $pacienteId, int $limite): array
    {
        $limite = max(1, min(10, $limite));
        $pacienteId = trim((string) ($pacienteId ?: ($this->pageContext['paciente_id'] ?? '')));

        if ($pacienteId === '') {
            return [
                'ok' => false,
                'error' => 'Indica un paciente o abre su historial en pantalla.',
            ];
        }

        if (! Schema::hasTable('pacientes')) {
            return ['ok' => false, 'error' => 'Módulo de pacientes no disponible.'];
        }

        $p = Paciente::query()
            ->with('propietario:id,nombres,apellidos,razon_social')
            ->find($pacienteId, ['id', 'nombre', 'especie', 'raza', 'activo', 'propietario_id']);

        if ($p === null) {
            return ['ok' => false, 'error' => 'No encontré ese paciente.'];
        }

        $titular = $p->propietario?->razon_social
            ?: trim(implode(' ', array_filter([(string) $p->propietario?->nombres, (string) $p->propietario?->apellidos])));

        $consultas = [];
        if (Schema::hasTable('historias_clinicas') && Schema::hasTable('consultas')) {
            $hc = HistoriaClinica::query()->where('paciente_id', $p->id)->first(['id']);
            if ($hc !== null) {
                $consultas = Consulta::query()
                    ->where('historia_clinica_id', $hc->id)
                    ->orderByDesc('atendido_at')
                    ->limit($limite)
                    ->get(['id', 'atendido_at', 'motivo', 'analisis', 'cerrada_at'])
                    ->map(static fn (Consulta $c): array => [
                        'fecha' => optional($c->atendido_at)?->toDateTimeString(),
                        'motivo' => $c->motivo,
                        'analisis' => $c->analisis !== null ? mb_substr((string) $c->analisis, 0, 180) : null,
                        'cerrada' => $c->cerrada_at !== null,
                    ])
                    ->all();
            }
        }

        $aplicaciones = [];
        if (Schema::hasTable('vacunas_aplicadas')) {
            $aplicaciones = VacunaAplicada::query()
                ->where('paciente_id', $p->id)
                ->orderByDesc('aplicada_at')
                ->limit($limite)
                ->get(['nombre_vacuna', 'aplicada_at', 'categoria_registro', 'numero_dosis', 'fecha_proxima_sugerida'])
                ->map(static fn (VacunaAplicada $v): array => [
                    'fecha' => optional($v->aplicada_at)?->toDateTimeString(),
                    'nombre' => $v->nombre_vacuna,
                    'categoria' => $v->categoria_registro,
                    'dosis' => $v->numero_dosis,
                    'proxima' => optional($v->fecha_proxima_sugerida)?->toDateString(),
                ])
                ->all();
        }

        $labs = [];
        if (Schema::hasTable('pedidos_laboratorio')) {
            $labs = PedidoLaboratorio::query()
                ->where('paciente_id', $p->id)
                ->with(['lineas' => static fn ($q) => $q->limit(4)])
                ->orderByDesc('solicitado_at')
                ->limit($limite)
                ->get(['id', 'solicitado_at', 'estado', 'observaciones'])
                ->map(static function (PedidoLaboratorio $lab): array {
                    $examenes = $lab->relationLoaded('lineas')
                        ? $lab->lineas->pluck('nombre_examen')->filter()->values()->all()
                        : [];

                    return [
                        'fecha' => optional($lab->solicitado_at)?->toDateTimeString(),
                        'estado' => $lab->estado,
                        'examenes' => $examenes,
                    ];
                })
                ->all();
        }

        return [
            'ok' => true,
            'paciente' => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'especie' => $p->especie,
                'raza' => $p->raza,
                'activo' => $p->activo,
                'titular' => $titular !== '' ? $titular : null,
                'url' => '/clinica/pacientes/'.$p->id,
            ],
            'consultas' => $consultas,
            'aplicaciones' => $aplicaciones,
            'laboratorio' => $labs,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function agendaCitas(?string $fecha, ?string $veterinario, ?string $sede): array
    {
        if (! Schema::hasTable('citas')) {
            return ['ok' => false, 'error' => 'Módulo de citas no disponible.'];
        }

        $tz = (string) config('app.timezone', 'America/Lima');
        $day = $this->resolveAgendaDate($fecha, $tz);

        $query = Cita::query()
            ->with([
                'paciente:id,nombre',
                'veterinario:id,name',
                'sede:id,nombre',
            ])
            ->whereDate('inicio_at', $day)
            ->whereNotIn('estado', [Cita::ESTADO_CANCELADA])
            ->orderBy('inicio_at')
            ->limit(25);

        $vetLabel = null;
        $vet = trim((string) $veterinario);
        if ($vet !== '') {
            $vetUser = User::query()
                ->where('name', 'ILIKE', '%'.addcslashes($vet, '%_\\').'%')
                ->orderBy('name')
                ->first(['id', 'name']);
            if ($vetUser === null) {
                return [
                    'ok' => false,
                    'error' => "No encontré un veterinario que coincida con «{$vet}».",
                    'fecha' => $day,
                ];
            }
            $query->where('veterinario_id', $vetUser->id);
            $vetLabel = $vetUser->name;
        }

        $sedeLabel = null;
        $sedeQ = trim((string) $sede);
        if ($sedeQ !== '' && Schema::hasTable('sedes')) {
            $sedeRow = Sede::query()
                ->where('nombre', 'ILIKE', '%'.addcslashes($sedeQ, '%_\\').'%')
                ->orderBy('nombre')
                ->first(['id', 'nombre']);
            if ($sedeRow === null) {
                return [
                    'ok' => false,
                    'error' => "No encontré una sede que coincida con «{$sedeQ}».",
                    'fecha' => $day,
                ];
            }
            $query->where('sede_id', $sedeRow->id);
            $sedeLabel = $sedeRow->nombre;
        }

        $rows = $query->get([
            'id', 'paciente_id', 'veterinario_id', 'sede_id', 'inicio_at', 'duracion_minutos', 'estado', 'motivo',
        ]);

        $this->pendingUiActions[] = [
            'type' => 'navigate',
            'url' => '/clinica/citas',
            'label' => 'Citas',
        ];

        return [
            'ok' => true,
            'fecha' => $day,
            'veterinario' => $vetLabel,
            'sede' => $sedeLabel,
            'count' => $rows->count(),
            'citas' => $rows->map(static fn (Cita $c): array => [
                'hora' => optional($c->inicio_at)?->timezone($tz)->format('H:i'),
                'paciente' => $c->paciente?->nombre,
                'paciente_id' => $c->paciente_id,
                'veterinario' => $c->veterinario?->name,
                'sede' => $c->sede?->nombre,
                'estado' => $c->estado,
                'motivo' => $c->motivo,
                'duracion_min' => $c->duracion_minutos,
            ])->all(),
            'url' => '/clinica/citas',
        ];
    }

    private function resolveAgendaDate(?string $fecha, string $tz): string
    {
        $raw = mb_strtolower(trim((string) $fecha));
        $now = Carbon::now($tz);

        return match (true) {
            $raw === '' || $raw === 'hoy' || $raw === 'today' => $now->toDateString(),
            in_array($raw, ['mañana', 'manana', 'tomorrow'], true) => $now->copy()->addDay()->toDateString(),
            (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) => $raw,
            default => $now->toDateString(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function cobrosPendientes(int $limite): array
    {
        $limite = max(1, min(40, $limite > 0 ? $limite : 20));

        $rows = SubscriptionPayment::query()
            ->with([
                'tenant:id,slug,nombre_comercial,razon_social',
                'plan:id,nombre,codigo',
            ])
            ->where('estado', 'pendiente')
            ->orderByDesc('created_at')
            ->limit($limite)
            ->get();

        $this->pendingUiActions[] = [
            'type' => 'navigate',
            'url' => '/plataforma/cobros?estado=pendiente',
            'label' => 'Cobros pendientes',
        ];

        return [
            'ok' => true,
            'count' => $rows->count(),
            'url' => '/plataforma/cobros?estado=pendiente',
            'items' => $rows->map(static function (SubscriptionPayment $p): array {
                $tenant = $p->tenant;

                return [
                    'id' => $p->id,
                    'clinica' => $tenant?->nombre_comercial ?: $tenant?->razon_social ?: $tenant?->slug,
                    'slug' => $tenant?->slug,
                    'plan' => $p->plan?->nombre,
                    'total' => $p->total,
                    'moneda' => $p->moneda,
                    'pasarela' => $p->pasarela,
                    'periodo_inicio' => optional($p->periodo_inicio)?->toDateString(),
                    'periodo_fin' => optional($p->periodo_fin)?->toDateString(),
                    'creado' => optional($p->created_at)?->toIso8601String(),
                ];
            })->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cobrosFallidos(int $dias, int $limite): array
    {
        $dias = max(1, min(60, $dias > 0 ? $dias : 14));
        $limite = max(1, min(40, $limite > 0 ? $limite : 20));
        $since = now()->subDays($dias);

        $rows = SubscriptionPayment::query()
            ->with([
                'tenant:id,slug,nombre_comercial,razon_social',
                'plan:id,nombre,codigo',
            ])
            ->where('estado', 'fallido')
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit($limite)
            ->get();

        $this->pendingUiActions[] = [
            'type' => 'navigate',
            'url' => '/plataforma/cobros?estado=fallido',
            'label' => 'Cobros fallidos',
        ];

        return [
            'ok' => true,
            'dias' => $dias,
            'count' => $rows->count(),
            'url' => '/plataforma/cobros?estado=fallido',
            'items' => $rows->map(static function (SubscriptionPayment $p): array {
                $tenant = $p->tenant;

                return [
                    'id' => $p->id,
                    'clinica' => $tenant?->nombre_comercial ?: $tenant?->razon_social ?: $tenant?->slug,
                    'slug' => $tenant?->slug,
                    'plan' => $p->plan?->nombre,
                    'total' => $p->total,
                    'moneda' => $p->moneda,
                    'error' => $p->error_mensaje,
                    'pasarela' => $p->pasarela,
                    'creado' => optional($p->created_at)?->toIso8601String(),
                ];
            })->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function suscripcionesEnRiesgo(int $diasProximoCobro): array
    {
        $diasProximoCobro = max(1, min(30, $diasProximoCobro > 0 ? $diasProximoCobro : 7));
        $now = now();
        $until = $now->copy()->addDays($diasProximoCobro)->endOfDay();

        $mapRow = static function (Subscription $s): array {
            $tenant = $s->tenant;

            return [
                'clinica' => $tenant?->nombre_comercial ?: $tenant?->razon_social ?: $tenant?->slug,
                'slug' => $tenant?->slug,
                'estado' => $s->estado,
                'plan' => $s->plan?->nombre,
                'proximo_cobro_at' => optional($s->proximo_cobro_at)?->toIso8601String(),
                'grace_ends_at' => optional($s->grace_ends_at)?->toIso8601String(),
            ];
        };

        $with = ['tenant:id,slug,nombre_comercial,razon_social', 'plan:id,nombre,codigo'];

        $grace = Subscription::query()
            ->with($with)
            ->where('estado', 'grace')
            ->orderBy('grace_ends_at')
            ->limit(25)
            ->get()
            ->map($mapRow)
            ->all();

        $suspended = Subscription::query()
            ->with($with)
            ->where('estado', 'suspended')
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get()
            ->map($mapRow)
            ->all();

        $proximoCobro = Subscription::query()
            ->with($with)
            ->billable()
            ->whereNotNull('proximo_cobro_at')
            ->whereBetween('proximo_cobro_at', [$now->copy()->startOfDay(), $until])
            ->orderBy('proximo_cobro_at')
            ->limit(25)
            ->get()
            ->map($mapRow)
            ->all();

        $this->pendingUiActions[] = [
            'type' => 'navigate',
            'url' => '/plataforma/tenants',
            'label' => 'Clínicas',
        ];

        return [
            'ok' => true,
            'dias_proximo_cobro' => $diasProximoCobro,
            'grace' => $grace,
            'suspended' => $suspended,
            'proximo_cobro' => $proximoCobro,
            'url' => '/plataforma/tenants',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resumenPlataforma(): array
    {
        $now = now();

        return [
            'ok' => true,
            'cobros' => [
                'pendientes' => SubscriptionPayment::query()->where('estado', 'pendiente')->count(),
                'fallidos_7d' => SubscriptionPayment::query()
                    ->where('estado', 'fallido')
                    ->where('created_at', '>=', $now->copy()->subDays(7))
                    ->count(),
            ],
            'suscripciones' => [
                'grace' => Subscription::query()->where('estado', 'grace')->count(),
                'suspended' => Subscription::query()->where('estado', 'suspended')->count(),
                'proximo_cobro_7d' => Subscription::query()
                    ->billable()
                    ->whereNotNull('proximo_cobro_at')
                    ->whereBetween('proximo_cobro_at', [
                        $now->copy()->startOfDay(),
                        $now->copy()->addDays(7)->endOfDay(),
                    ])
                    ->count(),
            ],
            'clinicas_activas' => Tenant::query()->where('estado', 'active')->count(),
            'urls' => [
                'cobros' => '/plataforma/cobros',
                'clinicas' => '/plataforma/tenants',
                'suscripciones' => '/plataforma/suscripciones',
                'operaciones' => '/plataforma/operaciones',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buscarClinicas(string $q): array
    {
        $q = trim($q);
        if ($q === '' || mb_strlen($q) < 2) {
            return ['ok' => false, 'error' => 'Indica al menos 2 caracteres para buscar.'];
        }

        $like = '%'.addcslashes($q, '%_\\').'%';
        $rows = Tenant::query()
            ->where(function ($query) use ($like): void {
                $query->where('nombre_comercial', 'ILIKE', $like)
                    ->orWhere('razon_social', 'ILIKE', $like)
                    ->orWhere('slug', 'ILIKE', $like);
            })
            ->orderBy('nombre_comercial')
            ->limit(15)
            ->get(['id', 'slug', 'nombre_comercial', 'razon_social', 'estado']);

        return [
            'ok' => true,
            'count' => $rows->count(),
            'items' => $rows->map(static fn (Tenant $t): array => [
                'id' => $t->id,
                'slug' => $t->slug,
                'nombre' => $t->nombre_comercial ?: $t->razon_social,
                'razon_social' => $t->razon_social,
                'estado' => $t->estado,
                'url' => '/plataforma/tenants',
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverNavegacionPlataforma(string $destino): array
    {
        $map = [
            'cobros' => ['label' => 'Cobros', 'url' => '/plataforma/cobros', 'aliases' => ['pagos', 'pagos pendientes', 'pendientes de pago']],
            'clinicas' => ['label' => 'Clínicas', 'url' => '/plataforma/tenants', 'aliases' => ['tenants', 'clientes', 'veterinarias']],
            'suscripciones' => ['label' => 'Suscripciones', 'url' => '/plataforma/suscripciones', 'aliases' => ['subs', 'billing']],
            'planes' => ['label' => 'Planes', 'url' => '/plataforma/planes', 'aliases' => ['pricing', 'tarifas saas']],
            'configuracion' => ['label' => 'Configuración de plataforma', 'url' => '/plataforma/configuracion', 'aliases' => ['settings', 'ajustes', 'asistente']],
            'operaciones' => ['label' => 'Operaciones', 'url' => '/plataforma/operaciones', 'aliases' => ['ops', 'salud', 'monitoreo']],
        ];

        $q = mb_strtolower(trim($destino));
        $q = preg_replace('/\s+/u', ' ', $q) ?? $q;

        foreach ($map as $id => $dest) {
            $candidates = array_merge([$id, mb_strtolower($dest['label'])], $dest['aliases']);
            foreach ($candidates as $alias) {
                $alias = mb_strtolower(trim((string) $alias));
                if ($alias !== '' && (str_contains($q, $alias) || str_contains($alias, $q))) {
                    $this->pendingUiActions[] = [
                        'type' => 'navigate',
                        'url' => $dest['url'],
                        'label' => $dest['label'],
                    ];

                    return [
                        'ok' => true,
                        'id' => $id,
                        'label' => $dest['label'],
                        'url' => $dest['url'],
                    ];
                }
            }
        }

        return [
            'ok' => false,
            'error' => 'No encontré ese módulo. Prueba: cobros, clínicas, suscripciones, planes, configuración, operaciones.',
        ];
    }
}
