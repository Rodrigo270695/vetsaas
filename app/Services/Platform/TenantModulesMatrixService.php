<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantWhatsAppSession;
use App\Support\Tenancy\TenantModuleAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Matriz de módulos operativos y capacidades comerciales por clínica.
 * Solo lee schema public (tenants, subscriptions, plan_features, WA).
 */
final class TenantModulesMatrixService
{
    /** @var list<string> */
    public const HIGHLIGHT_KEYS = [
        'grooming',
        'hotel',
        'laboratorio',
        'hospitalizacion',
        'cirugias',
        'documentos',
        'bot_ia',
        'comunicaciones_cola',
    ];

    /** @var list<string> */
    public const SCOPES = [
        'todos',
        'con_apagados',
        'sin_grooming',
        'sin_hotel',
        'sin_laboratorio',
        'sin_bot_nav',
        'sin_bot_addon',
        'sin_whatsapp',
        'sin_fel',
        'upsell_bot',
    ];

    /** @var list<string> */
    private const LIVING = ['trial', 'active', 'grace', 'suspended'];

    /**
     * @return array{
     *     items: LengthAwarePaginator,
     *     filters: array{search: string, scope: string, per_page: int},
     *     stats: array<string, mixed>,
     *     columns: list<array{key: string, kind: string}>
     * }
     */
    public function paginate(string $search, string $scope, int $perPage): array
    {
        $perPage = in_array($perPage, [10, 15, 25, 50], true) ? $perPage : 15;
        $scope = in_array($scope, self::SCOPES, true) ? $scope : 'todos';
        $search = trim($search);

        $query = Tenant::query()
            ->whereIn('estado', self::LIVING)
            ->with(['whatsappSession']);

        $this->applySearch($query, $search);
        $this->applyScope($query, $scope);

        $query->orderBy('slug');

        $paginator = $query->paginate($perPage)->withQueryString();
        $rows = $paginator->getCollection();
        $subs = $this->subscriptionsByTenant($rows->pluck('id')->all());

        $mapped = $rows->map(fn (Tenant $tenant): array => $this->serialize(
            $tenant,
            $subs->get((string) $tenant->id),
        ));
        $paginator->setCollection($mapped);

        return [
            'items' => $paginator,
            'filters' => [
                'search' => $search,
                'scope' => $scope,
                'per_page' => $perPage,
            ],
            'stats' => $this->stats(),
            'columns' => $this->columnMeta(),
        ];
    }

    /**
     * @return list<array{key: string, kind: string}>
     */
    private function columnMeta(): array
    {
        $cols = [];
        foreach (self::HIGHLIGHT_KEYS as $key) {
            $cols[] = ['key' => $key, 'kind' => 'module'];
        }
        $cols[] = ['key' => 'bot_addon', 'kind' => 'addon'];
        $cols[] = ['key' => 'whatsapp', 'kind' => 'whatsapp'];
        $cols[] = ['key' => 'sunat', 'kind' => 'sunat'];
        $cols[] = ['key' => 'boletas', 'kind' => 'plan'];
        $cols[] = ['key' => 'facturas', 'kind' => 'plan'];

        return $cols;
    }

    /**
     * @param  Builder<Tenant>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $like = '%'.$search.'%';
        $query->where(function (Builder $inner) use ($like): void {
            $inner->where('slug', 'ilike', $like)
                ->orWhere('nombre_comercial', 'ilike', $like)
                ->orWhere('razon_social', 'ilike', $like);
        });
    }

    /**
     * @param  Builder<Tenant>  $query
     */
    private function applyScope(Builder $query, string $scope): void
    {
        match ($scope) {
            'con_apagados' => $query->whereRaw(
                "json_array_length(coalesce(modulos_deshabilitados, '[]'::json)) > 0",
            ),
            'sin_grooming' => $query->whereJsonContains('modulos_deshabilitados', 'grooming'),
            'sin_hotel' => $query->whereJsonContains('modulos_deshabilitados', 'hotel'),
            'sin_laboratorio' => $query->whereJsonContains('modulos_deshabilitados', 'laboratorio'),
            'sin_bot_nav' => $query->whereJsonContains('modulos_deshabilitados', 'bot_ia'),
            'sin_whatsapp' => $query->whereDoesntHave('whatsappSession', function (Builder $wa): void {
                $wa->where('status', TenantWhatsAppSession::STATUS_READY);
            }),
            'sin_bot_addon' => $query->whereDoesntHave('subscriptions', function (Builder $sub): void {
                $sub->where('bot_ia_activo', true)
                    ->where('estado', '!=', 'cancelled');
            }),
            'sin_fel' => $query->where(function (Builder $q): void {
                $q->where('sunat_configurado', false)
                    ->orWhereNull('sunat_configurado');
            }),
            'upsell_bot' => $query
                ->where(function (Builder $q): void {
                    $q->whereNull('modulos_deshabilitados')
                        ->orWhereJsonDoesntContain('modulos_deshabilitados', 'bot_ia');
                })
                ->whereDoesntHave('subscriptions', function (Builder $sub): void {
                    $sub->where('bot_ia_activo', true)
                        ->where('estado', '!=', 'cancelled');
                }),
            default => null,
        };
    }

    /**
     * @param  list<string>  $tenantIds
     * @return \Illuminate\Support\Collection<string, Subscription>
     */
    private function subscriptionsByTenant(array $tenantIds): \Illuminate\Support\Collection
    {
        if ($tenantIds === []) {
            return collect();
        }

        return Subscription::query()
            ->with(['plan.features'])
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('estado', ['trial', 'active', 'grace', 'suspended'])
            ->orderByDesc('updated_at')
            ->get()
            ->unique('tenant_id')
            ->keyBy(fn (Subscription $sub): string => (string) $sub->tenant_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(): array
    {
        $tenants = Tenant::query()
            ->whereIn('estado', self::LIVING)
            ->get(['id', 'modulos_deshabilitados', 'sunat_configurado']);

        $living = $tenants->count();
        $on = [];
        foreach (self::HIGHLIGHT_KEYS as $key) {
            $on[$key] = 0;
        }
        $apagados = 0;
        $sunat = 0;

        foreach ($tenants as $tenant) {
            $disabled = is_array($tenant->modulos_deshabilitados)
                ? $tenant->modulos_deshabilitados
                : [];
            if ($disabled !== []) {
                $apagados++;
            }
            foreach (self::HIGHLIGHT_KEYS as $key) {
                if (! in_array($key, $disabled, true)) {
                    $on[$key]++;
                }
            }
            if ($tenant->sunat_configurado) {
                $sunat++;
            }
        }

        $ids = $tenants->pluck('id');
        $whatsappReady = TenantWhatsAppSession::query()
            ->whereIn('tenant_id', $ids)
            ->where('status', TenantWhatsAppSession::STATUS_READY)
            ->count();
        $botAddon = Subscription::query()
            ->whereIn('tenant_id', $ids)
            ->where('bot_ia_activo', true)
            ->where('estado', '!=', 'cancelled')
            ->pluck('tenant_id')
            ->unique()
            ->count();

        return [
            'living' => $living,
            'con_apagados' => $apagados,
            'modules_on' => $on,
            'whatsapp_ready' => $whatsappReady,
            'bot_addon' => $botAddon,
            'sunat' => $sunat,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Tenant $tenant, ?Subscription $subscription): array
    {
        $nombre = trim((string) ($tenant->nombre_comercial ?: $tenant->razon_social ?: $tenant->slug));
        $snapshot = TenantModuleAccess::snapshot($tenant);
        $flags = [];
        foreach (self::HIGHLIGHT_KEYS as $key) {
            $flags[$key] = $snapshot['enabled'][$key] ?? true;
        }

        $plan = $subscription?->plan;
        $wa = $tenant->whatsappSession;

        return [
            'tenant' => [
                'id' => (string) $tenant->id,
                'slug' => $tenant->slug,
                'nombre' => $nombre,
                'estado' => $tenant->estado,
            ],
            'plan' => $plan?->nombre,
            'disabled_count' => count($snapshot['deshabilitados']),
            'flags' => $flags,
            'bot_addon' => (bool) ($subscription?->bot_ia_activo && $subscription->estado !== 'cancelled'),
            'whatsapp' => $wa?->status === TenantWhatsAppSession::STATUS_READY,
            'sunat' => (bool) $tenant->sunat_configurado,
            'boletas' => $this->planBool($plan, 'boletas_electronicas'),
            'facturas' => $this->planBool($plan, 'facturas_electronicas'),
        ];
    }

    private function planBool(?Plan $plan, string $feature): bool
    {
        if ($plan === null) {
            return false;
        }

        if ($plan->relationLoaded('features')) {
            $row = $plan->features->firstWhere('feature', $feature);
            if ($row === null) {
                $meta = Plan::FEATURE_CATALOG[$feature] ?? null;

                return (bool) ($meta['default'] ?? false);
            }

            return (bool) $row->valor_bool;
        }

        return (bool) $plan->resolveFeature($feature);
    }
}
