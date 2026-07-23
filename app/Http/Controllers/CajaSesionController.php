<?php

namespace App\Http\Controllers;

use App\Http\Requests\CloseCajaSesionRequest;
use App\Http\Requests\StoreCajaSesionRequest;
use App\Models\CajaSesion;
use App\Models\ClinicSetting;
use App\Models\Sede;
use App\Services\Caja\CajaSesionArqueoPdfService;
use App\Services\Caja\CajaSesionArqueoService;
use App\Support\Caja\TicketAnchoMm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CajaSesionController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'opened_at',
        'closed_at',
        'estado',
        'saldo_apertura',
    ];

    public function index(Request $request): InertiaResponse
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 403);

        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true) ? $perPageRequested : 10;

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);
        $directionSql = $directionValid ? $direction : 'desc';

        $estadoFiltro = (string) $request->string('estado', 'todas');
        if (! in_array($estadoFiltro, ['todas', CajaSesion::ESTADO_ABIERTA, CajaSesion::ESTADO_CERRADA], true)) {
            $estadoFiltro = 'todas';
        }

        $sedesActivas = Sede::query()
            ->where('tenant_id', $tenantId)
            ->where('activa', true)
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo']);

        $sedeIds = $sedesActivas->pluck('id')->all();

        $sedeRequested = (string) $request->string('sede_id', '');
        $sedeFiltro = '';
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $sedeRequested) === 1
            && in_array($sedeRequested, $sedeIds, true)) {
            $sedeFiltro = $sedeRequested;
        }

        $query = CajaSesion::query()
            ->with([
                'abiertaPor:id,name',
                'cerradaPor:id,name',
            ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('notas', 'like', '%'.addcslashes($search, '%_\\').'%');
                if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $search) === 1) {
                    $q->orWhere('id', $search);
                }
            });
        }

        if ($estadoFiltro !== 'todas') {
            $query->where('estado', $estadoFiltro);
        }

        if ($sedeFiltro !== '') {
            $query->where('sede_id', $sedeFiltro);
        }

        if ($sortValid) {
            $query->orderBy($sort, $directionSql);
            if ($sort !== 'opened_at') {
                $query->orderByDesc('opened_at');
            }
        } else {
            $query->orderByDesc('opened_at');
        }

        $sesiones = $query->paginate($perPage)->withQueryString();

        $sedeNombres = Sede::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $sesiones->pluck('sede_id')->unique()->filter()->all())
            ->pluck('nombre', 'id');

        $sesiones->getCollection()->transform(function (CajaSesion $s) use ($sedeNombres): CajaSesion {
            $s->setAttribute('sede_nombre', $sedeNombres[$s->sede_id] ?? '—');

            return $s;
        });

        $abiertasCount = CajaSesion::query()
            ->where('estado', CajaSesion::ESTADO_ABIERTA)
            ->when($sedeFiltro !== '', fn ($q) => $q->where('sede_id', $sedeFiltro))
            ->count();
        $cerradasCount = CajaSesion::query()
            ->where('estado', CajaSesion::ESTADO_CERRADA)
            ->when($sedeFiltro !== '', fn ($q) => $q->where('sede_id', $sedeFiltro))
            ->count();
        $totalCount = CajaSesion::query()
            ->when($sedeFiltro !== '', fn ($q) => $q->where('sede_id', $sedeFiltro))
            ->count();

        $miSesionAbierta = CajaSesion::query()
            ->where('estado', CajaSesion::ESTADO_ABIERTA)
            ->where('opened_by_id', Auth::id())
            ->with(['abiertaPor:id,name'])
            ->first();

        if ($miSesionAbierta !== null) {
            $miSesionAbierta->setAttribute(
                'sede_nombre',
                Sede::query()->whereKey($miSesionAbierta->sede_id)->value('nombre') ?? '—',
            );
        }

        return Inertia::render('caja/sesiones/index', [
            'sesiones' => $sesiones,
            'sedes_opciones' => $sedesActivas,
            'mi_sesion_abierta' => $miSesionAbierta,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'estado' => $estadoFiltro,
                'sede_id' => $sedeFiltro,
            ],
            'stats' => [
                'total' => $totalCount,
                'abiertas' => $abiertasCount,
                'cerradas' => $cerradasCount,
                'coincidencias' => $sesiones->total(),
            ],
            'sin_sedes' => $sedesActivas->isEmpty(),
            'ticket_ancho_mm' => TicketAnchoMm::normalize(
                (string) (ClinicSetting::query()->value('ticket_ancho_mm') ?? TicketAnchoMm::DEFAULT),
            ),
        ]);
    }

    public function store(StoreCajaSesionRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data): void {
            $usuarioTieneAbierta = CajaSesion::query()
                ->where('estado', CajaSesion::ESTADO_ABIERTA)
                ->where('opened_by_id', Auth::id())
                ->lockForUpdate()
                ->exists();

            if ($usuarioTieneAbierta) {
                throw ValidationException::withMessages([
                    'sede_id' => __('caja.validation.usuario_tiene_sesion_abierta'),
                ]);
            }

            $exists = CajaSesion::query()
                ->where('sede_id', $data['sede_id'])
                ->where('estado', CajaSesion::ESTADO_ABIERTA)
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'sede_id' => __('caja.validation.sede_tiene_sesion_abierta'),
                ]);
            }

            CajaSesion::query()->create([
                'sede_id' => $data['sede_id'],
                'estado' => CajaSesion::ESTADO_ABIERTA,
                'moneda' => $data['moneda'],
                'saldo_apertura' => $data['saldo_apertura'],
                'opened_at' => now(),
                'notas' => $data['notas'] ?? null,
                'opened_by_id' => Auth::id(),
            ]);
        });

        return redirect()
            ->route('caja.sesiones.index', $request->query())
            ->with('success', __('caja.flash.sesion_abierta'));
    }

    public function cerrar(
        CloseCajaSesionRequest $request,
        CajaSesion $cajaSesion,
        CajaSesionArqueoService $arqueoService,
    ): RedirectResponse {
        if (! $cajaSesion->estaAbierta()) {
            return redirect()
                ->route('caja.sesiones.index', $request->query())
                ->with('error', __('caja.flash.sesion_ya_cerrada'));
        }

        if ((string) $cajaSesion->opened_by_id !== (string) Auth::id()) {
            return redirect()
                ->route('caja.sesiones.index', $request->query())
                ->with('error', __('caja.flash.solo_apertura_puede_cerrar'));
        }

        $data = $request->validated();
        $arqueo = $arqueoService->build($cajaSesion, (string) $data['saldo_cierre_efectivo']);

        $cajaSesion->update([
            'estado' => CajaSesion::ESTADO_CERRADA,
            'saldo_cierre_efectivo' => $data['saldo_cierre_efectivo'],
            'arqueo_json' => $arqueo,
            'closed_at' => now(),
            'closed_by_id' => Auth::id(),
            'notas' => $this->mergeNotasCierre($cajaSesion->notas, $data['notas'] ?? null),
        ]);

        return redirect()
            ->route('caja.sesiones.index', $request->query())
            ->with('success', __('caja.flash.sesion_cerrada'));
    }

    public function arqueo(CajaSesion $cajaSesion, CajaSesionArqueoService $arqueoService): JsonResponse
    {
        $this->authorizeSesionAccess($cajaSesion);

        $contado = $cajaSesion->saldo_cierre_efectivo !== null
            ? (string) $cajaSesion->saldo_cierre_efectivo
            : null;

        if (is_array($cajaSesion->arqueo_json) && $cajaSesion->arqueo_json !== [] && ! $cajaSesion->estaAbierta()) {
            return response()->json(['arqueo' => $cajaSesion->arqueo_json]);
        }

        return response()->json([
            'arqueo' => $arqueoService->build($cajaSesion, $contado),
        ]);
    }

    public function arqueoPdf(
        CajaSesion $cajaSesion,
        CajaSesionArqueoPdfService $pdfService,
        Request $request,
    ): Response|StreamedResponse {
        $this->authorizeSesionAccess($cajaSesion);

        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 403);

        $cajaSesion->loadMissing(['abiertaPor:id,name', 'cerradaPor:id,name']);

        $formato = CajaSesionArqueoPdfService::normalizeFormato(
            (string) $request->string('formato', 'a4'),
            (string) (ClinicSetting::query()->value('ticket_ancho_mm') ?? TicketAnchoMm::DEFAULT),
        );

        return $pdfService->stream($cajaSesion, (string) $tenantId, null, $formato);
    }

    private function authorizeSesionAccess(CajaSesion $cajaSesion): void
    {
        // La ruta exige caja-sesiones.view. Si la sesión está abierta y no es del
        // usuario, igual puede ver el preview si tiene el permiso (supervisor).
        unset($cajaSesion);
    }

    private function mergeNotasCierre(?string $existentes, ?string $nuevas): ?string
    {
        $nuevas = $nuevas !== null ? trim($nuevas) : '';
        if ($nuevas === '') {
            return $existentes;
        }

        $existentes = $existentes !== null ? trim($existentes) : '';
        if ($existentes === '') {
            return $nuevas;
        }

        return $existentes."\n\n--- Cierre ---\n".$nuevas;
    }
}
