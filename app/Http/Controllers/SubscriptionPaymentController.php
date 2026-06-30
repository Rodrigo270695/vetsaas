<?php

namespace App\Http\Controllers;

use App\Exports\SubscriptionPaymentsXlsxExport;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Support\Subscriptions\SubscriptionExpiry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Panel de cobros del SaaS (Plataforma → Cobros).
 *
 * Notas de diseño:
 *   - Es una vista **read-only** sobre los datos del webhook. Los cobros
 *     los procesa Orvae (con Niubiz/Culqi/MP) y escribe la fila acá.
 *     VetSaaS NO habla con la pasarela.
 *   - Las únicas acciones que el superadmin puede hacer son operativas
 *     de soporte:
 *       · `markRefunded`   → reembolso manual (cuando devolviste por
 *                            transferencia, por ejemplo).
 *       · `addNote`        → agregar/editar nota interna de soporte.
 *       · `resendInvoice`  → marca el momento del reenvío de factura;
 *                            el envío real lo dispara un job/evento que
 *                            vive fuera de este controller (se hará
 *                            cuando se conecte el módulo FEL).
 *   - No hay `store`, `update`, `destroy`, `bulkDestroy`: la fila base
 *     es inmutable.
 */
class SubscriptionPaymentController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'estado',
        'pasarela',
        'total',
        'pagado_at',
        'created_at',
    ];

    private const ESTADO_OPTIONS = ['todos', 'procesado', 'pendiente', 'fallido', 'reembolsado'];

    public function index(Request $request): Response
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

        $estado = (string) $request->string('estado', 'todos');
        if (! in_array($estado, self::ESTADO_OPTIONS, true)) {
            $estado = 'todos';
        }

        $subscriptionId = trim((string) $request->string('subscription_id', ''));
        $tenantId = trim((string) $request->string('tenant_id', ''));
        $planId = trim((string) $request->string('plan_id', ''));
        $vencimiento = (string) $request->string('vencimiento', 'todos');
        if (! in_array($vencimiento, SubscriptionExpiry::FILTER_OPTIONS, true)) {
            $vencimiento = 'todos';
        }

        $query = $this->buildBaseQuery($search, $estado, $subscriptionId, $tenantId, $planId, $vencimiento);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $payments = $query
            ->with([
                'tenant:id,slug,razon_social,nombre_comercial,email_admin',
                'tenant.subscriptions' => fn ($q) => $q
                    ->whereIn('estado', ['trial', 'active', 'grace', 'suspended'])
                    ->latest()
                    ->limit(1),
                'tenant.subscriptions.plan:id,codigo,nombre,badge,color_hex',
                'plan:id,codigo,nombre,badge,color_hex',
                'subscription:id,tenant_id,plan_id,estado,trial_ends_at,current_period_end,grace_ends_at,proximo_cobro_at',
                'subscription.plan:id,codigo,nombre,badge,color_hex',
                'refundedBy:id,name,email',
            ])
            ->paginate($perPage)
            ->withQueryString();

        $plansCatalog = Plan::query()
            ->excludingFree()
            ->orderBy('orden')
            ->get(['id', 'codigo', 'nombre', 'badge', 'color_hex']);

        $tenantsCatalog = Tenant::query()
            ->whereHas('subscriptions', function (Builder $subscriptionQuery): void {
                $subscriptionQuery
                    ->whereIn('estado', ['trial', 'active', 'grace', 'suspended'])
                    ->whereHas('plan', fn (Builder $planQuery) => $planQuery->excludingFree());
            })
            ->orderBy('razon_social')
            ->get(['id', 'slug', 'razon_social']);

        $statsByEstado = SubscriptionPayment::query()
            ->forBillablePlans()
            ->forTenantsWithBillablePlan()
            ->selectRaw('estado, COUNT(*) as total, COALESCE(SUM(total), 0) as suma')
            ->groupBy('estado')
            ->get()
            ->keyBy('estado');

        // Cobrado total: suma de `total` solo de procesados (excluye
        // pendientes, fallidos y reembolsados).
        $cobradoTotal = (float) ($statsByEstado['procesado']->suma ?? 0);

        return Inertia::render('plataforma/cobros/index', [
            'payments' => $payments,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'estado' => $estado,
                'subscription_id' => $subscriptionId !== '' ? $subscriptionId : null,
                'tenant_id' => $tenantId !== '' ? $tenantId : null,
                'plan_id' => $planId !== '' ? $planId : null,
                'vencimiento' => $vencimiento,
            ],
            'stats' => [
                'total' => SubscriptionPayment::query()
                    ->forBillablePlans()
                    ->forTenantsWithBillablePlan()
                    ->count(),
                'procesado' => (int) ($statsByEstado['procesado']->total ?? 0),
                'pendiente' => (int) ($statsByEstado['pendiente']->total ?? 0),
                'fallido' => (int) ($statsByEstado['fallido']->total ?? 0),
                'reembolsado' => (int) ($statsByEstado['reembolsado']->total ?? 0),
                'cobrado_total' => $cobradoTotal,
                'coincidencias' => $payments->total(),
            ],
            'plans_catalog' => $plansCatalog,
            'tenants_catalog' => $tenantsCatalog,
        ]);
    }

    /**
     * Marca un pago como reembolsado manualmente. Esta acción se usa
     * cuando devolviste el dinero al cliente FUERA de la pasarela
     * (por ejemplo, transferencia bancaria) y necesitas dejar rastro.
     *
     * Si el reembolso fue vía pasarela, idealmente debería llegar como
     * webhook desde Orvae con `estado = reembolsado` y este endpoint
     * no se necesita.
     */
    public function markRefunded(Request $request, SubscriptionPayment $cobro): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if ($cobro->estado === 'reembolsado') {
            return back()->with('info', 'Este cobro ya estaba marcado como reembolsado.');
        }

        if ($cobro->estado === 'fallido') {
            throw ValidationException::withMessages([
                'reason' => 'No tiene sentido reembolsar un cobro fallido: nunca llegó a procesarse.',
            ]);
        }

        $cobro->update([
            'estado' => 'reembolsado',
            'refunded_at' => now(),
            'refunded_by' => $request->user()?->id,
            'refund_reason' => $data['reason'],
        ]);

        return back()->with('success', 'Cobro marcado como reembolsado.');
    }

    /**
     * Crea/actualiza la nota interna del cobro. Útil para dejar contexto
     * de conversaciones con el cliente, reclamos, decisiones, etc.
     */
    public function addNote(Request $request, SubscriptionPayment $cobro): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $cobro->update([
            'internal_note' => filled($data['note']) ? trim((string) $data['note']) : null,
        ]);

        return back()->with(
            'success',
            filled($data['note'])
                ? 'Nota interna guardada.'
                : 'Nota interna eliminada.',
        );
    }

    /**
     * Reenvía la factura electrónica al cliente. Acá solo registramos
     * el momento; el envío real (email/PDF) lo dispara un job/evento
     * que se conectará cuando exista el módulo FEL.
     */
    public function resendInvoice(SubscriptionPayment $cobro): RedirectResponse
    {
        if (! $cobro->fel_emitido) {
            throw ValidationException::withMessages([
                'fel_emitido' => 'Este cobro aún no tiene factura electrónica emitida.',
            ]);
        }

        $cobro->update(['invoice_resent_at' => now()]);

        // TODO: cuando exista el módulo FEL, despachar un job aquí:
        //   ResendInvoiceEmail::dispatch($cobro);

        return back()->with('success', 'Factura reenviada al cliente.');
    }

    public function export(Request $request): StreamedResponse
    {
        $search = trim((string) $request->string('search', ''));

        $estado = (string) $request->string('estado', 'todos');
        if (! in_array($estado, self::ESTADO_OPTIONS, true)) {
            $estado = 'todos';
        }

        $subscriptionId = trim((string) $request->string('subscription_id', ''));
        $tenantId = trim((string) $request->string('tenant_id', ''));
        $planId = trim((string) $request->string('plan_id', ''));
        $vencimiento = (string) $request->string('vencimiento', 'todos');
        if (! in_array($vencimiento, SubscriptionExpiry::FILTER_OPTIONS, true)) {
            $vencimiento = 'todos';
        }

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $query = $this->buildBaseQuery($search, $estado, $subscriptionId, $tenantId, $planId, $vencimiento)
            ->with([
                'tenant:id,slug,razon_social',
                'tenant.subscriptions' => fn ($q) => $q
                    ->whereIn('estado', ['trial', 'active', 'grace', 'suspended'])
                    ->latest()
                    ->limit(1),
                'tenant.subscriptions.plan:id,codigo,nombre',
                'plan:id,codigo,nombre',
            ]);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $filename = 'cobros-'.now()->format('Ymd-His').'.xlsx';
        $exporter = new SubscriptionPaymentsXlsxExport();

        return response()->streamDownload(
            function () use ($exporter, $query) {
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

    /**
     * @return Builder<SubscriptionPayment>
     */
    private function buildBaseQuery(
        string $search,
        string $estado,
        string $subscriptionId,
        string $tenantId,
        string $planId = '',
        string $vencimiento = 'todos',
    ): Builder {
        $query = SubscriptionPayment::query()
            ->forBillablePlans()
            ->forTenantsWithBillablePlan();

        if ($search !== '') {
            // Buscamos por:
            //  - el ID de transacción de la pasarela (caso típico: el cliente
            //    nos pasa "el código del pago" de Niubiz)
            //  - el número FEL (cuando el cliente busca su factura)
            //  - razón social / slug / email del tenant (búsqueda transitiva)
            $query->where(function ($q) use ($search) {
                $q->where('pasarela_transaction_id', 'ILIKE', "%{$search}%")
                    ->orWhere('fel_numero', 'ILIKE', "%{$search}%")
                    ->orWhereHas('tenant', function ($qq) use ($search) {
                        $qq->where('razon_social', 'ILIKE', "%{$search}%")
                            ->orWhere('slug', 'ILIKE', "%{$search}%")
                            ->orWhere('email_admin', 'ILIKE', "%{$search}%")
                            ->orWhere('ruc', 'ILIKE', "%{$search}%");
                    });
            });
        }

        if ($estado !== 'todos') {
            $query->where('estado', $estado);
        }

        if ($subscriptionId !== '') {
            $query->where('subscription_id', $subscriptionId);
        }

        if ($tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }

        if ($planId !== '') {
            SubscriptionExpiry::applyPaymentPlanFilter($query, $planId);
        }

        if ($vencimiento !== 'todos') {
            SubscriptionExpiry::applyPaymentFilter($query, $vencimiento);
        }

        return $query;
    }
}
