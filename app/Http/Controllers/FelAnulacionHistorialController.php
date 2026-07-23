<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FelDocument;
use App\Models\FelSerie;
use App\Models\Sede;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Historial de anulaciones FEL ya ejecutadas desde caja.
 *
 * - Resúmenes: boletas anuladas vía resumen diario Lucode.
 * - Notas de baja: facturas anuladas vía comunicación de baja Lucode.
 *
 * No crea envíos agregados nuevos: lista lo ya anulado en `fel_documents`.
 */
class FelAnulacionHistorialController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'anulado_at',
        'emitido_at',
        'numero_completo',
        'total',
    ];

    public function resumenes(Request $request): Response
    {
        return $this->index(
            $request,
            FelSerie::TIPO_BOLETA,
            'facturacion/resumenes/index',
            'Resúmenes diarios (boletas anuladas)',
        );
    }

    public function notasBaja(Request $request): Response
    {
        return $this->index(
            $request,
            FelSerie::TIPO_FACTURA,
            'facturacion/notas-baja/index',
            'Notas de baja (facturas anuladas)',
        );
    }

    private function index(
        Request $request,
        int $tipoComprobante,
        string $inertiaPage,
        string $pageTitle,
    ): Response {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 403);

        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 15);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true)
            ? $perPageRequested
            : 15;

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);
        $directionSql = $directionValid ? $direction : 'desc';

        $tz = (string) config('app.timezone');
        $now = now($tz);
        $hoy = $now->toDateString();
        $defaultDesde = $hoy;
        $defaultHasta = $hoy;

        $fechaDesde = $this->parseDateParam($request->query('fecha_desde')) ?? $defaultDesde;
        $fechaHasta = $this->parseDateParam($request->query('fecha_hasta')) ?? $defaultHasta;

        if ($fechaDesde > $fechaHasta) {
            [$fechaDesde, $fechaHasta] = [$fechaHasta, $fechaDesde];
        }

        $fueraDelRangoDefault = ($fechaDesde !== $defaultDesde) || ($fechaHasta !== $defaultHasta);

        $query = FelDocument::query()
            ->with([
                'venta:id,numero,sede_id,estado,motivo_anulacion,anulado_at,anulado_por_id',
                'venta.anuladoPor:id,name',
            ])
            ->where('estado', FelDocument::ESTADO_ANULADO)
            ->where('tipo_comprobante', $tipoComprobante);

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($like): void {
                $q->where('numero_completo', 'ILIKE', $like)
                    ->orWhere('receptor_nombre', 'ILIKE', $like)
                    ->orWhere('receptor_num_doc', 'ILIKE', $like)
                    ->orWhereHas('venta', fn ($vq) => $vq->where('numero', 'ILIKE', $like));
            });
        }

        $query->whereRaw('DATE(COALESCE(anulado_at, emitido_at, created_at)) >= ?', [$fechaDesde])
            ->whereRaw('DATE(COALESCE(anulado_at, emitido_at, created_at)) <= ?', [$fechaHasta]);

        if ($sortValid) {
            $query->orderBy($sort, $directionSql);
            if ($sort !== 'anulado_at') {
                $query->orderByDesc('anulado_at');
            }
        } else {
            $query->orderByDesc('anulado_at')->orderByDesc('emitido_at');
        }

        $documentos = $query->paginate($perPage)->withQueryString();

        $sedeIds = $documentos->getCollection()
            ->pluck('venta.sede_id')
            ->filter()
            ->unique()
            ->all();

        $sedeNombres = Sede::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $sedeIds)
            ->pluck('nombre', 'id');

        $documentos->getCollection()->transform(function (FelDocument $doc) use ($sedeNombres): array {
            $venta = $doc->venta;

            return [
                'id' => $doc->id,
                'numero_completo' => $doc->numero_completo,
                'tipo_comprobante' => $doc->tipo_comprobante,
                'tipo_label' => FelSerie::labelTipo($doc->tipo_comprobante),
                'estado' => $doc->estado,
                'receptor_nombre' => $doc->receptor_nombre,
                'receptor_num_doc' => $doc->receptor_num_doc,
                'total' => (string) $doc->total,
                'moneda' => $doc->moneda,
                'emitido_at' => $doc->emitido_at?->toIso8601String(),
                'anulado_at' => $doc->anulado_at?->toIso8601String()
                    ?? $venta?->anulado_at?->toIso8601String(),
                'motivo_anulacion' => $venta?->motivo_anulacion,
                'anulado_por' => $venta?->anuladoPor?->name,
                'venta_id' => $doc->venta_id,
                'venta_numero' => $venta?->numero,
                'venta_estado' => $venta?->estado,
                'sede' => $venta !== null ? ($sedeNombres[$venta->sede_id] ?? '—') : '—',
            ];
        });

        $baseStats = FelDocument::query()
            ->where('estado', FelDocument::ESTADO_ANULADO)
            ->where('tipo_comprobante', $tipoComprobante);

        return Inertia::render($inertiaPage, [
            'page_title' => $pageTitle,
            'tipo_comprobante' => $tipoComprobante,
            'documentos' => $documentos,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
            ],
            'filtro_ui' => [
                'default_desde' => $defaultDesde,
                'default_hasta' => $defaultHasta,
                'fuera_del_rango_default' => $fueraDelRangoDefault,
            ],
            'stats' => [
                'total_anulados' => (clone $baseStats)->count(),
                'coincidencias' => $documentos->total(),
            ],
        ]);
    }

    private function parseDateParam(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }
}
