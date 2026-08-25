<?php

namespace App\Http\Controllers;

use App\Exports\FelDocumentsXlsxExport;
use App\Http\Controllers\Concerns\LogsAuditExports;
use App\Models\ClinicSetting;
use App\Models\FelDocument;
use App\Models\FelSerie;
use App\Models\Sede;
use App\Models\Tenant;
use App\Services\Fel\FelDocumentApisunatFileService;
use App\Services\Fel\FelDocumentWhatsAppSender;
use App\Services\Fel\FelSandboxToProduccionService;
use App\Support\Fel\ApisunatCredentialResolver;
use App\Support\Fel\FelDocumentApisunatModeResolver;
use App\Support\Fel\FelDocumentPdfUrls;
use App\Support\WhatsApp\WhatsAppChatId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class FelDocumentController extends Controller
{
    use LogsAuditExports;

    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'emitido_at',
        'numero_completo',
        'total',
        'estado',
    ];

    private const METODOS_PAGO = ['efectivo', 'yape', 'plin', 'tarjeta', 'transferencia', 'otro'];

    public function index(Request $request): Response
    {
        $tenantId = clinic_tenant_id();

        $ctx = $this->resolveDocumentosContext($request);
        $perPage = $ctx['per_page'];
        $sortValid = $ctx['sort_valid'];
        $directionValid = $ctx['direction_valid'];
        $documentoFiltroUi = $ctx['documento_filtro_ui'];

        $query = $this->buildDocumentosQuery($ctx)
            ->with([
                'venta:id,numero,sede_id,estado,propietario_id,metodo_pago',
                'venta.propietario:id,nombres,apellidos,razon_social,telefono',
            ]);

        $montoFiltrado = round((float) ((clone $query)->reorder()->sum('total') ?? 0), 2);

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

        $sandboxProd = app(FelSandboxToProduccionService::class);
        $siguientesSandbox = $sandboxProd->siguientesPendientesPorSerie();
        $clinic = ClinicSetting::current();
        $clinicaEnProduccion = ($clinic->apisunat_mode ?? 'sandbox') === 'produccion'
            && ApisunatCredentialResolver::estaConfigurado($clinic);

        $documentos->getCollection()->transform(function (FelDocument $doc) use ($sedeNombres, $siguientesSandbox, $clinicaEnProduccion): array {
            $venta = $doc->venta;
            $pdfTicket = $doc->url_pdf;
            $pdfA4 = FelDocumentPdfUrls::pdfA4FromTicket($pdfTicket);
            $propietario = $venta?->propietario;
            $mode = FelDocumentApisunatModeResolver::resolveAndPersist($doc);
            $esSandbox = $mode === 'sandbox' && $doc->estado === FelDocument::ESTADO_EMITIDO;
            $siguiente = $siguientesSandbox[(string) $doc->serie] ?? null;
            $puedePasar = $clinicaEnProduccion
                && $esSandbox
                && $siguiente !== null
                && (int) $doc->correlativo === (int) $siguiente;

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
                'metodo_pago' => $venta?->metodo_pago,
                'sede' => $venta !== null ? ($sedeNombres[$venta->sede_id] ?? '—') : '—',
                'cliente_telefono' => $propietario?->telefono,
                'url_pdf_ticket' => $pdfTicket,
                'url_pdf_a4' => $pdfA4,
                'tiene_xml' => filled($doc->url_xml),
                'tiene_cdr' => filled($doc->url_cdr),
                'tiene_json' => true,
                'download_xml_url' => route('facturacion.documentos.download-xml', $doc),
                'download_cdr_url' => route('facturacion.documentos.download-cdr', $doc),
                'json_url' => route('facturacion.documentos.json', $doc),
                'apisunat_mode' => $mode,
                'puede_pasar_a_produccion' => $puedePasar,
                'pasar_a_produccion_url' => route('facturacion.documentos.pasar-a-produccion', $doc),
                'siguiente_sandbox_numero' => $siguiente !== null
                    ? ((string) $doc->serie).'-'.str_pad((string) $siguiente, 8, '0', STR_PAD_LEFT)
                    : null,
            ];
        });

        return Inertia::render('facturacion/documentos/index', [
            'documentos' => $documentos,
            'filters' => [
                'search' => $ctx['search'],
                'per_page' => $perPage,
                'sort' => $sortValid ? $ctx['sort'] : null,
                'direction' => $sortValid && $directionValid ? $ctx['direction'] : null,
                'estado' => $ctx['estado'],
                'metodo_pago' => $ctx['metodo_pago'],
                'fecha_desde' => $ctx['fecha_desde'],
                'fecha_hasta' => $ctx['fecha_hasta'],
            ],
            'documento_filtro_ui' => $documentoFiltroUi,
            'stats' => [
                'total' => FelDocument::query()->count(),
                'emitidos' => FelDocument::query()->where('estado', FelDocument::ESTADO_EMITIDO)->count(),
                'coincidencias' => $documentos->total(),
                'monto_total' => number_format($montoFiltrado, 2, '.', ''),
                'moneda' => 'PEN',
            ],
        ]);
    }

    /**
     * Exporta el listado filtrado de comprobantes a XLSX para cruces contables.
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('documentos.view'), 403);

        clinic_tenant_id();

        $ctx = $this->resolveDocumentosContext($request);

        $query = $this->buildDocumentosQuery($ctx)
            ->with([
                'venta:id,numero,sede_id,estado,metodo_pago',
                'venta.sede:id,nombre,codigo',
            ]);

        $filename = 'comprobantes-'.now()->format('Ymd-His').'.xlsx';
        $exporter = new FelDocumentsXlsxExport;

        $this->auditExport('documentos', $filename);

        return response()->streamDownload(
            function () use ($exporter, $query): void {
                $exporter->streamTo($query);
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ],
        );
    }

    public function downloadXml(
        FelDocument $felDocument,
        FelDocumentApisunatFileService $lucodeFiles,
    ): StreamedResponse|RedirectResponse {
        $this->authorizeDocument($felDocument);

        return $this->proxyLucodeDownload(
            $lucodeFiles,
            $felDocument,
            'xml',
            $felDocument->numero_completo.'.xml',
            'application/xml',
        );
    }

    public function downloadCdr(
        FelDocument $felDocument,
        FelDocumentApisunatFileService $lucodeFiles,
    ): StreamedResponse|RedirectResponse {
        $this->authorizeDocument($felDocument);

        return $this->proxyLucodeDownload(
            $lucodeFiles,
            $felDocument,
            'cdr',
            'R-'.$felDocument->numero_completo.'.xml',
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

    /**
     * Reenvía un CPE de sandbox a APISUNAT producción (misma serie/correlativo, en orden).
     */
    public function pasarAProduccion(
        Request $request,
        FelDocument $felDocument,
        FelSandboxToProduccionService $converter,
    ): RedirectResponse {
        abort_unless($request->user()?->can('documentos.create'), 403);

        try {
            $converted = $converter->convertir($felDocument);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('fel.pasar_a_produccion_failed', [
                'fel_document_id' => $felDocument->id,
                'message' => $e->getMessage(),
            ]);

            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            'success',
            __('caja.ventas.fel.sandbox_prod.ok', [
                'numero' => $converted->numero_completo,
            ]),
        );
    }

    /**
     * Envía comprobante electrónico por WhatsApp con adjuntos seleccionados.
     */
    public function enviarWhatsApp(
        Request $request,
        FelDocument $felDocument,
        FelDocumentWhatsAppSender $sender,
    ): RedirectResponse {
        abort_unless($request->user()?->can('documentos.send'), 403);

        if ($felDocument->estado !== FelDocument::ESTADO_EMITIDO) {
            return back()->with('warning', __('facturacion.documentos.flash.whatsapp_no_emitido'));
        }

        if (FelDocumentApisunatModeResolver::resolveAndPersist($felDocument) === 'sandbox') {
            return back()->with('warning', __('facturacion.documentos.flash.whatsapp_sandbox'));
        }

        $data = $request->validate([
            'telefono' => ['nullable', 'string', 'max:20'],
            'pdf_ticket' => ['sometimes', 'boolean'],
            'pdf_a4' => ['sometimes', 'boolean'],
            'xml' => ['sometimes', 'boolean'],
            'cdr' => ['sometimes', 'boolean'],
        ]);

        $pdfTicket = $request->boolean('pdf_ticket');
        $pdfA4 = $request->boolean('pdf_a4');
        $xml = $request->boolean('xml');
        $cdr = $request->boolean('cdr');

        if (! $pdfTicket && ! $pdfA4 && ! $xml && ! $cdr) {
            return back()->with('warning', __('facturacion.documentos.flash.whatsapp_sin_adjuntos'));
        }

        $felDocument->loadMissing([
            'venta.propietario:id,nombres,apellidos,razon_social,telefono',
        ]);

        $propietario = $felDocument->venta?->propietario;
        $phone = trim((string) ($data['telefono'] ?? '')) !== ''
            ? (string) $data['telefono']
            : $propietario?->telefono;

        $chatId = WhatsAppChatId::fromPhone($phone);
        if ($chatId === null) {
            return back()->with('warning', __('facturacion.documentos.flash.whatsapp_no_phone'));
        }

        $tenantId = tenant_id();
        $tenant = $tenantId !== null ? Tenant::query()->find($tenantId) : null;
        if ($tenant === null) {
            return back()->with('warning', __('facturacion.documentos.flash.whatsapp_fallo'));
        }

        $recipientName = trim($felDocument->receptor_nombre) !== ''
            ? trim($felDocument->receptor_nombre)
            : ($propietario !== null && trim($propietario->displayName()) !== ''
                ? $propietario->displayName()
                : 'cliente');

        try {
            $sender->send(
                $felDocument,
                $tenant,
                $chatId,
                $recipientName,
                ClinicSetting::current(),
                $pdfTicket,
                $pdfA4,
                $xml,
                $cdr,
            );

            return back()->with('success', __('facturacion.documentos.flash.whatsapp_enviado'));
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar comprobante por WhatsApp', [
                'fel_document_id' => $felDocument->id,
                'error' => $e->getMessage(),
            ]);

            $msg = __('facturacion.documentos.flash.whatsapp_fallo');
            $detail = trim($e->getMessage());
            if ($detail !== '') {
                $msg .= ' '.$detail;
            }

            return back()->with('warning', $msg);
        }
    }

    /**
     * Filtros compartidos entre el listado Inertia y la exportación XLSX.
     *
     * @return array{
     *     search: string,
     *     per_page: int,
     *     sort: string,
     *     sort_valid: bool,
     *     direction: string,
     *     direction_valid: bool,
     *     direction_sql: string,
     *     estado: string,
     *     metodo_pago: string,
     *     fecha_desde: string,
     *     fecha_hasta: string,
     *     documento_filtro_ui: array{default_desde: string, default_hasta: string, fuera_del_mes_actual: bool},
     * }
     */
    private function resolveDocumentosContext(Request $request): array
    {
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

        $metodoPago = (string) $request->string('metodo_pago', 'todos');
        if (! in_array($metodoPago, ['todos', ...self::METODOS_PAGO], true)) {
            $metodoPago = 'todos';
        }

        $hoy = now(config('app.timezone'))->toDateString();
        $defaultDesde = $hoy;
        $defaultHasta = $hoy;

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

        return [
            'search' => $search,
            'per_page' => $perPage,
            'sort' => $sort,
            'sort_valid' => $sortValid,
            'direction' => $direction,
            'direction_valid' => $directionValid,
            'direction_sql' => $directionSql,
            'estado' => $estado,
            'metodo_pago' => $metodoPago,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'documento_filtro_ui' => [
                'default_desde' => $defaultDesde,
                'default_hasta' => $defaultHasta,
                'fuera_del_mes_actual' => $fueraDelMesActual,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return Builder<FelDocument>
     */
    private function buildDocumentosQuery(array $ctx): Builder
    {
        $query = FelDocument::query();

        $search = (string) $ctx['search'];
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($like): void {
                $q->where('numero_completo', 'ILIKE', $like)
                    ->orWhere('receptor_nombre', 'ILIKE', $like)
                    ->orWhere('receptor_num_doc', 'ILIKE', $like)
                    ->orWhereHas('venta', fn ($vq) => $vq->where('numero', 'ILIKE', $like));
            });
        }

        if ($ctx['estado'] !== 'todos') {
            $query->where('estado', $ctx['estado']);
        }

        $metodoPago = (string) $ctx['metodo_pago'];
        if ($metodoPago !== 'todos') {
            if ($metodoPago === 'otro') {
                $query->whereHas('venta', function ($vq): void {
                    $vq->where(function ($q): void {
                        $q->whereNull('metodo_pago')
                            ->orWhere('metodo_pago', '')
                            ->orWhereNotIn('metodo_pago', array_values(array_diff(self::METODOS_PAGO, ['otro'])));
                    });
                });
            } else {
                $query->whereHas('venta', fn ($vq) => $vq->where('metodo_pago', $metodoPago));
            }
        }

        $query->whereRaw('DATE(COALESCE(emitido_at, created_at)) >= ?', [$ctx['fecha_desde']])
            ->whereRaw('DATE(COALESCE(emitido_at, created_at)) <= ?', [$ctx['fecha_hasta']]);

        if ($ctx['sort_valid']) {
            $query->orderBy((string) $ctx['sort'], (string) $ctx['direction_sql']);
            if ($ctx['sort'] !== 'emitido_at') {
                $query->orderByDesc('emitido_at');
            }
        } else {
            $query->orderByDesc('emitido_at')->orderByDesc('created_at');
        }

        return $query;
    }

    private function authorizeDocument(FelDocument $felDocument): void
    {
        abort_unless(request()->user()?->can('documentos.view'), 403);
    }

    private function proxyLucodeDownload(
        FelDocumentApisunatFileService $lucodeFiles,
        FelDocument $felDocument,
        string $tipo,
        string $filename,
        string $mime,
    ): StreamedResponse|RedirectResponse {
        try {
            $content = $lucodeFiles->descargar($felDocument, ClinicSetting::current(), $tipo);
        } catch (Throwable $e) {
            return back()->with('error', 'No se pudo descargar el archivo: '.$e->getMessage());
        }

        return response()->streamDownload(
            fn () => print ($content),
            $filename,
            ['Content-Type' => $mime],
        );
    }

    private function buildA4FromTicket(?string $ticketUrl): ?string
    {
        return FelDocumentPdfUrls::pdfA4FromTicket($ticketUrl);
    }

    private function parseDateParam(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
