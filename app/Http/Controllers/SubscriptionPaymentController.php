<?php

namespace App\Http\Controllers;

use App\Exports\SubscriptionPaymentsXlsxExport;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Support\Subscriptions\CobrosListPresenter;
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
        'precio_pactado',
        'proximo_cobro_at',
    ];

    private const ESTADO_OPTIONS = ['todos', 'procesado', 'pendiente', 'fallido', 'reembolsado'];

    public function index(Request $request): Response
    {
        $vista = $request->routeIs('plataforma.pagos.index') ? 'pagos' : 'cobros';

        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true)
            ? $perPageRequested
            : 10;

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $estadoDefault = $vista === 'pagos' ? 'procesado' : 'todos';
        $estado = (string) $request->string('estado', $estadoDefault);
        if (! in_array($estado, self::ESTADO_OPTIONS, true)) {
            $estado = $estadoDefault;
        }

        $subscriptionId = trim((string) $request->string('subscription_id', ''));
        $tenantId = trim((string) $request->string('tenant_id', ''));
        $planId = trim((string) $request->string('plan_id', ''));

        $query = $this->buildSubscriptionQuery(
            $search,
            $estado,
            $subscriptionId,
            $tenantId,
            $planId,
        );

        if ($sortValid) {
            $this->applySubscriptionSort($query, $sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $subscriptions = $query
            ->with([
                'tenant:id,slug,razon_social,nombre_comercial,email_admin',
                'plan:id,codigo,nombre,badge,color_hex',
            ])
            ->paginate($perPage)
            ->withQueryString();

        $subscriptions->setCollection(
            $subscriptions->getCollection()->map(
                fn (Subscription $subscription) => CobrosListPresenter::fromSubscription($subscription),
            ),
        );

        $plansCatalog = Plan::query()
            ->excludingFree()
            ->orderBy('orden')
            ->get(['id', 'codigo', 'nombre', 'badge', 'color_hex']);

        $tenantsCatalog = Tenant::query()
            ->whereHas('subscriptions', function (Builder $subscriptionQuery): void {
                $subscriptionQuery->billable();
            })
            ->orderBy('razon_social')
            ->get(['id', 'slug', 'razon_social']);

        $billableSubscriptions = Subscription::query()->billable();

        $statsByEstado = SubscriptionPayment::query()
            ->forBillablePlans()
            ->selectRaw('estado, COUNT(*) as total, COALESCE(SUM(total), 0) as suma')
            ->groupBy('estado')
            ->get()
            ->keyBy('estado');

        $cobradoTotal = (float) ($statsByEstado['procesado']->suma ?? 0);
        $sinCobro = (clone $billableSubscriptions)
            ->whereDoesntHave('payments', fn (Builder $paymentQuery) => $paymentQuery->forBillablePlans())
            ->count();

        return Inertia::render('plataforma/cobros/index', [
            'vista' => $vista,
            'payments' => $subscriptions,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'estado' => $estado,
                'subscription_id' => $subscriptionId !== '' ? $subscriptionId : null,
                'tenant_id' => $tenantId !== '' ? $tenantId : null,
                'plan_id' => $planId !== '' ? $planId : null,
            ],
            'stats' => [
                'total' => (clone $billableSubscriptions)->count(),
                'procesado' => (int) ($statsByEstado['procesado']->total ?? 0),
                'pendiente' => (int) ($statsByEstado['pendiente']->total ?? 0) + $sinCobro,
                'fallido' => (int) ($statsByEstado['fallido']->total ?? 0),
                'reembolsado' => (int) ($statsByEstado['reembolsado']->total ?? 0),
                'cobrado_total' => $cobradoTotal,
                'coincidencias' => $subscriptions->total(),
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

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $query = $this->buildSubscriptionQuery(
            $search,
            $estado,
            $subscriptionId,
            $tenantId,
            $planId,
        );

        if ($sortValid) {
            $this->applySubscriptionSort($query, $sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $subscriptions = $query
            ->with([
                'tenant:id,slug,razon_social',
                'plan:id,codigo,nombre',
            ])
            ->get()
            ->map(fn (Subscription $subscription) => CobrosListPresenter::fromSubscription($subscription));

        $filename = 'cobros-'.now()->format('Ymd-His').'.xlsx';
        $exporter = new SubscriptionPaymentsXlsxExport();

        return response()->streamDownload(
            function () use ($exporter, $subscriptions) {
                $exporter->streamFromRows($subscriptions->all());
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
     * @return Builder<Subscription>
     */
    private function buildSubscriptionQuery(
        string $search,
        string $estado,
        string $subscriptionId,
        string $tenantId,
        string $planId = '',
    ): Builder {
        $query = Subscription::query()->billable();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('tenant', function ($qq) use ($search) {
                    $qq->where('razon_social', 'ILIKE', "%{$search}%")
                        ->orWhere('slug', 'ILIKE', "%{$search}%")
                        ->orWhere('email_admin', 'ILIKE', "%{$search}%")
                        ->orWhere('ruc', 'ILIKE', "%{$search}%");
                })->orWhereHas('plan', function ($qq) use ($search) {
                    $qq->where('codigo', 'ILIKE', "%{$search}%")
                        ->orWhere('nombre', 'ILIKE', "%{$search}%");
                })->orWhereHas('payments', function ($qq) use ($search) {
                    $qq->forBillablePlans()
                        ->where(function ($paymentQuery) use ($search) {
                            $paymentQuery->where('pasarela_transaction_id', 'ILIKE', "%{$search}%")
                                ->orWhere('fel_numero', 'ILIKE', "%{$search}%");
                        });
                });
            });
        }

        if ($estado === 'procesado') {
            $query->whereHas('payments', fn (Builder $paymentQuery) => $paymentQuery
                ->forBillablePlans()
                ->where('estado', 'procesado'));
        } elseif ($estado === 'pendiente') {
            $query->where(function (Builder $pendingQuery): void {
                $pendingQuery
                    ->whereDoesntHave('payments', fn (Builder $paymentQuery) => $paymentQuery->forBillablePlans())
                    ->orWhereHas('payments', fn (Builder $paymentQuery) => $paymentQuery
                        ->forBillablePlans()
                        ->where('estado', 'pendiente'));
            });
        } elseif ($estado !== 'todos') {
            $query->whereHas('payments', fn (Builder $paymentQuery) => $paymentQuery
                ->forBillablePlans()
                ->where('estado', $estado));
        }

        if ($subscriptionId !== '') {
            $query->whereKey($subscriptionId);
        }

        if ($tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }

        if ($planId !== '') {
            $query->where('plan_id', $planId);
        }

        return $query;
    }

    /**
     * @param  Builder<Subscription>  $query
     */
    private function applySubscriptionSort(Builder $query, string $sort, string $direction): void
    {
        $dir = $direction === 'asc' ? 'asc' : 'desc';

        match ($sort) {
            'precio_pactado', 'total' => $query->orderBy('precio_pactado', $dir),
            'proximo_cobro_at', 'pagado_at' => $query->orderBy('proximo_cobro_at', $dir),
            'created_at' => $query->orderBy('created_at', $dir),
            default => $query->orderBy('created_at', 'desc'),
        };
    }
}
