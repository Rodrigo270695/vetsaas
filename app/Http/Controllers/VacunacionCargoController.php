<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpsertConsultaCargoRequest;
use App\Models\CajaSesion;
use App\Models\ClinicSetting;
use App\Models\ConsultaCargo;
use App\Models\ConsultaCargoLinea;
use App\Models\Producto;
use App\Models\ServicioClinico;
use App\Models\User;
use App\Models\VacunaAplicada;
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

class VacunacionCargoController extends Controller
{
    public function __construct(
        private readonly ConsultaCargoStockSync $cargoStock,
    ) {}

    public function show(Request $request, VacunaAplicada $vacuna_aplicada): Response
    {
        $this->ensurePuedeVer($request);
        abort_unless($vacuna_aplicada->permiteCargosPreCuenta(), 403);
        abort_unless(Schema::hasColumn('consulta_cargos', 'vacuna_aplicada_id'), 503);

        $cfg = ClinicSetting::query()->first();
        if ($cfg === null) {
            abort(503, 'Configuración de clínica no disponible.');
        }

        $cargo = ConsultaCargoActivoResolver::resolveOrCreate(
            'vacuna_aplicada_id',
            $vacuna_aplicada->id,
            $cfg,
        );

        $this->seedLineasInicialesSiVacio($cargo, $vacuna_aplicada, $cfg);

        $vacuna_aplicada->load([
            'paciente.propietario:id,nombres,apellidos,razon_social',
            'veterinario:id,name',
            'servicioClinico:id,nombre,precio_lista',
            'cargo.lineas' => fn ($q) => $q->orderBy('orden')->with('producto:id,nombre,sku,unidad'),
        ]);

        $cargo = $vacuna_aplicada->cargo;
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

        return Inertia::render('clinica/vacunaciones/cargos', [
            'vacuna' => $vacuna_aplicada,
            'cargo' => $cargo,
            'cobro' => [
                'venta_id' => $cargo->venta_id,
                'venta_numero' => $ventaVinculada?->numero,
                'puede_cobrar' => $puedeCobrarPorPermiso && $sesionCajaAbierta,
                'requiere_sesion_caja' => $puedeCobrarPorPermiso && ! $sesionCajaAbierta,
                'url_cobrar' => route('caja.ventas.create-desde-vacuna', ['vacuna_aplicada' => $vacuna_aplicada], absolute: false),
                'url_sesiones_caja' => route('caja.sesiones.index', absolute: false),
            ],
            'clinic_billing' => [
                'moneda' => $cfg->moneda,
                'igv_porcentaje' => (float) $cfg->igv_porcentaje,
                'precio_incluye_igv' => (bool) $cfg->precio_incluye_igv,
                'ticket_ancho_mm' => TicketAnchoMm::normalize((string) $cfg->ticket_ancho_mm),
            ],
        ]);
    }

    public function ticket(Request $request, VacunaAplicada $vacuna_aplicada): View
    {
        $this->ensurePuedeVer($request);
        abort_unless($vacuna_aplicada->permiteCargosPreCuenta(), 403);

        $cfg = ClinicSetting::query()->first();
        if ($cfg === null) {
            abort(503);
        }

        $vacuna_aplicada->load([
            'paciente.propietario:id,nombres,apellidos,razon_social',
            'veterinario:id,name',
            'cargo.lineas' => fn ($q) => $q->orderBy('orden')->with('producto:id,nombre,sku,unidad'),
        ]);

        $cargo = $vacuna_aplicada->cargo;
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
            'igv_porcentaje' => (string) $cfg->igv_porcentaje,
            'precio_incluye_igv' => (bool) $cfg->precio_incluye_igv,
            'consulta' => null,
            'paciente_nombre' => $vacuna_aplicada->paciente->nombre,
            'veterinario_nombre' => $vacuna_aplicada->veterinario?->name,
            'fecha_referencia' => $vacuna_aplicada->aplicada_at->copy()->timezone($tz),
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

    public function update(UpsertConsultaCargoRequest $request, VacunaAplicada $vacuna_aplicada): RedirectResponse
    {
        $this->ensurePuedeVer($request);
        abort_unless($vacuna_aplicada->permiteCargosPreCuenta(), 403);

        $cargo = ConsultaCargo::query()
            ->where('vacuna_aplicada_id', $vacuna_aplicada->id)
            ->whereNull('venta_id')
            ->orderByDesc('updated_at')
            ->first();
        if ($cargo === null || ! $cargo->esBorrador()) {
            return redirect()
                ->route('clinica.vacunaciones.cargos.show', $vacuna_aplicada)
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
            (float) $cfg->igv_porcentaje,
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
            ->route('clinica.vacunaciones.cargos.show', $vacuna_aplicada)
            ->with('success', __('consulta-cargos.flash.guardado'));
    }

    public function confirmar(UpsertConsultaCargoRequest $request, VacunaAplicada $vacuna_aplicada): RedirectResponse
    {
        $user = $request->user();
        $this->ensurePuedeVer($request);
        abort_unless($vacuna_aplicada->permiteCargosPreCuenta(), 403);

        $cargo = ConsultaCargo::query()
            ->where('vacuna_aplicada_id', $vacuna_aplicada->id)
            ->whereNull('venta_id')
            ->orderByDesc('updated_at')
            ->first();
        if ($cargo === null) {
            return redirect()
                ->route('clinica.vacunaciones.cargos.show', $vacuna_aplicada)
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
                ->route('clinica.vacunaciones.cargos.show', $vacuna_aplicada)
                ->with('error', __('consulta-cargos.flash.sin_lineas'));
        }

        $totales = ConsultaCargoTotales::fromLineas(
            $lineasIn,
            (bool) $cfg->precio_incluye_igv,
            (float) $cfg->igv_porcentaje,
        );

        $sedeId = (string) ($vacuna_aplicada->sede_id ?? '');
        if ($sedeId === '' && collect($lineasIn)->contains(
            fn (array $row): bool => ($row['tipo_linea'] ?? '') === ConsultaCargoLinea::TIPO_PRODUCTO
                && ! empty($row['producto_id']),
        )) {
            return redirect()
                ->route('clinica.vacunaciones.cargos.show', $vacuna_aplicada)
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

                    $movimientos = $this->cargoStock->registrarSalida(
                        $linea,
                        $sedeId,
                        (string) $user->getAuthIdentifier(),
                    );
                    $primerMov = $movimientos[0] ?? null;
                    if ($primerMov !== null) {
                        $linea->update(['movimiento_inventario_id' => $primerMov->id]);
                    }
                }
            });
        } catch (ValidationException $e) {
            $msg = $e->errors()['cantidad'][0] ?? __('consulta-cargos.flash.stock_insuficiente');

            return redirect()
                ->route('clinica.vacunaciones.cargos.show', $vacuna_aplicada)
                ->withErrors($e->errors())
                ->with('error', $msg);
        }

        return redirect()
            ->route('clinica.vacunaciones.cargos.show', $vacuna_aplicada)
            ->with('success', __('consulta-cargos.flash.confirmado'));
    }

    private function seedLineasInicialesSiVacio(
        ConsultaCargo $cargo,
        VacunaAplicada $vacuna,
        ClinicSetting $cfg,
    ): void {
        if ($cargo->lineas()->exists()) {
            return;
        }

        $lineas = [];
        $orden = 0;

        $servicio = null;
        if ($vacuna->servicio_clinico_id !== null) {
            $servicio = ServicioClinico::query()
                ->with(['productosPaquete.producto:id,nombre,sku,precio_venta'])
                ->whereKey($vacuna->servicio_clinico_id)
                ->first();
        }

        $precioLista = $servicio !== null
            ? number_format((float) (string) $servicio->precio_lista, 2, '.', '')
            : '0.00';

        $lineas[] = [
            'tipo_linea' => ConsultaCargoLinea::TIPO_SERVICIO,
            'producto_id' => null,
            'concepto' => $vacuna->descripcionParaVenta(),
            'cantidad' => '1.00',
            'precio_unitario' => $precioLista,
            'descuento_importe' => '0.00',
            'orden' => $orden++,
        ];

        if ($servicio !== null) {
            foreach ($servicio->productosPaquete as $item) {
                $prod = $item->producto;
                if ($prod === null) {
                    continue;
                }
                $lineas[] = [
                    'tipo_linea' => ConsultaCargoLinea::TIPO_PRODUCTO,
                    'producto_id' => $prod->id,
                    'concepto' => $prod->nombre,
                    'cantidad' => number_format((float) (string) $item->cantidad, 2, '.', ''),
                    // Precio 0: el cobro fijo va en la línea de servicio; estos ítems solo descuentan stock.
                    'precio_unitario' => '0.00',
                    'descuento_importe' => '0.00',
                    'orden' => $orden++,
                ];
            }
        } elseif ($vacuna->producto_id !== null) {
            $prod = Producto::query()->whereKey($vacuna->producto_id)->first(['id', 'nombre', 'precio_venta']);
            if ($prod !== null) {
                // Legacy sin paquete: una línea producto con precio de venta.
                $lineas = [[
                    'tipo_linea' => ConsultaCargoLinea::TIPO_PRODUCTO,
                    'producto_id' => $prod->id,
                    'concepto' => $vacuna->descripcionParaVenta(),
                    'cantidad' => '1.00',
                    'precio_unitario' => number_format((float) (string) ($prod->precio_venta ?? 0), 2, '.', ''),
                    'descuento_importe' => '0.00',
                    'orden' => 0,
                ]];
            }
        }

        $totales = ConsultaCargoTotales::fromLineas(
            $lineas,
            (bool) $cfg->precio_incluye_igv,
            (float) $cfg->igv_porcentaje,
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
            && ($user->can('consulta-cargos.view') || $user->can('vacunaciones.view')),
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
                || $user->can('productos.view')
            ),
            403,
        );
    }
}
