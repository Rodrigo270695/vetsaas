<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertConsultaCargoRequest;
use App\Models\CajaSesion;
use App\Models\ClinicSetting;
use App\Models\ConsultaCargo;
use App\Models\ConsultaCargoLinea;
use App\Models\Internamiento;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Support\Caja\TicketAnchoMm;
use App\Support\ConsultaCargo\ConsultaCargoStockSync;
use App\Support\ConsultaCargo\ConsultaCargoTotales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class InternamientoCargoController extends Controller
{
    public function __construct(
        private readonly ConsultaCargoStockSync $cargoStock,
    ) {}
    public function show(Request $request, Internamiento $internamiento): Response
    {
        $this->ensurePuedeVer($request);

        $cfg = ClinicSetting::query()->first();
        if ($cfg === null) {
            abort(503, 'Configuración de clínica no disponible.');
        }

        ConsultaCargo::query()->firstOrCreate(
            ['internamiento_id' => $internamiento->id],
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

        $internamiento->load([
            'paciente.propietario:id,nombres,apellidos,razon_social',
            'veterinario:id,name',
            'consulta:id,atendido_at',
            'cargo.lineas' => fn ($q) => $q->orderBy('orden')->with('producto:id,nombre,sku,unidad'),
        ]);

        $cargo = $internamiento->cargo;
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

        $tz = config('app.timezone');

        return Inertia::render('clinica/hospitalizacion/cargos', [
            'internamiento' => $internamiento,
            'cargo' => $cargo,
            'dias_estadia' => $this->diasEstadia($internamiento, $tz),
            'cobro' => [
                'venta_id' => $cargo->venta_id,
                'venta_numero' => $ventaVinculada?->numero,
                'puede_cobrar' => $puedeCobrarPorPermiso && $sesionCajaAbierta,
                'requiere_sesion_caja' => $puedeCobrarPorPermiso && ! $sesionCajaAbierta,
                'url_cobrar' => route('caja.ventas.create-desde-internamiento', ['internamiento' => $internamiento]),
                'url_sesiones_caja' => route('caja.sesiones.index'),
                'url_cargos_consulta' => $internamiento->consulta_id !== null
                    ? route('clinica.historias-clinicas.consultas.cargos.show', $internamiento->consulta_id)
                    : null,
            ],
            'clinic_billing' => [
                'moneda' => $cfg->moneda,
                'igv_porcentaje' => (float) $cfg->igv_porcentaje,
                'precio_incluye_igv' => (bool) $cfg->precio_incluye_igv,
                'ticket_ancho_mm' => TicketAnchoMm::normalize((string) $cfg->ticket_ancho_mm),
            ],
        ]);
    }

    public function ticket(Request $request, Internamiento $internamiento): View
    {
        $this->ensurePuedeVer($request);

        $cfg = ClinicSetting::query()->first();
        if ($cfg === null) {
            abort(503);
        }

        $internamiento->load([
            'paciente.propietario:id,nombres,apellidos,razon_social',
            'veterinario:id,name',
            'cargo.lineas' => fn ($q) => $q->orderBy('orden')->with('producto:id,nombre,sku,unidad'),
        ]);

        $cargo = $internamiento->cargo;
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
            'paciente_nombre' => $internamiento->paciente->nombre,
            'veterinario_nombre' => $internamiento->veterinario?->name,
            'fecha_referencia' => $internamiento->ingreso_at->copy()->timezone($tz),
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

    public function update(UpsertConsultaCargoRequest $request, Internamiento $internamiento): RedirectResponse
    {
        $this->ensurePuedeVer($request);

        $cargo = ConsultaCargo::query()->where('internamiento_id', $internamiento->id)->first();
        if ($cargo === null || ! $cargo->esBorrador()) {
            return redirect()
                ->route('clinica.hospitalizacion.cargos.show', $internamiento)
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
            ->route('clinica.hospitalizacion.cargos.show', $internamiento)
            ->with('success', __('consulta-cargos.flash.guardado'));
    }

    public function confirmar(Request $request, Internamiento $internamiento): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User
            && ($user->can('consulta-cargos.manage') || $user->can('hospitalizacion.update')),
            403,
        );

        $this->ensurePuedeVer($request);

        $cargo = ConsultaCargo::query()->where('internamiento_id', $internamiento->id)->first();
        if ($cargo === null || ! $cargo->esBorrador()) {
            return redirect()
                ->route('clinica.hospitalizacion.cargos.show', $internamiento)
                ->with('error', __('consulta-cargos.flash.solo_borrador'));
        }

        if ($cargo->lineas()->count() === 0) {
            return redirect()
                ->route('clinica.hospitalizacion.cargos.show', $internamiento)
                ->with('error', __('consulta-cargos.flash.sin_lineas'));
        }

        $sedeId = (string) ($internamiento->sede_id ?? '');
        if ($sedeId === '') {
            return redirect()
                ->route('clinica.hospitalizacion.cargos.show', $internamiento)
                ->with('error', __('consulta-cargos.flash.sin_sede_stock'));
        }

        try {
            DB::transaction(function () use ($cargo, $sedeId, $user): void {
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

                $cargo->update([
                    'estado' => ConsultaCargo::ESTADO_CONFIRMADO,
                    'updated_by_id' => Auth::id(),
                ]);
            });
        } catch (ValidationException $e) {
            $msg = $e->errors()['cantidad'][0] ?? __('consulta-cargos.flash.stock_insuficiente');

            return redirect()
                ->route('clinica.hospitalizacion.cargos.show', $internamiento)
                ->withErrors($e->errors())
                ->with('error', $msg);
        }

        return redirect()
            ->route('clinica.hospitalizacion.cargos.show', $internamiento)
            ->with('success', __('consulta-cargos.flash.confirmado'));
    }

    private function diasEstadia(Internamiento $internamiento, string $tz): int
    {
        $inicio = $internamiento->ingreso_at->copy()->timezone($tz)->startOfDay();
        $fin = ($internamiento->alta_at ?? now($tz))->copy()->timezone($tz)->startOfDay();

        return max(1, $inicio->diffInDays($fin) + 1);
    }

    private function ensurePuedeVer(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User
            && ($user->can('consulta-cargos.view') || $user->can('hospitalizacion.view')),
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
                || $user->can('hospitalizacion.view')
                || $user->can('productos.view')
            ),
            403,
        );
    }
}
