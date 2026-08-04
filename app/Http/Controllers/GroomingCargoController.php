<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertConsultaCargoRequest;
use App\Models\CajaSesion;
use App\Models\ClinicSetting;
use App\Models\ConsultaCargo;
use App\Models\ConsultaCargoLinea;
use App\Models\GroomingServicio;
use App\Models\GroomingServicioTarifa;
use App\Models\GroomingTurno;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Support\Caja\TicketAnchoMm;
use App\Support\ConsultaCargo\ConsultaCargoStockSync;
use App\Support\ConsultaCargo\ConsultaCargoTotales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class GroomingCargoController extends Controller
{
    public function __construct(
        private readonly ConsultaCargoStockSync $cargoStock,
    ) {}

    public function show(Request $request, GroomingTurno $groomingTurno): Response
    {
        $this->ensurePuedeVer($request);

        $cfg = ClinicSetting::query()->first();
        if ($cfg === null) {
            abort(503, 'Configuración de clínica no disponible.');
        }

        $cargo = ConsultaCargo::query()->firstOrCreate(
            ['grooming_turno_id' => $groomingTurno->id],
            [
                'estado' => ConsultaCargo::ESTADO_BORRADOR,
                'moneda' => $cfg->moneda,
                'subtotal_sin_igv' => 0,
                'igv_importe' => 0,
                'total' => 0,
                'created_by_id' => Auth::id(),
                'updated_by_id' => Auth::id(),
            ],
        );

        $this->seedLineaInicialSiVacio($cargo, $groomingTurno, $cfg);

        $groomingTurno->load([
            'paciente.propietario:id,nombres,apellidos,razon_social',
            'responsable:id,name',
            'cargo.lineas' => fn ($q) => $q->orderBy('orden')->with('producto:id,nombre,sku,unidad'),
        ]);

        $cargo = $groomingTurno->cargo;
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

        return Inertia::render('servicios/grooming/cargos', [
            'turno' => $groomingTurno,
            'cargo' => $cargo,
            'cobro' => [
                'venta_id' => $cargo->venta_id,
                'venta_numero' => $ventaVinculada?->numero,
                'puede_cobrar' => $puedeCobrarPorPermiso && $sesionCajaAbierta,
                'requiere_sesion_caja' => $puedeCobrarPorPermiso && ! $sesionCajaAbierta,
                'url_cobrar' => route('caja.ventas.create-desde-grooming', ['grooming_turno' => $groomingTurno]),
                'url_sesiones_caja' => route('caja.sesiones.index'),
            ],
            'clinic_billing' => [
                'moneda' => $cfg->moneda,
                'igv_porcentaje' => (float) $cfg->igv_porcentaje,
                'precio_incluye_igv' => (bool) $cfg->precio_incluye_igv,
                'ticket_ancho_mm' => TicketAnchoMm::normalize((string) $cfg->ticket_ancho_mm),
            ],
        ]);
    }

    public function ticket(Request $request, GroomingTurno $groomingTurno): View
    {
        $this->ensurePuedeVer($request);

        $cfg = ClinicSetting::query()->first();
        if ($cfg === null) {
            abort(503);
        }

        $groomingTurno->load([
            'paciente.propietario:id,nombres,apellidos,razon_social',
            'responsable:id,name',
            'cargo.lineas' => fn ($q) => $q->orderBy('orden')->with('producto:id,nombre,sku,unidad'),
        ]);

        $cargo = $groomingTurno->cargo;
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
            'paciente_nombre' => $groomingTurno->paciente->nombre,
            'veterinario_nombre' => $groomingTurno->responsable?->name,
            'fecha_referencia' => $groomingTurno->inicio_at->copy()->timezone($tz),
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

    public function update(UpsertConsultaCargoRequest $request, GroomingTurno $groomingTurno): RedirectResponse
    {
        $this->ensurePuedeVer($request);

        $cargo = ConsultaCargo::query()->where('grooming_turno_id', $groomingTurno->id)->first();
        if ($cargo === null || ! $cargo->esBorrador()) {
            return redirect()
                ->route('servicios.grooming.cargos.show', $groomingTurno)
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
            ->route('servicios.grooming.cargos.show', $groomingTurno)
            ->with('success', __('consulta-cargos.flash.guardado'));
    }

    public function confirmar(UpsertConsultaCargoRequest $request, GroomingTurno $groomingTurno): RedirectResponse
    {
        $user = $request->user();
        $this->ensurePuedeVer($request);

        $cargo = ConsultaCargo::query()->where('grooming_turno_id', $groomingTurno->id)->first();
        if ($cargo === null || $cargo->venta_id !== null) {
            return redirect()
                ->route('servicios.grooming.cargos.show', $groomingTurno)
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
                ->route('servicios.grooming.cargos.show', $groomingTurno)
                ->with('error', __('consulta-cargos.flash.sin_lineas'));
        }

        $totales = ConsultaCargoTotales::fromLineas(
            $lineasIn,
            (bool) $cfg->precio_incluye_igv,
            (float) $cfg->igv_porcentaje,
        );

        $sedeId = (string) ($groomingTurno->sede_id ?? '');
        if ($sedeId === '') {
            return redirect()
                ->route('servicios.grooming.cargos.show', $groomingTurno)
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
                ->route('servicios.grooming.cargos.show', $groomingTurno)
                ->withErrors($e->errors())
                ->with('error', $msg);
        }

        return redirect()
            ->route('servicios.grooming.cargos.show', $groomingTurno)
            ->with('success', __('consulta-cargos.flash.confirmado'));
    }

    private function seedLineaInicialSiVacio(ConsultaCargo $cargo, GroomingTurno $groomingTurno, ClinicSetting $cfg): void
    {
        if ($cargo->lineas()->exists()) {
            return;
        }

        $concepto = $groomingTurno->descripcionParaVenta();
        $precioLista = $this->precioListaGrooming($groomingTurno);

        $linea = [
            'tipo_linea' => ConsultaCargoLinea::TIPO_SERVICIO,
            'concepto' => $concepto,
            'cantidad' => '1.00',
            'precio_unitario' => $precioLista,
            'descuento_importe' => '0.00',
        ];

        $totales = ConsultaCargoTotales::fromLineas(
            [$linea],
            (bool) $cfg->precio_incluye_igv,
            (float) $cfg->igv_porcentaje,
        );

        DB::transaction(function () use ($cargo, $linea, $totales): void {
            ConsultaCargoLinea::query()->create([
                'consulta_cargo_id' => $cargo->id,
                'tipo_linea' => $linea['tipo_linea'],
                'producto_id' => null,
                'concepto' => $linea['concepto'],
                'cantidad' => $linea['cantidad'],
                'precio_unitario' => $linea['precio_unitario'],
                'descuento_importe' => $linea['descuento_importe'],
                'orden' => 0,
            ]);

            $cargo->update([
                'subtotal_sin_igv' => $totales['subtotal_sin_igv'],
                'igv_importe' => $totales['igv_importe'],
                'total' => $totales['total'],
                'updated_by_id' => Auth::id(),
            ]);
        });
    }

    private function precioListaGrooming(GroomingTurno $turno): string
    {
        $precioLista = '0.00';

        if ($turno->grooming_servicio_id !== null) {
            $servicioPersonalizado = GroomingServicio::query()
                ->whereKey($turno->grooming_servicio_id)
                ->where('activo', true)
                ->first();
            if ($servicioPersonalizado !== null) {
                return number_format((float) (string) $servicioPersonalizado->precio_lista, 2, '.', '');
            }
        }

        $tarifa = GroomingServicioTarifa::query()
            ->where('servicio', $turno->servicio)
            ->where('activo', true)
            ->first();

        if ($tarifa !== null) {
            $precioLista = number_format((float) (string) $tarifa->precio_lista, 2, '.', '');
        }

        return $precioLista;
    }

    private function ensurePuedeVer(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User
            && ($user->can('consulta-cargos.view') || $user->can('grooming.view')),
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
                || $user->can('grooming.view')
                || $user->can('grooming.update')
                || $user->can('productos.view')
            ),
            403,
        );
    }
}
