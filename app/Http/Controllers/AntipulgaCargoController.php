<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpsertConsultaCargoRequest;
use App\Models\CajaSesion;
use App\Models\ClinicSetting;
use App\Models\ConsultaCargo;
use App\Models\ConsultaCargoLinea;
use App\Models\Antipulga;
use App\Models\Producto;
use App\Support\Clinica\RecetaFichaLineas;
use App\Models\Sede;
use App\Models\User;
use App\Models\Venta;
use App\Support\Caja\TicketAnchoMm;
use App\Support\ConsultaCargo\ConsultaCargoActivoResolver;
use App\Support\ConsultaCargo\ConsultaCargoStockSync;
use App\Support\ConsultaCargo\ConsultaCargoTotales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AntipulgaCargoController extends Controller
{
    public function __construct(
        private readonly ConsultaCargoStockSync $cargoStock,
    ) {}

    public function show(Request $request, Antipulga $antipulga): Response
    {
        $this->ensurePuedeVer($request);
        abort_unless($antipulga->permiteCargosPreCuenta(), 403);
        abort_unless(Schema::hasColumn('consulta_cargos', 'antipulga_id'), 503);

        $cfg = ClinicSetting::query()->first();
        if ($cfg === null) {
            abort(503, 'Configuración de clínica no disponible.');
        }

        $cargo = ConsultaCargoActivoResolver::resolveOrCreate(
            'antipulga_id',
            $antipulga->id,
            $cfg,
        );

        $this->seedLineasInicialesSiVacio($cargo, $antipulga, $cfg);

        $antipulga->load([
            'paciente.propietario:id,nombres,apellidos,razon_social',
            'veterinario:id,name',
            'cargo.lineas' => fn ($q) => $q->orderBy('orden')->with('producto:id,nombre,sku,unidad'),
        ]);

        $cargo = $antipulga->cargo;
        abort_if($cargo === null, 404);

        $user = $request->user();
        $ventaVinculada = $cargo->venta_id !== null
            ? Venta::query()->whereKey($cargo->venta_id)->first(['id', 'numero'])
            : null;

        $puedeCobrarPorPermiso = $cargo->estado === ConsultaCargo::ESTADO_CONFIRMADO
            && $cargo->venta_id === null
            && $user !== null
            && $user->can('consulta-cargos.cobrar')
            && $user->can('ventas.create');

        $sesionCajaAbierta = $puedeCobrarPorPermiso
            && CajaSesion::query()
                ->where('estado', CajaSesion::ESTADO_ABIERTA)
                ->where('opened_by_id', Auth::id())
                ->exists();

        $aplicados = is_array($antipulga->aplicados) ? $antipulga->aplicados : [];
        $nombre = is_string($aplicados[0]['nombre'] ?? null) && $aplicados[0]['nombre'] !== ''
            ? $aplicados[0]['nombre']
            : 'Antipulgas';

        return Inertia::render('clinica/pacientes/antipulga-cargos', [
            'vacuna' => [
                'id' => $antipulga->id,
                'nombre_vacuna' => $nombre,
                'aplicada_at' => $antipulga->atendido_at,
                'paciente' => $antipulga->paciente,
                'veterinario' => $antipulga->veterinario,
            ],
            'volver_url' => route('clinica.pacientes.show', $antipulga->paciente_id),
            'cargos_base_url' => route('clinica.pacientes.antipulgas.cargos.show', [$antipulga->paciente_id, $antipulga]),
            'cargo' => $cargo,
            'cobro' => [
                'venta_id' => $cargo->venta_id,
                'venta_numero' => $ventaVinculada?->numero,
                'puede_cobrar' => $puedeCobrarPorPermiso && $sesionCajaAbierta,
                'requiere_sesion_caja' => $puedeCobrarPorPermiso && ! $sesionCajaAbierta,
                'url_cobrar' => route('caja.ventas.create-desde-antipulga', [$antipulga->paciente_id, $antipulga], absolute: false),
                'url_sesiones_caja' => route('caja.sesiones.index', absolute: false),
            ],
            'clinic_billing' => [
                'moneda' => $cfg->moneda,
                'igv_porcentaje' => $cfg->igvPorcentajeEfectivo(),
                'precio_incluye_igv' => (bool) $cfg->precio_incluye_igv,
                'ticket_ancho_mm' => TicketAnchoMm::normalize((string) $cfg->ticket_ancho_mm),
            ],
        ]);
    }

    public function ticket(Request $request, Antipulga $antipulga): View
    {
        $this->ensurePuedeVer($request);
        abort_unless($antipulga->permiteCargosPreCuenta(), 403);

        $cfg = ClinicSetting::query()->first();
        if ($cfg === null) {
            abort(503);
        }

        $antipulga->load([
            'paciente.propietario:id,nombres,apellidos,razon_social',
            'veterinario:id,name',
            'cargo.lineas' => fn ($q) => $q->orderBy('orden')->with('producto:id,nombre,sku,unidad'),
        ]);

        $cargo = $antipulga->cargo;
        abort_if($cargo === null, 404);

        $ancho = TicketAnchoMm::fromRequest($request, (string) $cfg->ticket_ancho_mm);

        $lineas = $cargo->lineas->map(function (ConsultaCargoLinea $l): array {
            $tipo = match ($l->tipo_linea) {
                ConsultaCargoLinea::TIPO_PRODUCTO => __('consulta-cargos.ticket.tipo_producto'),
                ConsultaCargoLinea::TIPO_OTRO => __('consulta-cargos.ticket.tipo_otro'),
                default => __('consulta-cargos.ticket.tipo_servicio'),
            };

            return [
                'tipo' => $tipo,
                'concepto' => $l->concepto,
                'cantidad' => (string) $l->cantidad,
                'precio_unitario' => (string) $l->precio_unitario,
            ];
        })->values()->all();

        $trim = static function (?string $v): ?string {
            if ($v === null) {
                return null;
            }
            $t = trim($v);

            return $t === '' ? null : $t;
        };

        $clinicNombre = $cfg->nombre_comercial ?: $cfg->razon_social ?: config('app.name');
        $tz = config('app.timezone');

        return view('clinica.consulta-cargo-ticket', [
            'ancho_mm' => $ancho,
            'clinic_logo_url' => $cfg->logo_url,
            'clinic_nombre' => $clinicNombre,
            'clinic_ruc' => $trim($cfg->ruc),
            'clinic_direccion' => $trim($cfg->direccion_fiscal),
            'clinic_telefono' => $trim($cfg->telefono_principal),
            'moneda' => $cargo->moneda,
            'igv_porcentaje' => number_format($cfg->igvPorcentajeEfectivo(), 2, '.', ''),
            'precio_incluye_igv' => (bool) $cfg->precio_incluye_igv,
            'consulta' => null,
            'paciente_nombre' => $antipulga->paciente->nombre,
            'veterinario_nombre' => $antipulga->veterinario?->name,
            'fecha_referencia' => $antipulga->atendido_at->copy()->timezone($tz),
            'cargo' => $cargo,
            'lineas' => $lineas,
            'auto_print' => $request->boolean('print'),
        ]);
    }

    public function productosBuscar(Request $request): JsonResponse
    {
        $this->ensurePuedeBuscarProductos($request);

        $q = trim((string) $request->query('q', ''));
        $items = Producto::query()
            ->where('activo', true)
            ->when($q !== '', function ($query) use ($q): void {
                $escaped = addcslashes(mb_strtolower($q, 'UTF-8'), '%_\\');
                $term = '%'.$escaped.'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->whereRaw('LOWER(nombre) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(sku, \'\')) LIKE ?', [$term]);
                });
            })
            ->orderBy('nombre')
            ->limit(25)
            ->get(['id', 'nombre', 'sku', 'unidad', 'precio_venta']);

        return response()->json(['data' => $items]);
    }

    public function serviciosBuscar(Request $request): JsonResponse
    {
        $this->ensurePuedeBuscarProductos($request);

        $q = trim((string) $request->query('q', ''));

        return response()->json([
            'data' => \App\Support\Servicios\ServicioTarifaSearch::search($q),
        ]);
    }

    public function update(UpsertConsultaCargoRequest $request, Antipulga $antipulga): RedirectResponse
    {
        $this->ensurePuedeVer($request);
        abort_unless($antipulga->permiteCargosPreCuenta(), 403);

        $cargo = ConsultaCargo::query()
            ->where('antipulga_id', $antipulga->id)
            ->whereNull('venta_id')
            ->orderByDesc('updated_at')
            ->first();
        if ($cargo === null || ! $cargo->esBorrador()) {
            return redirect()
                ->route('clinica.pacientes.antipulgas.cargos.show', [$antipulga->paciente_id, $antipulga])
                ->with('error', __('consulta-cargos.flash.solo_borrador'));
        }

        $cfg = ClinicSetting::query()->first();
        if ($cfg === null) {
            abort(503);
        }

        $validated = $request->validated();
        $lineasIn = $validated['lineas'] ?? [];

        $totales = ConsultaCargoTotales::fromLineas(
            $lineasIn,
            (bool) $cfg->precio_incluye_igv,
            $cfg->igvPorcentajeEfectivo(),
        );

        DB::transaction(function () use ($cargo, $lineasIn, $validated, $totales): void {
            $cargo->update([
                'notas' => $validated['notas'] ?? null,
                'subtotal_sin_igv' => $totales['subtotal_sin_igv'],
                'igv_importe' => $totales['igv_importe'],
                'total' => $totales['total'],
                'updated_by_id' => Auth::id(),
            ]);

            $cargo->lineas()->delete();

            foreach (array_values($lineasIn) as $i => $row) {
                ConsultaCargoLinea::query()->create([
                    'consulta_cargo_id' => $cargo->id,
                    'tipo_linea' => $row['tipo_linea'],
                    'producto_id' => $row['producto_id'] ?? null,
                    'concepto' => $row['concepto'],
                    'cantidad' => $row['cantidad'],
                    'precio_unitario' => $row['precio_unitario'],
                    'descuento_importe' => $row['descuento_importe'] ?? 0,
                    'orden' => $i,
                ]);
            }
        });

        return redirect()
            ->route('clinica.pacientes.antipulgas.cargos.show', [$antipulga->paciente_id, $antipulga])
            ->with('success', __('consulta-cargos.flash.guardado'));
    }

    public function confirmar(UpsertConsultaCargoRequest $request, Antipulga $antipulga): RedirectResponse
    {
        $user = $request->user();
        $this->ensurePuedeVer($request);
        abort_unless($antipulga->permiteCargosPreCuenta(), 403);

        $cargo = ConsultaCargo::query()
            ->where('antipulga_id', $antipulga->id)
            ->whereNull('venta_id')
            ->orderByDesc('updated_at')
            ->first();
        if ($cargo === null) {
            return redirect()
                ->route('clinica.pacientes.antipulgas.cargos.show', [$antipulga->paciente_id, $antipulga])
                ->with('error', __('consulta-cargos.flash.ya_cobrado_no_editable'));
        }

        $cfg = ClinicSetting::query()->first();
        if ($cfg === null) {
            abort(503);
        }

        $validated = $request->validated();
        $notas = $request->has('notas') ? ($validated['notas'] ?? null) : $cargo->notas;
        $lineasIn = $request->has('lineas')
            ? ($validated['lineas'] ?? [])
            : $cargo->lineas()->get()->map(static fn (ConsultaCargoLinea $linea): array => [
                'tipo_linea' => $linea->tipo_linea,
                'producto_id' => $linea->producto_id,
                'concepto' => $linea->concepto,
                'cantidad' => $linea->cantidad,
                'precio_unitario' => $linea->precio_unitario,
                'descuento_importe' => $linea->descuento_importe,
            ])->all();
        if ($lineasIn === []) {
            return redirect()
                ->route('clinica.pacientes.antipulgas.cargos.show', [$antipulga->paciente_id, $antipulga])
                ->with('error', __('consulta-cargos.flash.sin_lineas'));
        }

        $totales = ConsultaCargoTotales::fromLineas(
            $lineasIn,
            (bool) $cfg->precio_incluye_igv,
            $cfg->igvPorcentajeEfectivo(),
        );

        $sedeId = (string) ($antipulga->sede_id ?? '');
        if ($sedeId === '') {
            $sesion = CajaSesion::query()
                ->where('estado', CajaSesion::ESTADO_ABIERTA)
                ->where('opened_by_id', Auth::id())
                ->first();
            $sedeId = (string) ($sesion?->sede_id ?? '');
        }

        $tieneProductos = collect($lineasIn)->contains(
            fn (array $row): bool => ($row['tipo_linea'] ?? '') === ConsultaCargoLinea::TIPO_PRODUCTO
                && ! empty($row['producto_id']),
        );

        // Misma lógica que cargos de consulta: si aún no hay sede, usa la primera activa del tenant.
        if ($sedeId === '' && $tieneProductos) {
            $tenantId = (string) ($user?->tenant_id ?? '');
            if ($tenantId !== '') {
                $sedeId = (string) (Sede::query()
                    ->where('tenant_id', $tenantId)
                    ->where('activa', true)
                    ->whereNull('deleted_at')
                    ->orderBy('nombre')
                    ->value('id') ?? '');
            }
        }

        if ($sedeId === '' && $tieneProductos) {
            return redirect()
                ->route('clinica.pacientes.antipulgas.cargos.show', [$antipulga->paciente_id, $antipulga])
                ->with('error', __('consulta-cargos.flash.sin_sede_stock'));
        }

        try {
            DB::transaction(function () use ($cargo, $sedeId, $user, $notas, $lineasIn, $totales): void {
                $cargo->load('lineas');

                foreach ($cargo->lineas as $lineaAnterior) {
                    $this->cargoStock->revertirLinea(
                        $lineaAnterior,
                        (string) $user->getAuthIdentifier(),
                    );
                }

                $cargo->lineas()->delete();

                foreach (array_values($lineasIn) as $i => $row) {
                    ConsultaCargoLinea::query()->create([
                        'consulta_cargo_id' => $cargo->id,
                        'tipo_linea' => $row['tipo_linea'],
                        'producto_id' => $row['producto_id'] ?? null,
                        'concepto' => $row['concepto'],
                        'cantidad' => $row['cantidad'],
                        'precio_unitario' => $row['precio_unitario'],
                        'descuento_importe' => $row['descuento_importe'] ?? 0,
                        'orden' => $i,
                    ]);
                }

                $cargo->update([
                    'notas' => $notas,
                    'subtotal_sin_igv' => $totales['subtotal_sin_igv'],
                    'igv_importe' => $totales['igv_importe'],
                    'total' => $totales['total'],
                    'estado' => ConsultaCargo::ESTADO_CONFIRMADO,
                    'updated_by_id' => $user->getAuthIdentifier(),
                ]);

                $cargo->load(['lineas.producto:id,nombre']);

                foreach ($cargo->lineas as $linea) {
                    if (! ConsultaCargoStockSync::debeDescontar($linea, $sedeId)) {
                        continue;
                    }

                    try {
                        $movimientos = $this->cargoStock->registrarSalida(
                            $linea,
                            $sedeId,
                            (string) $user->getAuthIdentifier(),
                        );
                    } catch (ValidationException $e) {
                        $base = $e->errors()['cantidad'][0]
                            ?? collect($e->errors())->flatten()->first()
                            ?? __('consulta-cargos.flash.stock_insuficiente');
                        $msg = is_string($base)
                            ? $base.' ('.$linea->concepto.')'
                            : __('consulta-cargos.flash.stock_insuficiente').' ('.$linea->concepto.')';

                        throw ValidationException::withMessages([
                            'cantidad' => $msg,
                        ]);
                    }

                    $primerMov = $movimientos[0] ?? null;
                    if ($primerMov !== null) {
                        $linea->update(['movimiento_inventario_id' => $primerMov->id]);
                    }
                }
            });
        } catch (ValidationException $e) {
            $msg = $e->errors()['cantidad'][0]
                ?? collect($e->errors())->flatten()->first()
                ?? __('consulta-cargos.flash.stock_insuficiente');

            return redirect()
                ->route('clinica.pacientes.antipulgas.cargos.show', [$antipulga->paciente_id, $antipulga])
                ->withErrors($e->errors())
                ->with('error', is_string($msg) ? $msg : __('consulta-cargos.flash.stock_insuficiente'));
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('clinica.pacientes.antipulgas.cargos.show', [$antipulga->paciente_id, $antipulga])
                ->with('error', __('consulta-cargos.flash.stock_insuficiente').' ('.$e->getMessage().')');
        }

        return redirect()
            ->route('clinica.pacientes.antipulgas.cargos.show', [$antipulga->paciente_id, $antipulga])
            ->with('success', __('consulta-cargos.flash.confirmado'));
    }

    public function destroy(Request $request, Antipulga $antipulga): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User
            && ($user->can('consulta-cargos.manage')
                || $user->can('vacunaciones.update')
                || $user->can('historias-clinicas.update')),
            403,
        );

        $cargo = ConsultaCargo::query()
            ->where('antipulga_id', $antipulga->id)
            ->whereNull('venta_id')
            ->orderByDesc('updated_at')
            ->first();

        if ($cargo === null) {
            return redirect()
                ->route('clinica.pacientes.show', $antipulga->paciente_id)
                ->with('info', __('consulta-cargos.flash.sin_precuenta_eliminar'));
        }

        app(\App\Support\ConsultaCargo\ConsultaCargoPendingDestroyer::class)->destroy(
            $cargo,
            (string) $user->getAuthIdentifier(),
        );

        return redirect()
            ->route('clinica.pacientes.show', $antipulga->paciente_id)
            ->with('success', __('consulta-cargos.flash.eliminado'));
    }

    private function seedLineasInicialesSiVacio(
        ConsultaCargo $cargo,
        Antipulga $antipulga,
        ClinicSetting $cfg,
    ): void {
        if ($cargo->lineas()->exists()) {
            return;
        }

        $aplicados = is_array($antipulga->aplicados) ? $antipulga->aplicados : [];
        $ids = [];
        foreach ($aplicados as $linea) {
            $id = $linea['producto_id'] ?? null;
            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }
        $productos = $ids === []
            ? collect()
            : Producto::query()->whereIn('id', $ids)->get(['id', 'nombre', 'precio_venta'])->keyBy('id');

        $lineas = [];
        $orden = 0;
        foreach ($aplicados as $linea) {
            $nombre = trim((string) ($linea['nombre'] ?? ''));
            if ($nombre === '') {
                continue;
            }
            $productoId = is_string($linea['producto_id'] ?? null) ? $linea['producto_id'] : null;
            $prod = $productoId !== null ? $productos->get($productoId) : null;
            $lineas[] = [
                'tipo_linea' => $prod !== null ? ConsultaCargoLinea::TIPO_PRODUCTO : ConsultaCargoLinea::TIPO_OTRO,
                'producto_id' => $prod?->id,
                'concepto' => $nombre,
                'cantidad' => '1.00',
                'precio_unitario' => number_format((float) (string) ($prod->precio_venta ?? 0), 2, '.', ''),
                'descuento_importe' => '0.00',
                'orden' => $orden++,
            ];
        }

        $receta = array_merge(
            is_array($antipulga->receta) ? $antipulga->receta : [],
            RecetaFichaLineas::paraCargo('antipulga_id', $antipulga->id),
        );
        $idsReceta = [];
        foreach ($receta as $linea) {
            $id = $linea['producto_id'] ?? null;
            if (is_string($id) && $id !== '') {
                $idsReceta[] = $id;
            }
        }
        if ($idsReceta !== []) {
            $extra = Producto::query()->whereIn('id', $idsReceta)->get(['id', 'nombre', 'precio_venta'])->keyBy('id');
            $productos = $productos->union($extra);
        }
        $ya = collect($lineas)->pluck('producto_id')->filter()->all();
        foreach ($receta as $linea) {
            $nombre = trim((string) ($linea['nombre'] ?? ''));
            if ($nombre === '') {
                continue;
            }
            $productoId = is_string($linea['producto_id'] ?? null) ? $linea['producto_id'] : null;
            if ($productoId !== null && in_array($productoId, $ya, true)) {
                continue;
            }
            $prod = $productoId !== null ? $productos->get($productoId) : null;
            $cantidad = is_numeric(str_replace(',', '.', (string) ($linea['cantidad'] ?? '')))
                ? number_format((float) str_replace(',', '.', (string) $linea['cantidad']), 2, '.', '')
                : '1.00';
            if ((float) $cantidad < 0.01) {
                $cantidad = '1.00';
            }
            $lineas[] = [
                'tipo_linea' => $prod !== null ? ConsultaCargoLinea::TIPO_PRODUCTO : ConsultaCargoLinea::TIPO_OTRO,
                'producto_id' => $prod?->id,
                'concepto' => $nombre,
                'cantidad' => $cantidad,
                'precio_unitario' => number_format((float) (string) ($prod->precio_venta ?? 0), 2, '.', ''),
                'descuento_importe' => '0.00',
                'orden' => $orden++,
            ];
            if ($prod !== null) {
                $ya[] = $prod->id;
            }
        }

        if ($lineas === []) {
            return;
        }

        $totales = ConsultaCargoTotales::fromLineas(
            $lineas,
            (bool) $cfg->precio_incluye_igv,
            $cfg->igvPorcentajeEfectivo(),
        );

        DB::transaction(function () use ($cargo, $lineas, $totales): void {
            foreach ($lineas as $linea) {
                ConsultaCargoLinea::query()->create([
                    'consulta_cargo_id' => $cargo->id,
                    'tipo_linea' => $linea['tipo_linea'],
                    'producto_id' => $linea['producto_id'],
                    'concepto' => $linea['concepto'],
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'descuento_importe' => $linea['descuento_importe'],
                    'orden' => $linea['orden'],
                ]);
            }

            $cargo->update([
                'subtotal_sin_igv' => $totales['subtotal_sin_igv'],
                'igv_importe' => $totales['igv_importe'],
                'total' => $totales['total'],
                'updated_by_id' => Auth::id(),
            ]);
        });
    }

    private function ensurePuedeVer(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User
            && ($user->can('consulta-cargos.view')
                || $user->can('vacunaciones.view')
                || $user->can('historias-clinicas.view')),
            403,
        );
    }

    private function ensurePuedeBuscarProductos(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User
            && (
                $user->can('consulta-cargos.view')
                || $user->can('consulta-cargos.manage')
                || $user->can('vacunaciones.view')
                || $user->can('vacunaciones.update')
                || $user->can('historias-clinicas.view')
                || $user->can('historias-clinicas.update')
                || $user->can('productos.view')
            ),
            403,
        );
    }
}
