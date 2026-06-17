<?php

namespace App\Services\Tenancy;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantSubdomainUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Crea un tenant completo: registro en public.tenants, schema PostgreSQL dedicado,
 * migraciones tenant aplicadas y usuario admin de la clínica.
 *
 * Llamado desde el endpoint /api/internal/saas/provision (Orvae PE) y desde CLI.
 */
class TenantProvisioner
{
    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidArgumentException Slug inválido o plan inexistente.
     * @throws RuntimeException Driver de BD no soportado.
     */
    public function provision(array $payload): Tenant
    {
        $this->guardDriver();

        $plan = $this->resolvePlan($payload['plan_slug']);
        $slug = $this->normalizeSlug($payload['tenant_slug']);
        $schemaName = $this->buildSchemaName();

        $isFreePlan = $plan->codigo === 'free';

        return DB::transaction(function () use ($plan, $slug, $schemaName, $payload, $isFreePlan): Tenant {
            $tenant = Tenant::create([
                'slug' => $slug,
                'schema_name' => $schemaName,
                'razon_social' => $payload['razon_social'],
                'nombre_comercial' => $payload['nombre_comercial'] ?? null,
                'ruc' => $payload['ruc'] ?? null,
                'email_admin' => $payload['admin_email'],
                'telefono' => $payload['telefono'] ?? null,
                'estado' => $isFreePlan ? 'active' : 'trial',
                'trial_ends_at' => $isFreePlan
                    ? null
                    : now()->addDays((int) $plan->trial_days),
                'onboarding_paso' => 0,
                'timezone' => $payload['timezone'] ?? 'America/Lima',
                'locale' => $payload['locale'] ?? 'es_PE',
                'canal_adquisicion' => $payload['canal_adquisicion'] ?? 'orvae',
            ]);

            $subscription = $this->createSubscription($tenant, $plan, $payload);

            if (! empty($payload['payment'])) {
                $this->recordPayment($subscription, $tenant, $plan, $payload['payment']);
            } elseif ($isFreePlan) {
                $this->recordPayment($subscription, $tenant, $plan, [
                    'monto' => 0,
                    'moneda' => 'PEN',
                    'pasarela' => $payload['canal_adquisicion'] ?? 'orvae',
                    'estado' => 'procesado',
                ]);
            }

            $this->createSchemaAndMigrate($schemaName);
            $this->seedTenantSchema($schemaName, $tenant, $payload);

            return $tenant->refresh();
        });
    }

    public function buildLoginUrl(Tenant $tenant): string
    {
        return TenantSubdomainUrl::login($tenant);
    }

    private function guardDriver(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('Solo PostgreSQL soporta multi-tenant por schema.');
        }
    }

    private function resolvePlan(string $codigo): Plan
    {
        $plan = Plan::where('codigo', $codigo)->where('activo', true)->first();

        if ($plan === null) {
            throw new InvalidArgumentException("Plan no encontrado o inactivo: {$codigo}");
        }

        return $plan;
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));

        if (! preg_match('/^[a-z0-9\-]{3,60}$/', $slug)) {
            throw new InvalidArgumentException("Slug inválido: {$slug}");
        }

        if (Tenant::where('slug', $slug)->exists()) {
            throw new InvalidArgumentException("Slug ya está en uso: {$slug}");
        }

        return $slug;
    }

    private function buildSchemaName(): string
    {
        do {
            $name = 'vet_'.strtolower(Str::random(6));
        } while (Tenant::where('schema_name', $name)->exists());

        return $name;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createSubscription(Tenant $tenant, Plan $plan, array $payload): Subscription
    {
        $ciclo = $payload['ciclo'] ?? 'mensual';
        $precio = $ciclo === 'anual' ? (float) $plan->precio_anual : (float) $plan->precio_mensual;

        $isFreePlan = $plan->codigo === 'free';

        $periodEnd = $ciclo === 'anual' ? now()->addYear() : now()->addMonth();

        return Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'estado' => $isFreePlan ? 'active' : 'trial',
            'ciclo' => $ciclo,
            'trial_ends_at' => $tenant->trial_ends_at,
            'current_period_start' => now(),
            'current_period_end' => $periodEnd,
            'proximo_cobro_at' => $periodEnd,
            'precio_pactado' => $precio,
            'descuento_pct' => $payload['descuento_pct'] ?? 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function recordPayment(Subscription $subscription, Tenant $tenant, Plan $plan, array $payment): void
    {
        SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'monto' => $payment['monto'],
            'moneda' => $payment['moneda'] ?? 'PEN',
            'igv_monto' => $payment['igv_monto'] ?? 0,
            'descuento_monto' => $payment['descuento_monto'] ?? 0,
            'total' => $payment['total'] ?? $payment['monto'],
            'estado' => $payment['estado'] ?? 'procesado',
            'pasarela' => $payment['pasarela'] ?? 'orvae',
            'pasarela_transaction_id' => $payment['transaction_id'] ?? null,
            'pasarela_response' => $payment['raw_response'] ?? null,
            'periodo_inicio' => $subscription->current_period_start,
            'periodo_fin' => $subscription->current_period_end,
            'pagado_at' => isset($payment['pagado_at']) ? Carbon::parse($payment['pagado_at']) : now(),
            'created_at' => now(),
        ]);
    }

    private function createSchemaAndMigrate(string $schema): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS "'.$schema.'"');

        $tenantMigrationNames = collect(glob(database_path('migrations/tenant/*.php')) ?: [])
            ->map(fn (string $path): string => pathinfo($path, PATHINFO_FILENAME))
            ->all();

        if ($tenantMigrationNames !== []) {
            DB::table('migrations')->whereIn('migration', $tenantMigrationNames)->delete();
        }

        config(['tenant.migration_schema' => $schema]);

        try {
            Artisan::call('migrate', [
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
        } finally {
            config(['tenant.migration_schema' => null]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function seedTenantSchema(string $schema, Tenant $tenant, array $payload): void
    {
        DB::statement('SET LOCAL search_path TO "'.$schema.'", public');

        DB::table('cfg_clinic_settings')->insert([
            'id' => (string) Str::uuid(),
            'razon_social' => $tenant->razon_social,
            'nombre_comercial' => $tenant->nombre_comercial,
            'ruc' => $tenant->ruc,
            'email_institucional' => $tenant->email_admin,
            'telefono_principal' => $tenant->telefono,
            'grooming_catalogo_personalizado' => true,
            'hotel_catalogo_personalizado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::statement('SET LOCAL search_path TO public');

        $nombre = trim(implode(' ', array_filter([
            $payload['admin_nombres'] ?? 'Administrador',
            $payload['admin_apellidos'] ?? 'Clínica',
        ])));

        $user = User::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'email' => $tenant->email_admin,
            ],
            [
                'name' => $nombre !== '' ? $nombre : 'Administrador Clínica',
                'password' => Hash::make((string) $payload['admin_password']),
                'phone' => $payload['telefono'] ?? null,
                'is_active' => true,
                'must_change_password' => true,
                'email_verified_at' => null,
            ],
        );

        if ($user->roles()->where('name', 'admin_clinica')->doesntExist()) {
            $user->assignRole('admin_clinica');
        }
    }
}
