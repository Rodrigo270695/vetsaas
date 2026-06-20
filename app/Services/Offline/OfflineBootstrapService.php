<?php

declare(strict_types=1);

namespace App\Services\Offline;

use App\Grooming\GroomingCatalogoMode;
use App\Grooming\GroomingCatalogoServicio;
use App\Hotel\HotelCatalogoMode;
use App\Hotel\HotelCatalogoTipoEstancia;
use App\Models\CajaSesion;
use App\Models\CategoriaProducto;
use App\Models\Cita;
use App\Models\ClinicSetting;
use App\Models\Consulta;
use App\Models\GroomingServicio;
use App\Models\HotelTipoEstancia;
use App\Models\Paciente;
use App\Models\Producto;
use App\Models\Propietario;
use App\Models\Proveedor;
use App\Models\Sede;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Inventario\UnidadMedidaOpciones;
use App\Support\Pacientes\PacienteEspecieRazaCatalogo;
use App\Support\PlanCapabilities;
use Illuminate\Support\Facades\DB;

final class OfflineBootstrapService
{
    /**
     * @return array<string, mixed>
     */
    public function caja(User $user, ?Tenant $tenant): array
    {
        $miSesion = CajaSesion::query()
            ->where('estado', CajaSesion::ESTADO_ABIERTA)
            ->where('opened_by_id', $user->id)
            ->first();

        $sedeNombre = null;
        $sedeId = $miSesion?->sede_id;
        if ($miSesion !== null) {
            $sedeNombre = Sede::query()->whereKey($miSesion->sede_id)->value('nombre');
        }

        $clinic = ClinicSetting::current();

        $propietarios = Propietario::query()
            ->where('activo', true)
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get(['id', 'nombres', 'apellidos', 'razon_social', 'numero_documento'])
            ->map(fn (Propietario $pr): array => [
                'id' => $pr->id,
                'label' => $pr->razon_social ?: trim(implode(' ', array_filter([$pr->nombres, $pr->apellidos]))),
                'doc' => $pr->numero_documento,
            ])
            ->values()
            ->all();

        $productos = $this->productosParaSede($sedeId);

        $pacientes = Paciente::query()
            ->where('activo', true)
            ->whereIn('propietario_id', collect($propietarios)->pluck('id'))
            ->orderBy('nombre')
            ->limit(500)
            ->get(['id', 'nombre', 'propietario_id'])
            ->map(fn (Paciente $p): array => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'propietario_id' => $p->propietario_id,
            ])
            ->values()
            ->all();

        return [
            'cached_at' => now()->toIso8601String(),
            'puede_vender' => $miSesion !== null,
            'mi_sesion' => $miSesion === null ? null : [
                'id' => $miSesion->id,
                'sede_id' => $miSesion->sede_id,
                'sede_nombre' => $sedeNombre ?? '—',
                'moneda' => $miSesion->moneda,
            ],
            'clinica' => [
                'moneda' => $clinic->moneda,
                'igv_porcentaje' => (string) $clinic->igv_porcentaje,
                'precio_incluye_igv' => (bool) $clinic->precio_incluye_igv,
                'emite_comprobantes_sunat' => (bool) $clinic->emite_comprobantes_sunat,
                'plan_permite_boletas' => PlanCapabilities::boletasElectronicas($tenant),
                'plan_permite_facturas' => PlanCapabilities::facturasElectronicas($tenant),
            ],
            'propietarios_opciones' => $propietarios,
            'productos' => $productos,
            'pacientes' => $pacientes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function clinica(User $user): array
    {
        $tz = config('app.timezone');
        $now = now($tz);
        $inicioRango = $now->copy()->subDays(7)->startOfDay();
        $finRango = $now->copy()->addDays(14)->endOfDay();
        $tenantId = tenant_id();

        $propietarios = Propietario::query()
            ->where('activo', true)
            ->orderByDesc('updated_at')
            ->limit(300)
            ->get([
                'id',
                'nombres',
                'apellidos',
                'razon_social',
                'numero_documento',
                'telefono',
                'email',
            ])
            ->map(fn (Propietario $pr): array => [
                'id' => $pr->id,
                'nombres' => $pr->nombres,
                'apellidos' => $pr->apellidos,
                'razon_social' => $pr->razon_social,
                'numero_documento' => $pr->numero_documento,
                'telefono' => $pr->telefono,
                'email' => $pr->email,
                'label' => $pr->razon_social ?: trim(implode(' ', array_filter([$pr->nombres, $pr->apellidos]))),
            ])
            ->values()
            ->all();

        $pacientes = Paciente::query()
            ->with(['propietario:id,nombres,apellidos,razon_social'])
            ->where('activo', true)
            ->orderBy('nombre')
            ->limit(800)
            ->get(['id', 'nombre', 'propietario_id', 'especie', 'raza'])
            ->map(fn (Paciente $p): array => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'propietario_id' => $p->propietario_id,
                'especie' => $p->especie,
                'raza' => $p->raza,
                'propietario' => $p->propietario === null ? null : [
                    'id' => $p->propietario->id,
                    'nombres' => $p->propietario->nombres,
                    'apellidos' => $p->propietario->apellidos,
                    'razon_social' => $p->propietario->razon_social,
                ],
            ])
            ->values()
            ->all();

        $citas = Cita::query()
            ->with([
                'paciente:id,nombre,propietario_id',
                'paciente.propietario:id,nombres,apellidos,razon_social',
                'veterinario:id,name',
                'sede:id,nombre,codigo',
            ])
            ->whereBetween('inicio_at', [$inicioRango, $finRango])
            ->orderBy('inicio_at')
            ->limit(500)
            ->get()
            ->map(fn (Cita $c): array => [
                'id' => $c->id,
                'paciente_id' => $c->paciente_id,
                'veterinario_id' => $c->veterinario_id,
                'sede_id' => $c->sede_id,
                'inicio_at' => $c->inicio_at->toIso8601String(),
                'duracion_minutos' => $c->duracion_minutos,
                'estado' => $c->estado,
                'motivo' => $c->motivo,
                'notas' => $c->notas,
                'paciente' => $c->paciente === null ? null : [
                    'id' => $c->paciente->id,
                    'nombre' => $c->paciente->nombre,
                    'propietario' => $c->paciente->propietario === null ? null : [
                        'id' => $c->paciente->propietario->id,
                        'nombres' => $c->paciente->propietario->nombres,
                        'apellidos' => $c->paciente->propietario->apellidos,
                        'razon_social' => $c->paciente->propietario->razon_social,
                    ],
                ],
                'veterinario' => $c->veterinario === null ? null : [
                    'id' => $c->veterinario->id,
                    'name' => $c->veterinario->name,
                ],
                'sede' => $c->sede === null ? null : [
                    'id' => $c->sede->id,
                    'nombre' => $c->sede->nombre,
                    'codigo' => $c->sede->codigo,
                ],
            ])
            ->values()
            ->all();

        $sedes = Sede::query()
            ->where('tenant_id', $tenantId)
            ->where('activa', true)
            ->orderBy('nombre')
            ->limit(100)
            ->get(['id', 'nombre', 'codigo'])
            ->map(fn (Sede $s): array => [
                'id' => $s->id,
                'nombre' => $s->nombre,
                'codigo' => $s->codigo,
            ])
            ->values()
            ->all();

        $veterinarios = User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name'])
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
            ])
            ->values()
            ->all();

        $productosMedicamento = Producto::query()
            ->where('activo', true)
            ->where('medicamento', true)
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->limit(200)
            ->get(['id', 'nombre', 'sku', 'unidad'])
            ->map(fn (Producto $p): array => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'sku' => $p->sku,
                'unidad' => $p->unidad,
            ])
            ->values()
            ->all();

        $consultasAbiertas = Consulta::query()
            ->whereNull('cerrada_at')
            ->with([
                'historiaClinica:id,paciente_id',
                'historiaClinica.paciente:id,nombre',
            ])
            ->orderByDesc('atendido_at')
            ->limit(150)
            ->get(['id', 'atendido_at', 'historia_clinica_id'])
            ->map(fn (Consulta $c): array => [
                'id' => $c->id,
                'atendido_at' => $c->atendido_at->toIso8601String(),
                'historia_clinica_id' => $c->historia_clinica_id,
                'historia_clinica' => $c->historiaClinica === null ? null : [
                    'id' => $c->historiaClinica->id,
                    'paciente_id' => $c->historiaClinica->paciente_id,
                    'paciente' => $c->historiaClinica->paciente === null ? null : [
                        'id' => $c->historiaClinica->paciente->id,
                        'nombre' => $c->historiaClinica->paciente->nombre,
                    ],
                ],
            ])
            ->values()
            ->all();

        return [
            'cached_at' => now()->toIso8601String(),
            'propietarios' => $propietarios,
            'pacientes' => $pacientes,
            'citas' => $citas,
            'sedes' => $sedes,
            'veterinarios' => $veterinarios,
            'catalogo_especie_raza' => PacienteEspecieRazaCatalogo::payload(),
            'productos_vacuna' => $productosMedicamento,
            'productos_medicamento' => $productosMedicamento,
            'consultas_abiertas' => $consultasAbiertas,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function inventario(User $user): array
    {
        $tenantId = tenant_id();

        $productos = Producto::query()
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->limit(500)
            ->get([
                'id',
                'nombre',
                'sku',
                'codigo_barras',
                'unidad',
                'precio_venta',
                'precio_compra',
                'medicamento',
                'categoria_id',
                'activo',
            ])
            ->map(fn (Producto $p): array => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'sku' => $p->sku,
                'codigo_barras' => $p->codigo_barras,
                'unidad' => $p->unidad,
                'precio_venta' => $p->precio_venta !== null ? (string) $p->precio_venta : null,
                'precio_compra' => $p->precio_compra !== null ? (string) $p->precio_compra : null,
                'medicamento' => (bool) $p->medicamento,
                'categoria_id' => $p->categoria_id,
                'activo' => (bool) $p->activo,
            ])
            ->values()
            ->all();

        $categorias = CategoriaProducto::query()
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->limit(200)
            ->get(['id', 'nombre', 'slug', 'parent_id', 'activo'])
            ->map(fn (CategoriaProducto $c): array => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'slug' => $c->slug,
                'parent_id' => $c->parent_id,
                'activo' => (bool) $c->activo,
            ])
            ->values()
            ->all();

        $proveedores = Proveedor::query()
            ->whereNull('deleted_at')
            ->orderBy('razon_social')
            ->limit(200)
            ->get(['id', 'ruc', 'razon_social', 'activo'])
            ->map(fn (Proveedor $pr): array => [
                'id' => $pr->id,
                'ruc' => $pr->ruc,
                'razon_social' => $pr->razon_social,
                'activo' => (bool) $pr->activo,
            ])
            ->values()
            ->all();

        $sedes = Sede::query()
            ->where('tenant_id', $tenantId)
            ->where('activa', true)
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->limit(100)
            ->get(['id', 'nombre', 'codigo'])
            ->map(fn (Sede $s): array => [
                'id' => $s->id,
                'nombre' => $s->nombre,
                'codigo' => $s->codigo,
            ])
            ->values()
            ->all();

        return [
            'cached_at' => now()->toIso8601String(),
            'productos' => $productos,
            'categorias' => $categorias,
            'proveedores' => $proveedores,
            'sedes' => $sedes,
            'unidades' => UnidadMedidaOpciones::forProductoForm(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function servicios(User $user): array
    {
        $tenantId = tenant_id();

        $pacientes = Paciente::query()
            ->with(['propietario:id,nombres,apellidos,razon_social'])
            ->where('activo', true)
            ->orderBy('nombre')
            ->limit(500)
            ->get(['id', 'nombre', 'propietario_id'])
            ->map(fn (Paciente $p): array => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'propietario_id' => $p->propietario_id,
                'propietario' => $p->propietario === null ? null : [
                    'id' => $p->propietario->id,
                    'nombres' => $p->propietario->nombres,
                    'apellidos' => $p->propietario->apellidos,
                    'razon_social' => $p->propietario->razon_social,
                ],
            ])
            ->values()
            ->all();

        $usuarios = User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name'])
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
            ])
            ->values()
            ->all();

        $sedes = Sede::query()
            ->where('tenant_id', $tenantId)
            ->where('activa', true)
            ->orderBy('nombre')
            ->limit(100)
            ->get(['id', 'nombre', 'codigo'])
            ->map(fn (Sede $s): array => [
                'id' => $s->id,
                'nombre' => $s->nombre,
                'codigo' => $s->codigo,
            ])
            ->values()
            ->all();

        $catalogoGroomingPersonalizado = GroomingCatalogoMode::usaCatalogoPersonalizado();
        $catalogoHotelPersonalizado = HotelCatalogoMode::usaCatalogoPersonalizado();

        $groomingServicios = $catalogoGroomingPersonalizado
            ? GroomingServicio::query()
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'categoria', 'codigo_legacy', 'precio_lista', 'moneda', 'duracion_minutos', 'activo', 'orden'])
                ->map(fn (GroomingServicio $s): array => [
                    'id' => $s->id,
                    'nombre' => $s->nombre,
                    'categoria' => $s->categoria,
                    'codigo_legacy' => $s->codigo_legacy,
                    'precio_lista' => $s->precio_lista !== null ? (string) $s->precio_lista : null,
                    'moneda' => $s->moneda,
                    'duracion_minutos' => $s->duracion_minutos,
                    'activo' => (bool) $s->activo,
                    'orden' => $s->orden,
                ])
                ->values()
                ->all()
            : [];

        $hotelTipos = $catalogoHotelPersonalizado
            ? HotelTipoEstancia::query()
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'categoria', 'codigo_legacy', 'precio_lista', 'moneda', 'activo', 'orden'])
                ->map(fn (HotelTipoEstancia $t): array => [
                    'id' => $t->id,
                    'nombre' => $t->nombre,
                    'categoria' => $t->categoria,
                    'codigo_legacy' => $t->codigo_legacy,
                    'precio_lista' => $t->precio_lista !== null ? (string) $t->precio_lista : null,
                    'moneda' => $t->moneda,
                    'activo' => (bool) $t->activo,
                    'orden' => $t->orden,
                ])
                ->values()
                ->all()
            : [];

        return [
            'cached_at' => now()->toIso8601String(),
            'pacientes' => $pacientes,
            'usuarios' => $usuarios,
            'sedes' => $sedes,
            'grooming_catalogo_personalizado' => $catalogoGroomingPersonalizado,
            'grooming_servicios' => $groomingServicios,
            'grooming_servicio_grupos' => $catalogoGroomingPersonalizado ? [] : GroomingCatalogoServicio::grupos(),
            'grooming_servicio_duraciones' => $catalogoGroomingPersonalizado
                ? collect($groomingServicios)->mapWithKeys(fn (array $s): array => [$s['id'] => $s['duracion_minutos']])->all()
                : GroomingCatalogoServicio::duracionesSugeridas(),
            'hotel_catalogo_personalizado' => $catalogoHotelPersonalizado,
            'hotel_tipos' => $hotelTipos,
            'hotel_tipo_grupos' => $catalogoHotelPersonalizado ? [] : HotelCatalogoTipoEstancia::grupos(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function productosParaSede(?string $sedeId): array
    {
        $query = Producto::query()
            ->where('productos.activo', true)
            ->whereNull('productos.deleted_at');

        if ($sedeId !== null) {
            $rows = (clone $query)
                ->leftJoin('existencias_sede as es', function ($join) use ($sedeId): void {
                    $join->on('es.producto_id', '=', 'productos.id')
                        ->where('es.sede_id', '=', $sedeId);
                })
                ->orderBy('productos.nombre')
                ->limit(400)
                ->get([
                    'productos.id',
                    'productos.nombre',
                    'productos.sku',
                    'productos.codigo_barras',
                    'productos.precio_venta',
                    'productos.unidad',
                    DB::raw('COALESCE(es.cantidad, 0) as stock_sede'),
                ]);
        } else {
            $rows = $query
                ->orderBy('productos.nombre')
                ->limit(400)
                ->get([
                    'productos.id',
                    'productos.nombre',
                    'productos.sku',
                    'productos.codigo_barras',
                    'productos.precio_venta',
                    'productos.unidad',
                ]);

            foreach ($rows as $p) {
                $p->setAttribute('stock_sede', '0');
            }
        }

        return $rows->map(fn (Producto $p): array => [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'sku' => $p->sku,
            'codigo_barras' => $p->codigo_barras,
            'precio_venta' => $p->precio_venta !== null ? (string) $p->precio_venta : null,
            'unidad' => $p->unidad,
            'stock_sede' => (string) ($p->stock_sede ?? '0'),
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function configuracion(User $user): array
    {
        $tenantId = tenant_id();

        $sedes = Sede::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->limit(100)
            ->get([
                'id',
                'nombre',
                'codigo',
                'activa',
                'telefono',
                'email',
                'direccion',
                'distrito_id',
                'distrito',
                'provincia',
                'departamento',
            ])
            ->map(fn (Sede $s): array => [
                'id' => $s->id,
                'nombre' => $s->nombre,
                'codigo' => $s->codigo,
                'activa' => (bool) $s->activa,
                'telefono' => $s->telefono,
                'email' => $s->email,
                'direccion' => $s->direccion,
                'distrito_id' => $s->distrito_id,
                'distrito' => $s->distrito,
                'provincia' => $s->provincia,
                'departamento' => $s->departamento,
            ])
            ->values()
            ->all();

        return [
            'cached_at' => now()->toIso8601String(),
            'sedes' => $sedes,
        ];
    }
}
