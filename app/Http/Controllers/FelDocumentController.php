<?php

namespace App\Http\Controllers;

use App\Models\FelDocument;
use App\Models\FelSerie;
use App\Models\Sede;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FelDocumentController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'emitido_at',
        'numero_completo',
        'total',
        'estado',
    ];

    public function index(Request $request): Response
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 403);

        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 15);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true) ? $perPageRequested : 15;

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);
        $directionSql = $directionValid ? $direction : 'desc';

        $estado = (string) $request->string('estado', 'todos');
        if (! in_array($estado, ['todos', FelDocument::ESTADO_EMITIDO, FelDocument::ESTADO_ANULADO, FelDocument::ESTADO_RECHAZADO, FelDocument::ESTADO_PENDIENTE], true)) {
            $estado = 'todos';
        }

        $tz = config('app.timezone');
        $now = now($tz);
        $defaultDesde = $now->copy()->startOfMonth()->toDateString();
        $defaultHasta = $now->copy()->endOfMonth()->toDateString();

        $fechaDesde = $this->parseDateParam($request->query('fecha_desde'));
        $fechaHasta = $this->parseDateParam($request->query('fecha_hasta'));

        if ($fechaDesde === null || $fechaHasta === null) {
            $fechaDesde = $defaultDesde;
            $fechaHasta = $defaultHasta;
            $fueraDelMesActual = false;
        } else {
            if ($fechaDesde > $fechaHasta) {
                [$fechaDesde, $fechaHasta] = [$fechaHasta, $fechaDesde];
            }
            $fueraDelMesActual = ($fechaDesde !== $defaultDesde) || ($fechaHasta !== $defaultHasta);
        }

        $documentoFiltroUi = [
            'default_desde' => $defaultDesde,
            'default_hasta' => $defaultHasta,
            'fuera_del_mes_actual' => $fueraDelMesActual,
        ];

        $query = FelDocument::query()
            ->with([
                'venta:id,numero,sede_id,estado',
            ]);

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($like): void {
                $q->where('numero_completo', 'ILIKE', $like)
                    ->orWhere('receptor_nombre', 'ILIKE', $like)
                    ->orWhere('receptor_num_doc', 'ILIKE', $like)
                    ->orWhereHas('venta', fn ($vq) => $vq->where('numero', 'ILIKE', $like));
            });
        }

        if ($estado !== 'todos') {
            $query->where('estado', $estado);
        }

        $query->whereRaw('DATE(COALESCE(emitido_at, created_at)) >= ?', [$fechaDesde])
            ->whereRaw('DATE(COALESCE(emitido_at, created_at)) <= ?', [$fechaHasta]);

        if ($sortValid) {
            $query->orderBy($sort, $directionSql);
            if ($sort !== 'emitido_at') {
                $query->orderByDesc('emitido_at');
            }
        } else {
            $query->orderByDesc('emitido_at')->orderByDesc('created_at');
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
            $pdfTicket = $doc->url_pdf;
            $pdfA4 = $this->buildA4FromTicket($pdfTicket);

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
                'venta_id' => $doc->venta_id,
                'venta_numero' => $venta?->numero,
                'venta_estado' => $venta?->estado,
                'sede' => $venta !== null ? ($sedeNombres[$venta->sede_id] ?? '—') : '—',
                'url_pdf_ticket' => $pdfTicket,
                'url_pdf_a4' => $pdfA4,
                'tiene_xml' => filled($doc->url_xml),
                'tiene_cdr' => filled($doc->url_cdr),
                'tiene_json' => true,
                'download_xml_url' => route('facturacion.documentos.download-xml', $doc),
                'download_cdr_url' => route('facturacion.documentos.download-cdr', $doc),
                'json_url' => route('facturacion.documentos.json', $doc),
                'apisunat_mode' => $doc->apisunat_mode,
            ];
        });

        return Inertia::render('facturacion/documentos/index', [
            'documentos' => $documentos,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'estado' => $estado,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
            ],
            'documento_filtro_ui' => $documentoFiltroUi,
            'stats' => [
                'total' => FelDocument::query()->count(),
                'emitidos' => FelDocument::query()->where('estado', FelDocument::ESTADO_EMITIDO)->count(),
                'coincidencias' => $documentos->total(),
            ],
        ]);
    }

    public function downloadXml(FelDocument $felDocument): StreamedResponse|RedirectResponse
    {
        $this->authorizeDocument($felDocument);

        return $this->proxyDownload(
            $felDocument->url_xml,
            $felDocument->numero_completo.'.xml',
            'application/xml',
        );
    }

    public function downloadCdr(FelDocument $felDocument): StreamedResponse|RedirectResponse
    {
        $this->authorizeDocument($felDocument);

        return $this->proxyDownload(
            $felDocument->url_cdr,
            'CDR-'.$felDocument->numero_completo.'.xml',
            'application/xml',
        );
    }

    public function json(FelDocument $felDocument): JsonResponse
    {
        $this->authorizeDocument($felDocument);

        $payload = $felDocument->apisunat_payload;

        if (! is_array($payload)) {
            $payload = [
                'numero_completo' => $felDocument->numero_completo,
                'tipo_comprobante' => $felDocument->tipo_comprobante,
                'tipo_label' => FelSerie::labelTipo($felDocument->tipo_comprobante),
                'estado' => $felDocument->estado,
                'receptor' => [
                    'tipo_doc' => $felDocument->receptor_tipo_doc,
                    'num_doc' => $felDocument->receptor_num_doc,
                    'nombre' => $felDocument->receptor_nombre,
                ],
                'totales' => [
                    'subtotal' => (string) $felDocument->subtotal,
                    'igv_monto' => (string) $felDocument->igv_monto,
                    'total' => (string) $felDocument->total,
                    'moneda' => $felDocument->moneda,
                ],
                'enlaces' => [
                    'pdf' => $felDocument->url_pdf,
                    'xml' => $felDocument->url_xml,
                    'cdr' => $felDocument->url_cdr,
                    'consulta' => $felDocument->enlace_consulta,
                ],
                'emitido_at' => $felDocument->emitido_at?->toIso8601String(),
                'venta_id' => $felDocument->venta_id,
                'nota' => 'Respuesta completa de APISUNAT no disponible para comprobantes emitidos antes de esta versión.',
            ];
        }

        return response()->json($payload, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function authorizeDocument(FelDocument $felDocument): void
    {
        abort_unless(request()->user()?->can('documentos.view'), 403);
    }

    private function proxyDownload(?string $url, string $filename, string $mime): StreamedResponse|RedirectResponse
    {
        if ($url === null || $url === '') {
            return back()->with('error', 'El archivo no está disponible.');
        }

        try {
            $response = Http::timeout(30)->get($url);
            $content = $response->body();
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo descargar el archivo: '.$e->getMessage());
        }

        return response()->streamDownload(
            fn () => print($content),
            $filename,
            ['Content-Type' => $mime],
        );
    }

    private function buildA4FromTicket(?string $ticketUrl): ?string
    {
        if ($ticketUrl === null || $ticketUrl === '') {
            return null;
        }

        if (str_contains($ticketUrl, '/pdf/a4/')) {
            return $ticketUrl;
        }

        if (str_contains($ticketUrl, '/pdf/ticket/')) {
            return str_replace('/pdf/ticket/', '/pdf/a4/', $ticketUrl);
        }

        return null;
    }

    private function parseDateParam(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
