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
use App\Models\ProductoLote;
use App\Models\Propietario;
use App\Models\SalesConversation;
use App\Models\Sede;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VacunaAplicada;
use App\Models\Venta;
use App\Services\Platform\OperacionesSnapshotService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class InAppAssistantToolExecutor
{
    /**
     * `any` permite herramientas agregadas; cada bloque interno vuelve a filtrarse.
     *
     * @var array<string, array{permissions: list<string>, mode: 'any'|'all'}>
     */
    public const CLINIC_TOOL_PERMISSIONS = [
        'buscar_pacientes' => ['permissions' => ['pacientes.view'], 'mode' => 'all'],
        'buscar_propietarios' => ['permissions' => ['propietarios.view'], 'mode' => 'all'],
        'buscar_productos' => ['permissions' => ['productos.view'], 'mode' => 'all'],
        'resumen_operativo' => ['permissions' => ['citas.view', 'ventas.view', 'alertas-stock.view', 'vacunaciones.view', 'caja-sesiones.view', 'pacientes.view'], 'mode' => 'any'],
        'alertas_operativas' => ['permissions' => ['vacunaciones.view', 'alertas-stock.view', 'caja-sesiones.view'], 'mode' => 'any'],
        'paciente_en_contexto' => ['permissions' => ['pacientes.view'], 'mode' => 'all'],
        'resolver_navegacion' => ['permissions' => ['in-app-assistant.use'], 'mode' => 'all'],
        'resumen_historia_paciente' => ['permissions' => ['pacientes.view', 'historias-clinicas.view'], 'mode' => 'all'],
        'agenda_citas' => ['permissions' => ['citas.view'], 'mode' => 'all'],
        'caducidades_proximas' => ['permissions' => ['alertas-stock.view'], 'mode' => 'all'],
        'caja_del_dia' => ['permissions' => ['caja-sesiones.view'], 'mode' => 'all'],
        'buscar_venta' => ['permissions' => ['ventas.view'], 'mode' => 'all'],
        'quien_atiende_hoy' => ['permissions' => ['citas.view'], 'mode' => 'all'],
        'explicar_pantalla' => ['permissions' => ['in-app-assistant.use'], 'mode' => 'all'],
    ];

    /** @var array{url?: string, component?: string, paciente_id?: string, scope?: string}|null */
    private ?array $pageContext = null;

    private string $scope = 'clinic';

    private ?User $user = null;

    /** @var list<array{type: string, url: string, label: string}> */
    private array $pendingUiActions = [];

    public function setUser(User $user): void
    {
        $this->user = $user;
        $this->pendingUiActions = [];
    }

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
        if (! self::isToolAuthorized($name, $this->scope, $this->user)) {
            return (string) json_encode([
                'ok' => false,
                'status' => 403,
                'error' => 'No tienes permiso para usar esta herramienta.',
            ], JSON_UNESCAPED_UNICODE);
        }

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
                'tenants_por_vencer' => $this->tenantsPorVencer((int) ($args['dias'] ?? 7)),
                'uso_bot_ia' => $this->usoBotIa((int) ($args['limite'] ?? 20)),
                'estado_whatsapp_openwa' => $this->estadoWhatsappOpenwa(),
                'leads_frios' => $this->leadsFrios(
                    (int) ($args['dias_inactividad'] ?? 3),
                    (int) ($args['limite'] ?? 10),
                ),
                'explicar_pantalla' => $this->explicarPantalla(),
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
            'caducidades_proximas' => $this->caducidadesProximas(
                (int) ($args['dias'] ?? 30),
                (int) ($args['limite'] ?? 15),
            ),
            'caja_del_dia' => $this->cajaDelDia(),
            'buscar_venta' => $this->buscarVenta((string) ($args['q'] ?? '')),
            'quien_atiende_hoy' => $this->quienAtiendeHoy(isset($args['fecha']) ? (string) $args['fecha'] : 'hoy'),
            'explicar_pantalla' => $this->explicarPantalla(),
            default => ['ok' => false, 'error' => 'Herramienta no disponible.'],
        };

        return (string) json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    public function canExecute(string $name): bool
    {
        return self::isToolAuthorized($name, $this->scope, $this->user);
    }

    public static function isToolAuthorized(string $name, string $scope, ?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($scope === 'platform') {
            return $user->isPlatformSuperadmin();
        }

        $rule = self::CLINIC_TOOL_PERMISSIONS[$name] ?? null;
        if ($rule === null) {
            return false;
        }

        if ($user->isPlatformSuperadmin()) {
            return true;
        }

        $checks = array_map(
            static fn (string $permission): bool => $user->can($permission),
            $rule['permissions'],
        );

        return $rule['mode'] === 'all'
            ? ! in_array(false, $checks, true)
            : in_array(true, $checks, true);
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
        $canViewOwners = $this->can('propietarios.view');
        $query = Paciente::query();
        if ($canViewOwners) {
            $query->with('propietario:id,nombres,apellidos,razon_social,telefono');
        }
        $rows = $query
            ->where(function ($query) use ($like, $canViewOwners): void {
                $query->where('nombre', 'ILIKE', $like)
                    ->orWhere('microchip', 'ILIKE', $like);
                if ($canViewOwners) {
                    $query->orWhereHas('propietario', function ($p) use ($like): void {
                        $p->where('nombres', 'ILIKE', $like)
                            ->orWhere('apellidos', 'ILIKE', $like)
                            ->orWhere('razon_social', 'ILIKE', $like)
                            ->orWhere('telefono', 'ILIKE', $like);
                    });
                }
            })
            ->orderBy('nombre')
            ->limit(8)
            ->get(['id', 'nombre', 'especie', 'raza', 'propietario_id', 'activo']);

        return [
            'ok' => true,
            'count' => $rows->count(),
            'pacientes' => $rows->map(static function (Paciente $p) use ($canViewOwners): array {
                $owner = $canViewOwners ? $p->propietario : null;
                $titular = $owner?->razon_social
                    ?: trim(implode(' ', array_filter([(string) $owner?->nombres, (string) $owner?->apellidos])));

                return [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'especie' => $p->especie,
                    'raza' => $p->raza,
                    'activo' => $p->activo,
                    'titular' => $titular !== '' ? $titular : null,
                    'telefono_titular' => $owner?->telefono,
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

        if ($this->can('citas.view') && Schema::hasTable('citas')) {
            $out['citas_hoy'] = Cita::query()
                ->whereDate('inicio_at', $hoy)
                ->count();
        }

        if ($this->can('ventas.view') && Schema::hasTable('ventas')) {
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

        $alertas = $this->canExecute('alertas_operativas') ? $this->alertasOperativas(14) : [];
        if (($alertas['ok'] ?? false) === true) {
            $out['alertas'] = [
                'stock_bajo_count' => $alertas['stock_bajo']['count'] ?? 0,
                'vacunas_proximas_count' => $alertas['vacunas_proximas']['count'] ?? 0,
                'cajas_abiertas_count' => $alertas['caja']['abiertas_count'] ?? 0,
                'usuario_tiene_caja_abierta' => $alertas['caja']['usuario_tiene_abierta'] ?? false,
            ];
        }

        if ($this->can('pacientes.view') && Schema::hasTable('pacientes')) {
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

        if ($this->can('vacunaciones.view') && Schema::hasTable('vacunas_aplicadas')) {
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

        if ($this->can('alertas-stock.view') && Schema::hasTable('existencias_sede') && Schema::hasTable('productos')) {
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

        if ($this->can('caja-sesiones.view') && Schema::hasTable('caja_sesiones')) {
            $abiertas = CajaSesion::query()
                ->where('estado', CajaSesion::ESTADO_ABIERTA)
                ->orderByDesc('opened_at')
                ->limit(8)
                ->get(['id', 'sede_id', 'opened_at', 'opened_by_id', 'moneda', 'saldo_apertura']);

            $sedeIds = $abiertas->pluck('sede_id')->filter()->unique()->values()->all();
            $sedes = Schema::hasTable('sedes') && $sedeIds !== []
                ? Sede::query()->whereIn('id', $sedeIds)->pluck('nombre', 'id')
                : collect();

            $userId = $this->user?->getAuthIdentifier();
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

        $caducidades = $this->can('alertas-stock.view') ? $this->caducidadesProximas(30, 8) : [];
        if (($caducidades['ok'] ?? false) === true) {
            $out['caducidades'] = [
                'vencidos_count' => $caducidades['vencidos_count'] ?? 0,
                'por_vencer_count' => $caducidades['por_vencer_count'] ?? 0,
                'items' => $caducidades['items'] ?? [],
                'url' => '/inventario/alertas?modo=lotes',
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

        $canViewOwners = $this->can('propietarios.view');
        $query = Paciente::query();
        if ($canViewOwners) {
            $query->with('propietario:id,nombres,apellidos,razon_social,telefono,documento');
        }
        $p = $query
            ->find($pacienteId, ['id', 'nombre', 'especie', 'raza', 'sexo', 'activo', 'microchip', 'propietario_id', 'fecha_nacimiento']);

        if ($p === null) {
            return ['ok' => false, 'error' => 'No encontré ese paciente.'];
        }

        $owner = $canViewOwners ? $p->propietario : null;
        $titular = $owner?->razon_social
            ?: trim(implode(' ', array_filter([(string) $owner?->nombres, (string) $owner?->apellidos])));

        $vacunasProximas = [];
        if ($this->can('vacunaciones.view') && Schema::hasTable('vacunas_aplicadas')) {
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
                'telefono_titular' => $owner?->telefono,
                'documento_titular' => $owner?->documento,
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
        $resolved = InAppAssistantNavigation::resolve($destino, $this->user);
        if ($resolved === null) {
            $opciones = array_map(
                static fn (array $d): string => $d['label'],
                InAppAssistantNavigation::destinations($this->user),
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

        $canViewOwners = $this->can('propietarios.view');
        $patientQuery = Paciente::query();
        if ($canViewOwners) {
            $patientQuery->with('propietario:id,nombres,apellidos,razon_social');
        }
        $p = $patientQuery
            ->find($pacienteId, ['id', 'nombre', 'especie', 'raza', 'activo', 'propietario_id']);

        if ($p === null) {
            return ['ok' => false, 'error' => 'No encontré ese paciente.'];
        }

        $owner = $canViewOwners ? $p->propietario : null;
        $titular = $owner?->razon_social
            ?: trim(implode(' ', array_filter([(string) $owner?->nombres, (string) $owner?->apellidos])));

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
        if ($this->can('vacunaciones.view') && Schema::hasTable('vacunas_aplicadas')) {
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
        if ($this->can('laboratorio.view') && Schema::hasTable('pedidos_laboratorio')) {
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
            'cobros' => ['label' => 'Cobros', 'url' => '/plataforma/cobros', 'aliases' => ['pagos pendientes', 'pendientes de pago', 'fallidos']],
            'pagos' => ['label' => 'Pagos', 'url' => '/plataforma/pagos', 'aliases' => ['pagos', 'quiénes pagaron', 'quienes pagaron', 'pagados', 'procesados']],
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

    /**
     * @return array<string, mixed>
     */
    private function explicarPantalla(): array
    {
        return InAppAssistantScreenGuide::explain($this->pageContext, $this->scope);
    }

    /**
     * @return array<string, mixed>
     */
    private function caducidadesProximas(int $dias, int $limite): array
    {
        $dias = max(1, min(90, $dias > 0 ? $dias : 30));
        $limite = max(1, min(30, $limite > 0 ? $limite : 15));

        if (! Schema::hasTable('producto_lotes') || ! Schema::hasTable('productos')) {
            return ['ok' => false, 'error' => 'Módulo de lotes no disponible.'];
        }

        $tz = (string) config('app.timezone', 'America/Lima');
        $hoy = Carbon::now($tz)->startOfDay();
        $hasta = $hoy->copy()->addDays($dias);

        $base = ProductoLote::query()
            ->with(['producto:id,nombre,sku'])
            ->where('cantidad', '>', 0)
            ->whereNotNull('fecha_vencimiento')
            ->whereHas('producto', static fn ($q) => $q->where('activo', true));

        $vencidosCount = (clone $base)
            ->whereDate('fecha_vencimiento', '<', $hoy->toDateString())
            ->count();

        $porVencerCount = (clone $base)
            ->whereDate('fecha_vencimiento', '>=', $hoy->toDateString())
            ->whereDate('fecha_vencimiento', '<=', $hasta->toDateString())
            ->count();

        $items = (clone $base)
            ->whereDate('fecha_vencimiento', '<=', $hasta->toDateString())
            ->orderBy('fecha_vencimiento')
            ->limit($limite)
            ->get(['id', 'producto_id', 'sede_id', 'numero_lote', 'fecha_vencimiento', 'cantidad']);

        $sedeIds = $items->pluck('sede_id')->filter()->unique()->values()->all();
        $sedes = Schema::hasTable('sedes') && $sedeIds !== []
            ? Sede::query()->whereIn('id', $sedeIds)->pluck('nombre', 'id')
            : collect();

        $this->pendingUiActions[] = [
            'type' => 'navigate',
            'url' => '/inventario/alertas?modo=lotes',
            'label' => 'Alertas de caducidad',
        ];

        return [
            'ok' => true,
            'ventana_dias' => $dias,
            'vencidos_count' => $vencidosCount,
            'por_vencer_count' => $porVencerCount,
            'url' => '/inventario/alertas?modo=lotes',
            'items' => $items->map(static function (ProductoLote $lote) use ($hoy, $sedes): array {
                $fecha = $lote->fecha_vencimiento;
                $diasRestantes = $fecha !== null ? $hoy->diffInDays($fecha, false) : null;

                return [
                    'producto' => $lote->producto?->nombre,
                    'sku' => $lote->producto?->sku,
                    'lote' => $lote->numero_lote,
                    'sede' => $sedes[$lote->sede_id] ?? null,
                    'cantidad' => (string) $lote->cantidad,
                    'vence' => optional($fecha)?->toDateString(),
                    'dias_restantes' => $diasRestantes,
                    'estado' => ($diasRestantes !== null && $diasRestantes < 0) ? 'vencido' : 'por_vencer',
                ];
            })->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cajaDelDia(): array
    {
        if (! Schema::hasTable('caja_sesiones')) {
            return ['ok' => false, 'error' => 'Módulo de caja no disponible.'];
        }

        $tz = (string) config('app.timezone', 'America/Lima');
        $hoy = Carbon::now($tz)->toDateString();
        $userId = $this->user?->getAuthIdentifier();

        $abiertas = CajaSesion::query()
            ->where('estado', CajaSesion::ESTADO_ABIERTA)
            ->orderByDesc('opened_at')
            ->limit(10)
            ->get(['id', 'sede_id', 'opened_at', 'opened_by_id', 'moneda', 'saldo_apertura']);

        $miSesion = $abiertas->first(static fn (CajaSesion $s): bool => (string) $s->opened_by_id === (string) $userId);

        $cerradasHoy = CajaSesion::query()
            ->where('estado', CajaSesion::ESTADO_CERRADA)
            ->whereDate('closed_at', $hoy)
            ->orderByDesc('closed_at')
            ->limit(8)
            ->get(['id', 'sede_id', 'opened_at', 'closed_at', 'saldo_apertura', 'saldo_cierre_efectivo', 'moneda', 'opened_by_id', 'closed_by_id']);

        $sedeIds = $abiertas->pluck('sede_id')
            ->merge($cerradasHoy->pluck('sede_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $sedes = Schema::hasTable('sedes') && $sedeIds !== []
            ? Sede::query()->whereIn('id', $sedeIds)->pluck('nombre', 'id')
            : collect();

        $ventasHoy = null;
        if ($this->can('ventas.view') && Schema::hasTable('ventas')) {
            $query = Venta::query()
                ->where(function ($q) use ($hoy): void {
                    $q->whereDate('fecha_pago', $hoy)
                        ->orWhere(function ($inner) use ($hoy): void {
                            $inner->whereNull('fecha_pago')->whereDate('created_at', $hoy);
                        });
                })
                ->whereNull('anulado_at');

            $ventasHoy = [
                'cantidad' => (clone $query)->count(),
                'total' => (string) ((clone $query)->sum('total') ?? 0),
            ];
        }

        $ventasMiSesion = null;
        if ($this->can('ventas.view') && $miSesion !== null && Schema::hasTable('ventas')) {
            $ventasMiSesion = [
                'cantidad' => Venta::query()->where('caja_sesion_id', $miSesion->id)->whereNull('anulado_at')->count(),
                'total' => (string) (Venta::query()->where('caja_sesion_id', $miSesion->id)->whereNull('anulado_at')->sum('total') ?? 0),
            ];
        }

        $this->pendingUiActions[] = [
            'type' => 'navigate',
            'url' => '/caja/sesiones',
            'label' => 'Sesiones de caja',
        ];

        return [
            'ok' => true,
            'fecha' => $hoy,
            'abiertas_count' => $abiertas->count(),
            'mi_sesion' => $miSesion === null ? null : [
                'id' => $miSesion->id,
                'sede' => $sedes[$miSesion->sede_id] ?? null,
                'opened_at' => optional($miSesion->opened_at)?->toDateTimeString(),
                'saldo_apertura' => (string) $miSesion->saldo_apertura,
                'moneda' => $miSesion->moneda,
                'ventas' => $ventasMiSesion,
            ],
            'sesiones_abiertas' => $abiertas->map(static fn (CajaSesion $s): array => [
                'sede' => $sedes[$s->sede_id] ?? null,
                'opened_at' => optional($s->opened_at)?->toDateTimeString(),
                'saldo_apertura' => (string) $s->saldo_apertura,
                'es_mia' => (string) $s->opened_by_id === (string) $userId,
            ])->all(),
            'cierres_hoy' => $cerradasHoy->map(static fn (CajaSesion $s): array => [
                'sede' => $sedes[$s->sede_id] ?? null,
                'opened_at' => optional($s->opened_at)?->toDateTimeString(),
                'closed_at' => optional($s->closed_at)?->toDateTimeString(),
                'saldo_apertura' => (string) $s->saldo_apertura,
                'saldo_cierre_efectivo' => (string) ($s->saldo_cierre_efectivo ?? ''),
                'moneda' => $s->moneda,
            ])->all(),
            'ventas_hoy' => $ventasHoy,
            'url' => '/caja/sesiones',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buscarVenta(string $q): array
    {
        $q = trim($q);
        if ($q === '' || mb_strlen($q) < 2) {
            return ['ok' => false, 'error' => 'Indica al menos 2 caracteres del número de venta o boleta.'];
        }

        if (! Schema::hasTable('ventas')) {
            return ['ok' => false, 'error' => 'Módulo de ventas no disponible.'];
        }

        $like = '%'.addcslashes($q, '%_\\').'%';
        $query = Venta::query()
            ->with([
                'propietario:id,nombres,apellidos,razon_social',
                'felDocument:id,numero_completo,serie,correlativo,estado,url_pdf',
            ])
            ->where(function ($inner) use ($like, $q): void {
                $inner->where('numero', 'ILIKE', $like);
                if (Schema::hasTable('fel_documents')) {
                    $inner->orWhereHas('felDocument', static function ($fq) use ($like): void {
                        $fq->where('numero_completo', 'ILIKE', $like)
                            ->orWhere('serie', 'ILIKE', $like);
                    });
                }
                if (preg_match('/^[0-9a-fA-F-]{36}$/', $q) === 1) {
                    $inner->orWhere('id', $q);
                }
            })
            ->orderByDesc('created_at')
            ->limit(12);

        $rows = $query->get();

        $this->pendingUiActions[] = [
            'type' => 'navigate',
            'url' => '/caja/ventas?search='.rawurlencode($q),
            'label' => 'Ventas',
        ];

        return [
            'ok' => true,
            'count' => $rows->count(),
            'url' => '/caja/ventas?search='.rawurlencode($q),
            'items' => $rows->map(static function (Venta $v): array {
                $titular = $v->propietario?->razon_social
                    ?: trim(implode(' ', array_filter([
                        (string) $v->propietario?->nombres,
                        (string) $v->propietario?->apellidos,
                    ])));
                $numeroDisplay = $v->felDocument?->numero_completo ?: $v->numero;

                return [
                    'id' => $v->id,
                    'numero' => $v->numero,
                    'numero_display' => $numeroDisplay,
                    'total' => (string) $v->total,
                    'estado_fel' => $v->fel_estado ?? $v->felDocument?->estado,
                    'anulado' => $v->anulado_at !== null,
                    'fecha' => optional($v->fecha_pago ?? $v->created_at)?->toDateTimeString(),
                    'titular' => $titular !== '' ? $titular : null,
                    'url' => '/caja/ventas/'.$v->id,
                    'pdf' => $v->felDocument?->url_pdf,
                ];
            })->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function quienAtiendeHoy(string $fechaRaw): array
    {
        if (! Schema::hasTable('citas')) {
            return ['ok' => false, 'error' => 'Módulo de citas no disponible.'];
        }

        $fecha = $this->resolveAgendaDate(
            $fechaRaw,
            (string) config('app.timezone', 'America/Lima'),
        );
        $rows = Cita::query()
            ->with(['veterinario:id,name', 'sede:id,nombre'])
            ->whereDate('inicio_at', $fecha)
            ->where('estado', '!=', Cita::ESTADO_CANCELADA)
            ->whereNotNull('veterinario_id')
            ->orderBy('inicio_at')
            ->get(['id', 'veterinario_id', 'sede_id', 'estado', 'inicio_at', 'paciente_id']);

        $byVet = $rows->groupBy('veterinario_id');
        $items = [];
        foreach ($byVet as $vetId => $citas) {
            /** @var Collection<int, Cita> $citas */
            $first = $citas->first();
            $items[] = [
                'veterinario_id' => (string) $vetId,
                'veterinario' => $first?->veterinario?->name ?? 'Sin nombre',
                'citas_count' => $citas->count(),
                'por_estado' => $citas->groupBy('estado')->map->count()->all(),
                'sedes' => $citas->map(static fn (Cita $c): ?string => $c->sede?->nombre)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
                'proxima_hora' => optional($citas->sortBy('inicio_at')->first()?->inicio_at)?->format('H:i'),
            ];
        }

        usort($items, static fn (array $a, array $b): int => ($b['citas_count'] <=> $a['citas_count']));

        $sinAsignar = Cita::query()
            ->whereDate('inicio_at', $fecha)
            ->where('estado', '!=', Cita::ESTADO_CANCELADA)
            ->whereNull('veterinario_id')
            ->count();

        $this->pendingUiActions[] = [
            'type' => 'navigate',
            'url' => '/clinica/citas',
            'label' => 'Agenda',
        ];

        return [
            'ok' => true,
            'fecha' => $fecha,
            'veterinarios_count' => count($items),
            'citas_con_vet' => $rows->count(),
            'citas_sin_veterinario' => $sinAsignar,
            'items' => $items,
            'url' => '/clinica/citas',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantsPorVencer(int $dias): array
    {
        $dias = max(1, min(60, $dias > 0 ? $dias : 7));
        $now = now();
        $until = $now->copy()->addDays($dias)->endOfDay();

        $with = ['tenant:id,slug,nombre_comercial,razon_social', 'plan:id,nombre,codigo'];

        $rows = Subscription::query()
            ->with($with)
            ->billable()
            ->where(function ($q) use ($now, $until): void {
                $q->where(function ($inner) use ($now, $until): void {
                    $inner->whereNotNull('proximo_cobro_at')
                        ->whereBetween('proximo_cobro_at', [$now->copy()->startOfDay(), $until]);
                })->orWhere(function ($inner) use ($now, $until): void {
                    $inner->whereNull('proximo_cobro_at')
                        ->whereNotNull('current_period_end')
                        ->whereBetween('current_period_end', [$now->copy()->startOfDay(), $until]);
                });
            })
            ->orderByRaw('COALESCE(proximo_cobro_at, current_period_end) ASC')
            ->limit(40)
            ->get();

        $this->pendingUiActions[] = [
            'type' => 'navigate',
            'url' => '/plataforma/suscripciones',
            'label' => 'Suscripciones',
        ];

        return [
            'ok' => true,
            'dias' => $dias,
            'count' => $rows->count(),
            'url' => '/plataforma/suscripciones',
            'items' => $rows->map(static function (Subscription $s): array {
                $tenant = $s->tenant;
                $vence = $s->proximo_cobro_at ?? $s->current_period_end;

                return [
                    'clinica' => $tenant?->nombre_comercial ?: $tenant?->razon_social ?: $tenant?->slug,
                    'slug' => $tenant?->slug,
                    'estado' => $s->estado,
                    'plan' => $s->plan?->nombre,
                    'vence_at' => optional($vence)?->toIso8601String(),
                    'proximo_cobro_at' => optional($s->proximo_cobro_at)?->toIso8601String(),
                    'current_period_end' => optional($s->current_period_end)?->toIso8601String(),
                    'bot_ia_activo' => (bool) $s->bot_ia_activo,
                ];
            })->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function usoBotIa(int $limite): array
    {
        $limite = max(1, min(40, $limite > 0 ? $limite : 20));
        $with = ['tenant:id,slug,nombre_comercial,razon_social', 'plan:id,nombre,codigo'];

        $activos = Subscription::query()
            ->with($with)
            ->billable()
            ->where('bot_ia_activo', true)
            ->orderByDesc('bot_ia_activado_at')
            ->limit($limite)
            ->get();

        $inactivos = Subscription::query()
            ->with($with)
            ->billable()
            ->where(function ($q): void {
                $q->where('bot_ia_activo', false)->orWhereNull('bot_ia_activo');
            })
            ->orderBy('updated_at')
            ->limit($limite)
            ->get();

        $map = static function (Subscription $s): array {
            $tenant = $s->tenant;

            return [
                'clinica' => $tenant?->nombre_comercial ?: $tenant?->razon_social ?: $tenant?->slug,
                'slug' => $tenant?->slug,
                'estado' => $s->estado,
                'plan' => $s->plan?->nombre,
                'bot_ia_activo' => (bool) $s->bot_ia_activo,
                'activado_at' => optional($s->bot_ia_activado_at)?->toIso8601String(),
                'precio_mensual' => $s->bot_ia_precio_mensual,
            ];
        };

        $this->pendingUiActions[] = [
            'type' => 'navigate',
            'url' => '/plataforma/suscripciones',
            'label' => 'Suscripciones',
        ];

        return [
            'ok' => true,
            'activos_count' => Subscription::query()->billable()->where('bot_ia_activo', true)->count(),
            'inactivos_count' => Subscription::query()->billable()->where(function ($q): void {
                $q->where('bot_ia_activo', false)->orWhereNull('bot_ia_activo');
            })->count(),
            'activos' => $activos->map($map)->all(),
            'inactivos_muestra' => $inactivos->map($map)->all(),
            'url' => '/plataforma/suscripciones',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function estadoWhatsappOpenwa(): array
    {
        $snapshot = app(OperacionesSnapshotService::class)->build();
        $whatsapp = is_array($snapshot['whatsapp'] ?? null) ? $snapshot['whatsapp'] : [];
        $failed = is_array($snapshot['failed_jobs'] ?? null) ? $snapshot['failed_jobs'] : [];

        $this->pendingUiActions[] = [
            'type' => 'navigate',
            'url' => '/plataforma/operaciones',
            'label' => 'Operaciones',
        ];

        return [
            'ok' => true,
            'openwa_configured' => (bool) ($whatsapp['openwa_configured'] ?? false),
            'platform' => $whatsapp['platform'] ?? null,
            'tenants_ready' => (int) ($whatsapp['tenants_ready'] ?? 0),
            'tenants_not_ready' => (int) ($whatsapp['tenants_not_ready'] ?? 0),
            'tenants_with_error' => (int) ($whatsapp['tenants_with_error'] ?? 0),
            'broken' => array_slice(is_array($whatsapp['broken'] ?? null) ? $whatsapp['broken'] : [], 0, 10),
            'failed_jobs_total' => (int) ($failed['total'] ?? 0),
            'failed_jobs_recent' => array_slice(is_array($failed['recent'] ?? null) ? $failed['recent'] : [], 0, 8),
            'url' => '/plataforma/operaciones',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function leadsFrios(int $inactiveDays, int $limite): array
    {
        $inactiveDays = max(1, min(30, $inactiveDays > 0 ? $inactiveDays : 3));
        $limite = max(1, min(25, $limite > 0 ? $limite : 10));

        if (! Schema::hasTable('sales_conversations')) {
            return ['ok' => false, 'error' => 'Tabla de conversaciones de ventas no disponible.'];
        }

        $pool = SalesConversation::query()
            ->where('converted', false)
            ->whereNull('lost_at')
            ->where('reactivation_count', '<', 2)
            ->where(function ($q): void {
                $q->where('bot_paused_manually', false)
                    ->orWhereNull('bot_paused_manually');
            })
            ->where(function ($q): void {
                $q->where('turn_count', '>', 0)
                    ->orWhere('activation_trigger', 'like', 'manual:%');
            })
            ->where(function ($q): void {
                $q->whereNull('last_reactivation_at')
                    ->orWhereRaw('EXTRACT(EPOCH FROM (NOW() - last_reactivation_at))/86400 >= 3');
            })
            ->whereRaw(
                'EXTRACT(EPOCH FROM (NOW() - COALESCE(last_message_at, created_at)))/86400 >= ?',
                [$inactiveDays],
            )
            ->orderByRaw('COALESCE(last_message_at, created_at) ASC')
            ->limit(80)
            ->get();

        $eligible = $pool->filter(
            static fn (SalesConversation $c): bool => $c->isEligibleForReactivation($inactiveDays, 2),
        )->values();

        $sample = $eligible->take($limite)->map(static function (SalesConversation $c): array {
            return [
                'id' => $c->id,
                'phone' => $c->phone,
                'name' => $c->prospect_name,
                'product' => $c->product,
                'turn_count' => $c->turn_count,
                'reactivation_count' => $c->reactivation_count,
                'last_message_at' => optional($c->last_message_at)?->toIso8601String(),
                'last_reactivation_at' => optional($c->last_reactivation_at)?->toIso8601String(),
            ];
        })->all();

        return [
            'ok' => true,
            'dias_inactividad' => $inactiveDays,
            'elegibles_hoy' => $eligible->count(),
            'pool_bruto' => $pool->count(),
            'muestra' => $sample,
            'nota' => 'Elegibles según la misma regla del comando vetsaas:reactivate-cold-leads (10:00 y 15:00).',
        ];
    }

    private function can(string $permission): bool
    {
        return $this->user !== null
            && ($this->user->isPlatformSuperadmin() || $this->user->can($permission));
    }
}
