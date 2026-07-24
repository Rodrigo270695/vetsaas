<?php

namespace App\Http\Controllers;

use App\Exports\SubscriptionsXlsxExport;
use App\Http\Requests\SubscriptionRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Subscriptions\SubscriptionRenewalReminderScanner;
use App\Services\Subscriptions\SubscriptionRenewalWhatsAppSender;
use App\Support\Subscriptions\SubscriptionBotIaAddon;
use App\Support\Subscriptions\SubscriptionCiclo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Administración de suscripciones (Plataforma → Suscripciones).
 *
 * Notas de diseño:
 *   - El alta normal de una suscripción la hace el checkout de Orvae
 *     cuando un cliente paga. Este controller existe para soporte,
 *     migraciones y casos especiales (clientes VIP, transferencias).
 *   - Acciones de transición de estado tienen endpoints dedicados
 *     (`extendTrial`, `changePlan`, `cancel`) para forzar auditoría
 *     clara y separar permisos.
 *   - La constraint en BD `UNIQUE (tenant_id) WHERE estado <> 'cancelled'`
 *     garantiza que un tenant solo tenga una suscripción viva a la vez.
 */
class SubscriptionController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'estado',
        'ciclo',
        'precio_pactado',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'proximo_cobro_at',
        'created_at',
    ];

    private const ESTADO_OPTIONS = ['todos', 'trial', 'active', 'grace', 'suspended', 'cancelled'];

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

        $planId = trim((string) $request->string('plan_id', ''));

        $query = $this->buildBaseQuery($search, $estado, $planId);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
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

        // Catálogo de planes para el select del filtro y del modal.
        $plansCatalog = Plan::query()
            ->orderBy('orden')
            ->get(['id', 'codigo', 'nombre', 'trial_days', 'precio_mensual', 'precio_anual', 'badge', 'color_hex']);

        // Catálogo de tenants para el select de creación (solo no-cancelled).
        $tenantsCatalog = Tenant::query()
            ->whereNotIn('estado', ['cancelled'])
            ->orderBy('razon_social')
            ->get(['id', 'slug', 'razon_social']);

        $statsByEstado = Subscription::query()
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->all();

        // MRR estimado: suma de precio_pactado de suscripciones active+grace.
        $mrrQuery = Subscription::query()->whereIn('estado', ['active', 'grace']);

        if ($planId !== '') {
            $mrrQuery->where('plan_id', $planId);
        }

        $mrr = (float) $mrrQuery->sum('precio_pactado');

        $mrrByPlanRows = Subscription::query()
            ->whereIn('estado', ['active', 'grace'])
            ->selectRaw('plan_id, COALESCE(SUM(precio_pactado), 0) as mrr, COUNT(*) as cantidad')
            ->groupBy('plan_id')
            ->get()
            ->keyBy('plan_id');

        $mrrByPlan = $plansCatalog
            ->map(function (Plan $plan) use ($mrrByPlanRows) {
                $row = $mrrByPlanRows->get($plan->id);

                return [
                    'plan_id' => $plan->id,
                    'codigo' => $plan->codigo,
                    'nombre' => $plan->nombre,
                    'mrr' => (float) ($row->mrr ?? 0),
                    'cantidad' => (int) ($row->cantidad ?? 0),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('plataforma/suscripciones/index', [
            'subscriptions' => $subscriptions,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'estado' => $estado,
                'plan_id' => $planId !== '' ? $planId : null,
            ],
            'stats' => [
                'total' => Subscription::query()->count(),
                'trial' => (int) ($statsByEstado['trial'] ?? 0),
                'active' => (int) ($statsByEstado['active'] ?? 0),
                'grace' => (int) ($statsByEstado['grace'] ?? 0),
                'suspended' => (int) ($statsByEstado['suspended'] ?? 0),
                'cancelled' => (int) ($statsByEstado['cancelled'] ?? 0),
                'mrr' => $mrr,
                'mrr_by_plan' => $mrrByPlan,
                'coincidencias' => $subscriptions->total(),
            ],
            'plans_catalog' => $plansCatalog,
            'tenants_catalog' => $tenantsCatalog,
        ]);
    }

    public function renewalReminderPreview(
        Subscription $suscripcion,
        SubscriptionRenewalReminderScanner $scanner,
    ): JsonResponse {
        $suscripcion->load(['tenant', 'plan']);

        return response()->json($scanner->preview($suscripcion));
    }

    public function sendRenewalWhatsApp(
        Subscription $suscripcion,
        SubscriptionRenewalWhatsAppSender $sender,
    ): RedirectResponse {
        $suscripcion->load(['tenant', 'plan']);

        $result = $sender->sendManual($suscripcion);

        if (! $result['ok']) {
            return back()->with('error', $result['error']);
        }

        $tenantName = $suscripcion->tenant?->nombre_comercial
            ?: $suscripcion->tenant?->razon_social
            ?: 'la clínica';

        return back()->with(
            'success',
            "Link de renovación enviado por WhatsApp a {$tenantName}.",
        );
    }

    public function store(SubscriptionRequest $request): RedirectResponse
    {
        Subscription::create($request->validated());

        return back()->with('success', 'Suscripción creada correctamente.');
    }

    public function update(SubscriptionRequest $request, Subscription $suscripcion): RedirectResponse
    {
        $suscripcion->update($request->validated());

        return back()->with('success', 'Suscripción actualizada correctamente.');
    }

    /**
     * Extiende el periodo de prueba sumando N días a `trial_ends_at`.
     * Si la suscripción no estaba en trial, primero la pasa a 'trial'.
     */
    public function extendTrial(Request $request, Subscription $suscripcion): RedirectResponse
    {
        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        if ($suscripcion->estado === 'cancelled') {
            throw ValidationException::withMessages([
                'days' => 'No se puede extender el trial de una suscripción cancelada.',
            ]);
        }

        $base = $suscripcion->trial_ends_at && $suscripcion->trial_ends_at->isFuture()
            ? $suscripcion->trial_ends_at
            : now();

        $suscripcion->update([
            'estado' => 'trial',
            'trial_ends_at' => $base->copy()->addDays((int) $data['days']),
        ]);

        return back()->with(
            'success',
            sprintf('Trial extendido %d día%s.', $data['days'], $data['days'] === 1 ? '' : 's'),
        );
    }

    /**
     * Cambia el plan de una suscripción. Recalcula el `precio_pactado`
     * con el precio del nuevo plan según el ciclo actual (mensual/trimestral/semestral/anual).
     */
    public function changePlan(Request $request, Subscription $suscripcion): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'uuid', 'exists:plans,id'],
            'keep_price' => ['nullable', 'boolean'],
        ]);

        if ($suscripcion->estado === 'cancelled') {
            throw ValidationException::withMessages([
                'plan_id' => 'No se puede cambiar el plan de una suscripción cancelada.',
            ]);
        }

        /** @var Plan $newPlan */
        $newPlan = Plan::findOrFail($data['plan_id']);

        $payload = ['plan_id' => $newPlan->id];

        // Solo recalculamos el precio si NO se pidió mantener el actual.
        if (! ($data['keep_price'] ?? false)) {
            $payload['precio_pactado'] = SubscriptionCiclo::suggestedPriceFromPlan(
                (float) $newPlan->precio_mensual,
                $newPlan->precio_anual !== null ? (float) $newPlan->precio_anual : null,
                (string) $suscripcion->ciclo,
            );
        }

        $suscripcion->update($payload);

        return back()->with('success', "Plan cambiado a {$newPlan->nombre}.");
    }

    /**
     * Activa o desactiva el add-on Asistente IA WhatsApp (S/15/mes por defecto).
     */
    public function toggleBotIa(Request $request, Subscription $suscripcion): RedirectResponse
    {
        $data = $request->validate([
            'activo' => ['required', 'boolean'],
            'precio_mensual' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
        ]);

        if ($suscripcion->estado === 'cancelled') {
            throw ValidationException::withMessages([
                'activo' => 'No se puede modificar el add-on en una suscripción cancelada.',
            ]);
        }

        $activo = (bool) $data['activo'];
        $precio = $data['precio_mensual'] ?? null;

        $payload = [
            'bot_ia_activo' => $activo,
            'bot_ia_activado_at' => $activo ? ($suscripcion->bot_ia_activado_at ?? now()) : null,
        ];

        if ($precio !== null) {
            $payload['bot_ia_precio_mensual'] = $precio;
        } elseif ($activo && $suscripcion->bot_ia_precio_mensual === null) {
            $payload['bot_ia_precio_mensual'] = SubscriptionBotIaAddon::defaultPrecioMensual();
        }

        $suscripcion->update($payload);

        $tenantName = $suscripcion->tenant?->nombre_comercial
            ?: $suscripcion->tenant?->razon_social
            ?: 'el tenant';

        return back()->with(
            'success',
            $activo
                ? "Asistente IA activado para {$tenantName}."
                : "Asistente IA desactivado para {$tenantName}.",
        );
    }

    /**
     * Cancela la suscripción de forma definitiva. La fila se conserva
     * (no soft delete) para mantener historia de cobranza.
     */
    public function cancel(Request $request, Subscription $suscripcion): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($suscripcion->estado === 'cancelled') {
            return back()->with('info', 'La suscripción ya estaba cancelada.');
        }

        $suscripcion->update([
            'estado' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => $data['reason'],
            'cancel_feedback' => $data['feedback'] ?? null,
        ]);

        return back()->with('success', 'Suscripción cancelada correctamente.');
    }

    public function destroy(Subscription $suscripcion): RedirectResponse
    {
        // Solo permitimos borrar suscripciones canceladas (defensa: las
        // activas / trial son contratos vivos, no se deben perder).
        if ($suscripcion->estado !== 'cancelled') {
            throw ValidationException::withMessages([
                'id' => 'Solo se pueden eliminar suscripciones canceladas. Cancela primero la suscripción.',
            ]);
        }

        $suscripcion->delete();

        return back()->with('success', 'Suscripción eliminada correctamente.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['uuid'],
        ]);

        $deletableIds = Subscription::query()
            ->whereIn('id', $data['ids'])
            ->where('estado', 'cancelled')
            ->pluck('id')
            ->all();

        if (empty($deletableIds)) {
            return back()->with(
                'info',
                'No se eliminó ninguna suscripción: ninguna de las seleccionadas estaba en estado cancelled.',
            );
        }

        $count = Subscription::whereIn('id', $deletableIds)->delete();
        $skipped = count($data['ids']) - $count;

        $message = $count === 1
            ? '1 suscripción eliminada correctamente.'
            : "{$count} suscripciones eliminadas correctamente.";

        if ($skipped > 0) {
            $message .= sprintf(
                ' (%d activa%s se omitieron)',
                $skipped,
                $skipped === 1 ? '' : 's',
            );
        }

        return back()->with('success', $message);
    }

    public function export(Request $request): StreamedResponse
    {
        $search = trim((string) $request->string('search', ''));

        $estado = (string) $request->string('estado', 'todos');
        if (! in_array($estado, self::ESTADO_OPTIONS, true)) {
            $estado = 'todos';
        }

        $planId = trim((string) $request->string('plan_id', ''));

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'desc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $query = $this->buildBaseQuery($search, $estado, $planId)
            ->with([
                'tenant:id,slug,razon_social',
                'plan:id,codigo,nombre',
            ]);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $filename = 'suscripciones-'.now()->format('Ymd-His').'.xlsx';
        $exporter = new SubscriptionsXlsxExport();

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
     * @return Builder<Subscription>
     */
    private function buildBaseQuery(string $search, string $estado, string $planId): Builder
    {
        $query = Subscription::query();

        if ($search !== '') {
            // Búsqueda transitiva: el search aplica sobre el tenant y el
            // plan, no sobre la propia suscripción (sus campos son numéricos
            // y fechas, no son buscables por humanos).
            $query->where(function ($q) use ($search) {
                $q->whereHas('tenant', function ($qq) use ($search) {
                    $qq->where('razon_social', 'ILIKE', "%{$search}%")
                        ->orWhere('slug', 'ILIKE', "%{$search}%")
                        ->orWhere('email_admin', 'ILIKE', "%{$search}%")
                        ->orWhere('ruc', 'ILIKE', "%{$search}%");
                })->orWhereHas('plan', function ($qq) use ($search) {
                    $qq->where('codigo', 'ILIKE', "%{$search}%")
                        ->orWhere('nombre', 'ILIKE', "%{$search}%");
                });
            });
        }

        if ($estado !== 'todos') {
            $query->where('estado', $estado);
        }

        if ($planId !== '') {
            $query->where('plan_id', $planId);
        }

        return $query;
    }
}
