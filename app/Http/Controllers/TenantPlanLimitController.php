<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TenantPlanLimitsRequest;
use App\Models\Tenant;
use App\Models\TenantPlanOverride;
use App\Support\Plan\PlanLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TenantPlanLimitController extends Controller
{
    public function edit(Tenant $tenant): Response
    {
        $tenant->loadMissing(['subscriptions.plan']);

        $plan = $tenant->activeSubscription()?->plan;
        $overrides = $tenant->planOverrides()->get()->keyBy('feature');

        $features = [];

        foreach (PlanLimits::OVERRIDABLE_FEATURES as $feature) {
            $base = PlanLimits::planBaseLimit($tenant, $feature);
            /** @var TenantPlanOverride|null $row */
            $row = $overrides->get($feature);
            $active = $row !== null && $row->isActive();

            $features[] = [
                'feature' => $feature,
                'base' => $base,
                'extra' => $active ? (int) $row->extra : 0,
                'precio_mensual' => $active && $row->isPaid()
                    ? (float) $row->precio_mensual
                    : null,
                'override' => $active ? $row->override : null,
                'motivo' => $active ? $row->motivo : null,
                'expires_at' => $active && $row->expires_at !== null
                    ? $row->expires_at->timezone(config('app.timezone'))->toDateString()
                    : null,
                'effective' => PlanLimits::intLimit($tenant, $feature),
                'unlimited_base' => $base === null,
            ];
        }

        return Inertia::render('plataforma/tenants/limites', [
            'tenant' => [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'razon_social' => $tenant->razon_social,
                'nombre_comercial' => $tenant->nombre_comercial,
                'estado' => $tenant->estado,
            ],
            'plan' => $plan === null ? null : [
                'id' => $plan->id,
                'codigo' => $plan->codigo,
                'nombre' => $plan->nombre,
            ],
            'features' => $features,
        ]);
    }

    public function update(TenantPlanLimitsRequest $request, Tenant $tenant): RedirectResponse
    {
        $rows = $request->normalizedOverrides();

        DB::transaction(function () use ($tenant, $rows): void {
            foreach ($rows as $row) {
                $shouldKeep = $row['override'] !== null
                    || $row['extra'] > 0
                    || $row['precio_mensual'] !== null
                    || filled($row['motivo'])
                    || filled($row['expires_at']);

                if (! $shouldKeep) {
                    TenantPlanOverride::query()
                        ->where('tenant_id', $tenant->id)
                        ->where('feature', $row['feature'])
                        ->delete();

                    continue;
                }

                TenantPlanOverride::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'feature' => $row['feature'],
                    ],
                    [
                        'extra' => $row['override'] !== null ? 0 : $row['extra'],
                        'precio_mensual' => $row['precio_mensual'],
                        'override' => $row['override'],
                        'motivo' => $row['motivo'],
                        'expires_at' => $row['expires_at'],
                        'created_by_id' => Auth::id(),
                    ],
                );
            }
        });

        return redirect()
            ->route('plataforma.tenants.limits.edit', $tenant)
            ->with('success', __('tenants.limits.saved'));
    }
}
