<?php

namespace App\Http\Controllers;

use App\Models\ClinicSetting;
use App\Models\FelDocument;
use App\Models\FelSerie;
use App\Models\Sede;
use App\Models\Tenant;
use App\Services\Fel\FelDocumentApisunatFileService;
use App\Services\Fel\FelDocumentWhatsAppSender;
use App\Support\Fel\FelDocumentApisunatModeResolver;
use App\Support\Fel\FelDocumentPdfUrls;
use App\Support\WhatsApp\WhatsAppChatId;
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

        $metodosPermitidos = ['efectivo', 'yape', 'plin', 'tarjeta', 'transferencia', 'otro'];
        $metodoPago = (string) $request->string('metodo_pago', 'todos');
        if (! in_array($metodoPago, ['todos', ...$metodosPermitidos], true)) {
            $metodoPago = 'todos';
        }

        $tz = config('app.timezone');
        $now = now($tz);
        $hoy = $now->toDateString();
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

        $documentoFiltroUi = [
            'default_desde' => $defaultDesde,
            'default_hasta' => $defaultHasta,
            'fuera_del_mes_actual' => $fueraDelMesActual,
        ];

        $query = FelDocument::query()
            ->with([
                'venta:id,numero,sede_id,estado,propietario_id,metodo_pago',
                'venta.propietario:id,nombres,apellidos,razon_social,telefono',
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

        if ($metodoPago !== 'todos') {
            if ($metodoPago === 'otro') {
                $query->whereHas('venta', function ($vq) use ($metodosPermitidos): void {
                    $vq->where(function ($q) use ($metodosPermitidos): void {
                        $q->whereNull('metodo_pago')
                            ->orWhere('metodo_pago', '')
                            ->orWhereNotIn('metodo_pago', array_values(array_diff($metodosPermitidos, ['otro'])));
                    });
                });
            } else {
                $query->whereHas('venta', fn ($vq) => $vq->where('metodo_pago', $metodoPago));
            }
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
            $pdfA4 = FelDocumentPdfUrls::pdfA4FromTicket($pdfTicket);
            $propietario = $venta?->propietario;

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
                'apisunat_mode' => FelDocumentApisunatModeResolver::resolveAndPersist($doc),
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
                'metodo_pago' => $metodoPago,
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
