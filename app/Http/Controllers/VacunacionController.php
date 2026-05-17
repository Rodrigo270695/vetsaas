<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesClinicPdfBranding;
use App\Http\Requests\StoreVacunaAplicadaRequest;
use App\Http\Requests\UpdateVacunaAplicadaRequest;
use App\Models\ClinicSetting;
use App\Models\Consulta;
use App\Models\MovimientoInventario;
use App\Models\Paciente;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\User;
use App\Models\VacunaAplicada;
use App\Support\Vacunas\VacunaAplicadaStockSync;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class VacunacionController extends Controller
{
    use ResolvesClinicPdfBranding;

    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'aplicada_at',
        'paciente',
        'nombre_vacuna',
        'created_at',
    ];

    public function index(Request $request): InertiaResponse
    {
        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true)
            ? $perPageRequested
            : 10;

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $tz = config('app.timezone');
        $now = now($tz);
        $defaultDesde = $now->copy()->startOfMonth()->toDateString();
        $defaultHasta = $now->copy()->endOfMonth()->toDateString();

        $aplicadaDesde = $this->parseDateParam($request->query('aplicada_desde'));
        $aplicadaHasta = $this->parseDateParam($request->query('aplicada_hasta'));

        if ($aplicadaDesde === null || $aplicadaHasta === null) {
            $aplicadaDesde = $defaultDesde;
            $aplicadaHasta = $defaultHasta;
            $fueraDelMesActual = false;
        } else {
            if ($aplicadaDesde > $aplicadaHasta) {
                [$aplicadaDesde, $aplicadaHasta] = [$aplicadaHasta, $aplicadaDesde];
            }
            $fueraDelMesActual = ($aplicadaDesde !== $defaultDesde) || ($aplicadaHasta !== $defaultHasta);
        }

        $vacunaAbrirEditar = null;
        $editarVacunaRaw = $request->query('editar_vacuna_aplicada');
        if (is_string($editarVacunaRaw) && Str::isUuid($editarVacunaRaw) && ($request->user()?->can('vacunaciones.update') ?? false)) {
            $canAuditVac = $request->user()?->can('audit-trail.view') ?? false;
            $vacEditQuery = VacunaAplicada::query()
                ->with([
                    'paciente.propietario:id,nombres,apellidos,razon_social',
                    'producto:id,nombre,sku',
                    'veterinario:id,name',
                    'sede:id,nombre,codigo',
                    'consulta:id,atendido_at,cerrada_at',
                ])
                ->whereKey($editarVacunaRaw);

            if ($canAuditVac) {
                $vacEditQuery->with([
                    'creadoPor:id,name,email',
                    'actualizadoPor:id,name,email',
                ]);
            }

            $vacModel = $vacEditQuery->first();

            if ($vacModel !== null) {
                $vacunaAbrirEditar = $vacModel;
                $atVac = $vacModel->aplicada_at->copy()->timezone($tz);
                $aplicadaDesde = $atVac->copy()->startOfMonth()->toDateString();
                $aplicadaHasta = $atVac->copy()->endOfMonth()->toDateString();
                $fueraDelMesActual = ($aplicadaDesde !== $defaultDesde) || ($aplicadaHasta !== $defaultHasta);
            }
        }

        $inicioRango = Carbon::parse($aplicadaDesde, $tz)->startOfDay();
        $finRango = Carbon::parse($aplicadaHasta, $tz)->endOfDay();

        $canAudit = $request->user()?->can('audit-trail.view') ?? false;

        $query = VacunaAplicada::query()
            ->with([
                'paciente.propietario:id,nombres,apellidos,razon_social',
                'producto:id,nombre,sku',
                'veterinario:id,name',
                'sede:id,nombre,codigo',
                'consulta:id,atendido_at,cerrada_at',
            ]);

        if ($canAudit) {
            $query->with([
                'creadoPor:id,name,email',
                'actualizadoPor:id,name,email',
            ]);
        }

        $query->whereBetween('vacunas_aplicadas.aplicada_at', [$inicioRango, $finRango]);

        if ($sort === 'paciente') {
            $query
                ->join('pacientes as vac_pac', 'vac_pac.id', '=', 'vacunas_aplicadas.paciente_id')
                ->orderBy('vac_pac.nombre', $directionValid ? $direction : 'asc')
                ->orderByDesc('vacunas_aplicadas.aplicada_at')
                ->select('vacunas_aplicadas.*');
        } elseif ($sortValid) {
            $query->orderBy('vacunas_aplicadas.'.$sort, $directionValid ? $direction : 'desc');
            if ($sort !== 'aplicada_at') {
                $query->orderByDesc('vacunas_aplicadas.aplicada_at');
            }
        } else {
            $query->orderByDesc('vacunas_aplicadas.aplicada_at');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('vacunas_aplicadas.nombre_vacuna', 'ILIKE', "%{$search}%")
                    ->orWhere('vacunas_aplicadas.lote', 'ILIKE', "%{$search}%")
                    ->orWhere('vacunas_aplicadas.esquema_antigenos', 'ILIKE', "%{$search}%")
                    ->orWhereHas('paciente', function ($q2) use ($search) {
                        $q2->where('nombre', 'ILIKE', "%{$search}%")
                            ->orWhereHas('propietario', function ($q3) use ($search) {
                                $q3->where('nombres', 'ILIKE', "%{$search}%")
                                    ->orWhere('apellidos', 'ILIKE', "%{$search}%")
                                    ->orWhere('razon_social', 'ILIKE', "%{$search}%");
                            });
                    })
                    ->orWhereHas('producto', function ($q4) use ($search) {
                        $q4->where('nombre', 'ILIKE', "%{$search}%")
                            ->orWhere('sku', 'ILIKE', "%{$search}%");
                    });
            });
        }

        $vacunas = $query->paginate($perPage)->withQueryString();

        $totalEnRango = VacunaAplicada::query()
            ->whereBetween('aplicada_at', [$inicioRango, $finRango])
            ->count();

        $pacientesOpciones = Paciente::query()
            ->with(['propietario:id,nombres,apellidos,razon_social'])
            ->where('activo', true)
            ->orderBy('nombre')
            ->limit(500)
            ->get(['id', 'nombre', 'propietario_id']);

        $tenantId = tenant_id();
        $usuariosOpciones = User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name']);

        $sedesOpciones = Sede::query()
            ->where('tenant_id', $tenantId)
            ->where('activa', true)
            ->orderBy('nombre')
            ->limit(100)
            ->get(['id', 'nombre', 'codigo']);

        $vacunaPrefill = $this->vacunaPrefillDesdeQuery($request);

        return Inertia::render('clinica/vacunaciones/index', [
            'vacunas' => $vacunas,
            'pacientes_opciones' => $pacientesOpciones,
            'usuarios_opciones' => $usuariosOpciones,
            'sedes_opciones' => $sedesOpciones,
            'vacuna_prefill' => $vacunaPrefill,
            'vacuna_abrir_editar' => $vacunaAbrirEditar,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'aplicada_desde' => $aplicadaDesde,
                'aplicada_hasta' => $aplicadaHasta,
            ],
            'aplicacion_filtro_ui' => [
                'default_desde' => $defaultDesde,
                'default_hasta' => $defaultHasta,
                'fuera_del_mes_actual' => $fueraDelMesActual,
            ],
            'stats' => [
                'total' => $totalEnRango,
                'coincidencias' => $vacunas->total(),
            ],
        ]);
    }

    public function productosVacuna(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $items = Producto::query()
            ->where('activo', true)
            ->where('medicamento', true)
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
            ->get(['id', 'nombre', 'sku', 'unidad']);

        return response()->json(['data' => $items]);
    }

    /**
     * PDF del carnet de vacunación de un paciente (branding desde configuración general).
     *
     * Por defecto: stream con visualización en el navegador (p. ej. nueva pestaña e imprimir).
     * Query `?download=1`: forzar descarga del archivo (p. ej. adjunto por WhatsApp).
     */
    public function carnetPdf(Request $request, Paciente $paciente): Response
    {
        abort_unless($request->user()?->can('vacunaciones.view') ?? false, 403);

        $paciente->load(['propietario:id,nombres,apellidos,razon_social']);

        $vacunas = VacunaAplicada::query()
            ->where('paciente_id', $paciente->id)
            ->with(['veterinario:id,name', 'sede:id,nombre,codigo'])
            ->orderByDesc('aplicada_at')
            ->get();

        $clinic = ClinicSetting::current();
        $logoDataUri = $this->clinicLogoDataUri($clinic);
        $colorPrimario = $this->sanitizeHexColor($clinic->color_primario, '#166534');
        $colorSecundario = $this->sanitizeHexColor($clinic->color_secundario, '#f0fdf4');

        $clinicNombre = $clinic->nombre_comercial
            ?: $clinic->razon_social
            ?: (string) config('app.name', 'Clínica');

        $propietarioNombre = $this->propietarioNombreParaPdf($paciente);
        $tz = (string) config('app.timezone', 'UTC');

        $vacunasRows = $vacunas->map(function (VacunaAplicada $v) use ($tz): array {
            $aplicada = '—';
            if ($v->aplicada_at !== null) {
                $aplicada = Carbon::parse($v->aplicada_at)->timezone($tz)->format('d/m/Y H:i');
            }

            $proxima = '—';
            if ($v->fecha_proxima_sugerida !== null) {
                $proxima = Carbon::parse($v->fecha_proxima_sugerida)->format('d/m/Y');
            }

            $esquema = $v->esquema_antigenos !== null && trim((string) $v->esquema_antigenos) !== ''
                ? trim((string) $v->esquema_antigenos)
                : '—';

            $categoriaLabel = match ($v->categoria_registro) {
                VacunaAplicada::CATEGORIA_DESPARASITACION => __('carnet_vacunacion.categoria_desparasitacion'),
                VacunaAplicada::CATEGORIA_OTRO => __('carnet_vacunacion.categoria_otro'),
                VacunaAplicada::CATEGORIA_VACUNA => __('carnet_vacunacion.categoria_vacuna'),
                default => __('carnet_vacunacion.categoria_vacuna'),
            };

            $sedeTxt = null;
            if ($v->sede !== null) {
                $sedeTxt = $v->sede->nombre;
                if ($v->sede->codigo) {
                    $sedeTxt .= ' ('.$v->sede->codigo.')';
                }
            }

            return [
                'aplicada_at' => $aplicada,
                'categoria_label' => $categoriaLabel,
                'nombre_vacuna' => $v->nombre_vacuna,
                'esquema_antigenos' => $esquema,
                'numero_dosis' => $v->numero_dosis,
                'fecha_proxima_sugerida' => $proxima,
                'lote' => $v->lote,
                'veterinario' => $v->veterinario?->name,
                'sede' => $sedeTxt,
            ];
        });

        $generadoEn = now($tz)->format('d/m/Y H:i');

        $pdf = Pdf::loadView('pdf.carnet-vacunacion', [
            'clinicNombre' => $clinicNombre,
            'logoDataUri' => $logoDataUri,
            'colorPrimario' => $colorPrimario,
            'colorSecundario' => $colorSecundario,
            'clinicEmail' => $clinic->email_institucional,
            'clinicTelefono' => $clinic->telefono_principal,
            'clinicWeb' => $clinic->web_url,
            'clinicDireccion' => $clinic->direccion_fiscal,
            'paciente' => $paciente,
            'propietarioNombre' => $propietarioNombre,
            'vacunas' => $vacunasRows,
            'generadoEn' => $generadoEn,
            'vacunasCount' => $vacunas->count(),
        ]);
        $pdf->setPaper('a4', 'portrait');

        $slug = Str::slug($paciente->nombre) ?: 'paciente';
        $filename = 'carnet-vacunas-'.$slug.'.pdf';

        // Por defecto: inline en el navegador (nueva pestaña, imprimir sin forzar descarga).
        // ?download=1 para adjuntar como descarga (p. ej. envío por WhatsApp en el futuro).
        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    public function store(StoreVacunaAplicadaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['nombre_vacuna'] = Str::limit(trim($data['nombre_vacuna']), 500, '');
        $data['created_by_id'] = Auth::id();
        $data['updated_by_id'] = Auth::id();

        try {
            DB::transaction(function () use ($data): void {
                /** @var VacunaAplicada $vacuna */
                $vacuna = VacunaAplicada::query()->create($data);

                if (VacunaAplicadaStockSync::debeDescontar($vacuna)) {
                    $mov = VacunaAplicadaStockSync::registrarSalida($vacuna, Auth::id() !== null ? (string) Auth::id() : null);
                    $vacuna->forceFill(['movimiento_inventario_id' => $mov->id])->save();
                }
            });
        } catch (ValidationException $e) {
            $errors = $e->errors();
            if (isset($errors['cantidad'])) {
                return redirect()
                    ->back()
                    ->withErrors(['producto_id' => __('vacunaciones.stock.insufficient_stock')])
                    ->withInput();
            }

            return redirect()
                ->back()
                ->withErrors($errors)
                ->withInput();
        }

        return redirect()
            ->route('clinica.vacunaciones.index', $request->only([
                'search', 'per_page', 'sort', 'direction', 'aplicada_desde', 'aplicada_hasta',
            ]))
            ->with('success', __('vacunaciones.flash.created'));
    }

    public function update(
        UpdateVacunaAplicadaRequest $request,
        VacunaAplicada $vacuna_aplicada,
    ): RedirectResponse {
        $data = $request->validated();
        $data['nombre_vacuna'] = Str::limit(trim($data['nombre_vacuna']), 500, '');
        $data['updated_by_id'] = Auth::id();

        try {
            DB::transaction(function () use ($vacuna_aplicada, $data): void {
                $movAnterior = null;
                if ($vacuna_aplicada->movimiento_inventario_id !== null) {
                    $movAnterior = MovimientoInventario::query()->find($vacuna_aplicada->movimiento_inventario_id);
                    if ($movAnterior !== null) {
                        VacunaAplicadaStockSync::revertirPorMovimiento(
                            $movAnterior,
                            Auth::id() !== null ? (string) Auth::id() : null,
                        );
                    }
                }

                $vacuna_aplicada->fill($data);
                $vacuna_aplicada->movimiento_inventario_id = null;
                $vacuna_aplicada->save();

                if (VacunaAplicadaStockSync::debeDescontar($vacuna_aplicada)) {
                    $mov = VacunaAplicadaStockSync::registrarSalida(
                        $vacuna_aplicada,
                        Auth::id() !== null ? (string) Auth::id() : null,
                    );
                    $vacuna_aplicada->forceFill(['movimiento_inventario_id' => $mov->id])->save();
                }
            });
        } catch (ValidationException $e) {
            $errors = $e->errors();
            if (isset($errors['cantidad'])) {
                return redirect()
                    ->back()
                    ->withErrors(['producto_id' => __('vacunaciones.stock.insufficient_stock')])
                    ->withInput();
            }

            return redirect()
                ->back()
                ->withErrors($errors)
                ->withInput();
        }

        return redirect()
            ->route('clinica.vacunaciones.index', $request->only([
                'search', 'per_page', 'sort', 'direction', 'aplicada_desde', 'aplicada_hasta',
            ]))
            ->with('success', __('vacunaciones.flash.updated'));
    }

    public function destroy(Request $request, VacunaAplicada $vacuna_aplicada): RedirectResponse
    {
        abort_unless($request->user()?->can('vacunaciones.delete') ?? false, 403);

        try {
            DB::transaction(function () use ($vacuna_aplicada): void {
                if ($vacuna_aplicada->movimiento_inventario_id !== null) {
                    $mov = MovimientoInventario::query()->find($vacuna_aplicada->movimiento_inventario_id);
                    if ($mov !== null) {
                        VacunaAplicadaStockSync::revertirPorMovimiento(
                            $mov,
                            Auth::id() !== null ? (string) Auth::id() : null,
                        );
                    }
                    $vacuna_aplicada->forceFill(['movimiento_inventario_id' => null])->save();
                }

                $vacuna_aplicada->delete();
            });
        } catch (ValidationException $e) {
            $errors = $e->errors();
            if (isset($errors['cantidad'])) {
                return redirect()
                    ->back()
                    ->withErrors(['producto_id' => __('vacunaciones.stock.revert_failed')]);
            }

            return redirect()
                ->back()
                ->withErrors($errors);
        }

        return redirect()
            ->route('clinica.vacunaciones.index', $request->only([
                'search', 'per_page', 'sort', 'direction', 'aplicada_desde', 'aplicada_hasta',
            ]))
            ->with('success', __('vacunaciones.flash.deleted'));
    }

    /**
     * @return array{paciente_id: string, consulta_id: string|null}|null
     */
    private function vacunaPrefillDesdeQuery(Request $request): ?array
    {
        if (! $request->user()?->can('vacunaciones.create')) {
            return null;
        }

        $pid = $request->query('prefill_paciente_id');
        if (! is_string($pid) || ! Str::isUuid($pid)) {
            return null;
        }

        if (! Paciente::query()->whereKey($pid)->where('activo', true)->exists()) {
            return null;
        }

        $out = [
            'paciente_id' => $pid,
            'consulta_id' => null,
        ];

        $cid = $request->query('prefill_consulta_id');
        if (! is_string($cid) || ! Str::isUuid($cid)) {
            return $out;
        }

        $consulta = Consulta::query()
            ->with('historiaClinica:id,paciente_id')
            ->find($cid);

        if ($consulta === null || $consulta->historiaClinica === null) {
            return $out;
        }

        if ((string) $consulta->historiaClinica->paciente_id !== $pid || $consulta->cerrada_at !== null) {
            return $out;
        }

        $out['consulta_id'] = $cid;

        return $out;
    }

    private function parseDateParam(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
