<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Tenancy\TenantShowcaseService;
use App\Support\Clinic\ClinicBrandingUrls;
use App\Tenancy\TenantManager;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Showcase tenant requiere PostgreSQL.');
    }

    Cache::flush();
    $this->seed(PermissionsSeeder::class);
    $this->seed(PlansAndFeaturesSeeder::class);

    $this->slugPaid = 'showcase-paid-'.Str::lower(Str::random(4));
    $this->schemaPaid = 'vet_test_'.Str::lower(Str::random(6));

    Artisan::call('vetsaas:tenant-migrate', ['schema' => $this->schemaPaid]);

    $this->planPro = Plan::query()->where('codigo', 'pro')->where('activo', true)->firstOrFail();

    $this->tenantPaid = Tenant::query()->create([
        'slug' => $this->slugPaid,
        'schema_name' => $this->schemaPaid,
        'razon_social' => 'Clínica Showcase Pro',
        'nombre_comercial' => 'Showcase Pro',
        'email_admin' => 'showcase-pro@test.local',
        'timezone' => 'America/Lima',
        'locale' => 'es',
        'estado' => 'active',
    ]);

    Subscription::query()->create([
        'tenant_id' => $this->tenantPaid->id,
        'plan_id' => $this->planPro->id,
        'estado' => 'active',
        'ciclo' => 'mensual',
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
    ]);

    app(TenantManager::class)->runForSlug($this->slugPaid, function (): void {
        $path = 'tenants/'.$this->slugPaid.'/logos/test.png';
        Storage::disk('public')->put($path, 'fake-png');
        DB::table('cfg_clinic_settings')->insert([
            'id' => (string) Str::uuid(),
            'logo_path' => $path,
            'duracion_cita_default_min' => 30,
            'intervalo_agenda_min' => 15,
            'dias_anticipacion_cita' => 1,
            'horas_min_cancelacion' => 24,
            'moneda' => 'PEN',
            'igv_porcentaje' => 18,
            'precio_incluye_igv' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });
});

afterEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        return;
    }

    if (isset($this->tenantPaid)) {
        $this->tenantPaid->forceDelete();
    }

    if (isset($this->schemaPaid)) {
        DB::statement('DROP SCHEMA IF EXISTS "'.$this->schemaPaid.'" CASCADE');
    }

    DB::statement('SET search_path TO public');
});

it('expone clientes de plan pago con logo en el endpoint público', function (): void {
    $response = $this->getJson('/api/public/vetsaas/showcase');

    $response->assertOk();
    $response->assertJsonPath('data.0.slug', $this->slugPaid);
    $response->assertJsonPath('data.0.name', 'Showcase Pro');
});

it('omite tenants sin logo propio', function (): void {
    $slugFree = 'showcase-free-'.Str::lower(Str::random(4));
    $schemaFree = 'vet_test_'.Str::lower(Str::random(6));
    Artisan::call('vetsaas:tenant-migrate', ['schema' => $schemaFree]);

    $tenantFree = Tenant::query()->create([
        'slug' => $slugFree,
        'schema_name' => $schemaFree,
        'razon_social' => 'Clínica Sin Logo',
        'email_admin' => 'free@test.local',
        'timezone' => 'America/Lima',
        'locale' => 'es',
        'estado' => 'active',
    ]);

    Subscription::query()->create([
        'tenant_id' => $tenantFree->id,
        'plan_id' => $this->planPro->id,
        'estado' => 'active',
        'ciclo' => 'mensual',
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
    ]);

    $data = app(TenantShowcaseService::class)->clientsForCarousel();
    $slugs = collect($data)->pluck('slug');

    expect($slugs)->toContain($this->slugPaid);
    expect($slugs)->not->toContain($slugFree);

    $tenantFree->forceDelete();
    DB::statement('DROP SCHEMA IF EXISTS "'.$schemaFree.'" CASCADE');
});

it('marca has_custom_logo en el listado de tenants', function (): void {
    $branding = ClinicBrandingUrls::resolveForTenant(
        app(TenantManager::class),
        $this->tenantPaid,
    );

    expect($branding['has_custom_logo'])->toBeTrue();
});
