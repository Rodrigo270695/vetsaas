<?php

use App\Models\ClinicSetting;
use App\Models\FelDocument;
use App\Models\FelSerie;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Venta;
use App\Tenancy\Facades\Tenant as TenantContext;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\CajaConsultaCargoScenario;
use Tests\Support\TenantMigrateTestGuards;

/**
 * Emisión FEL (Nubefact) tras venta: job síncrono + servicio de emisión.
 *
 * Requiere PostgreSQL. Con SQLite se omiten.
 *
 * @example DB_CONNECTION=pgsql DB_DATABASE=vetsaas_test php artisan test tests/Feature/Caja/FelEmisionVentaTest.php
 */
beforeEach(function (): void {
    TenantMigrateTestGuards::guardIfUnsafePgsql($this);

    if (\Illuminate\Support\Facades\DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('FEL / multi-schema requiere PostgreSQL.');
    }

    config([
        'tenant.central_domains' => ['localhost', '127.0.0.1', 'vetsaas.test'],
        'tenant.root_domain' => 'vetsaas.test',
        'tenant.schema_prefix' => 'vet_',
        'tenant.allowed_states' => ['active', 'trial', 'grace'],
        'tenant.cache_ttl' => 0,
    ]);

    $this->seed(PermissionsSeeder::class);
    $this->seed(TenantRolesSeeder::class);

    $this->slug = 'fel-venta-'.Str::lower(Str::random(4));
    $this->schema = 'vet_test_'.Str::lower(Str::random(6));

    $this->plan = Plan::query()->create([
        'codigo' => 'TEST-FEL-'.Str::upper(Str::random(6)),
        'nombre' => 'Plan test FEL',
        'descripcion' => null,
        'precio_mensual' => '0.00',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => 999,
        'es_publico' => false,
        'activo' => true,
    ]);

    PlanFeature::query()->create([
        'id' => (string) Str::uuid(),
        'plan_id' => $this->plan->id,
        'feature' => 'factura_electronica',
        'valor_int' => null,
        'valor_bool' => true,
        'valor_str' => null,
    ]);

    Artisan::call('vetsaas:tenant-migrate', [
        'schema' => $this->schema,
    ]);

    $this->tenant = Tenant::create([
        'slug' => $this->slug,
        'schema_name' => $this->schema,
        'razon_social' => 'Clínica FEL Test',
        'nombre_comercial' => 'FEL Test',
        'email_admin' => 'fel@test.local',
        'timezone' => 'America/Lima',
        'locale' => 'es',
        'estado' => 'active',
    ]);

    Subscription::query()->create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'estado' => 'active',
        'ciclo' => 'mensual',
        'trial_ends_at' => null,
        'current_period_start' => now()->subMonth(),
        'current_period_end' => now()->addMonth(),
        'grace_ends_at' => null,
        'cancelled_at' => null,
        'cancel_reason' => null,
        'cancel_feedback' => null,
        'precio_pactado' => '0.00',
        'descuento_pct' => '0.00',
        'promo_code_id' => null,
        'proximo_cobro_at' => null,
        'metodo_pago_token' => null,
    ]);

    $this->cajero = User::factory()->create([
        'email' => 'fel-caja@test.local',
        'tenant_id' => $this->tenant->id,
        'password' => Hash::make('clave-fel'),
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $this->cajero->assignRole('recepcionista');

    $this->host = $this->slug.'.vetsaas.test';
    $this->baseUrl = 'http://'.$this->host;

    $this->scenario = CajaConsultaCargoScenario::seed(
        $this->tenant,
        $this->slug,
        (string) $this->cajero->id,
    );

    TenantContext::runForSlug($this->slug, function (): void {
        $clinic = ClinicSetting::query()->firstOrFail();
        $clinic->update([
            'emite_comprobantes_sunat' => true,
            'nubefact_configurado' => true,
            'nubefact_ruc' => '20600655571',
            'nubefact_token_enc' => Crypt::encryptString('token-fel-test'),
        ]);
    });
});

afterEach(function (): void {
    if (\Illuminate\Support\Facades\DB::getDriverName() !== 'pgsql') {
        return;
    }

    Http::fake();

    if (isset($this->cajero)) {
        $this->cajero->forceDelete();
    }

    if (isset($this->tenant)) {
        $this->tenant->forceDelete();
    }

    if (isset($this->schema)) {
        \Illuminate\Support\Facades\DB::statement('DROP SCHEMA IF EXISTS "'.$this->schema.'" CASCADE');
    }

    \Illuminate\Support\Facades\DB::statement('SET search_path TO public');

    if (isset($this->scenario['sede'])) {
        $this->scenario['sede']->forceDelete();
    }

    if (isset($this->plan)) {
        PlanFeature::query()->where('plan_id', $this->plan->id)->delete();
        $this->plan->delete();
    }
});

it('emite FEL vía job síncrono al registrar venta cuando plan y clínica lo permiten', function (): void {
    Http::fake([
        '*' => Http::response([
            'aceptada_por_sunat' => true,
            'codigo_unico' => 'NUBEFACT-TEST-001',
            'enlace_del_pdf' => 'https://example.test/cpe.pdf',
            'enlace_del_xml' => 'https://example.test/cpe.xml',
        ], 200),
    ]);

    $payload = CajaConsultaCargoScenario::ventaPayloadFromCargo($this->scenario);

    $this->actingAs($this->cajero)
        ->post($this->baseUrl.'/caja/ventas', $payload)
        ->assertRedirect();

    TenantContext::runForSlug($this->slug, function (): void {
        $venta = Venta::query()->orderByDesc('created_at')->first();
        expect($venta)->not->toBeNull();
        expect($venta->fel_estado)->toBe(Venta::FEL_EMITIDO);

        $doc = FelDocument::query()->where('venta_id', $venta->id)->first();
        expect($doc)->not->toBeNull();
        expect($doc->estado)->toBe(FelDocument::ESTADO_EMITIDO);
        expect($doc->nubefact_id)->toBe('NUBEFACT-TEST-001');

        $serie = FelSerie::query()->whereKey($doc->fel_serie_id)->first();
        expect($serie)->not->toBeNull();
        expect((int) $serie->ultimo_correlativo)->toBeGreaterThan(0);
    });

    Http::assertSentCount(1);
});

it('expone emitir FEL manual cuando la venta sigue pendiente de emisión', function (): void {
    TenantContext::runForSlug($this->slug, function (): void {
        ClinicSetting::query()->firstOrFail()->update([
            'emite_comprobantes_sunat' => false,
        ]);
    });

    $payload = CajaConsultaCargoScenario::ventaPayloadFromCargo($this->scenario);

    $this->actingAs($this->cajero)
        ->post($this->baseUrl.'/caja/ventas', $payload)
        ->assertRedirect();

    $ventaId = null;
    TenantContext::runForSlug($this->slug, function () use (&$ventaId): void {
        $venta = Venta::query()->orderByDesc('created_at')->first();
        expect($venta->fel_estado)->toBe(Venta::FEL_SIN_CPE);
        $ventaId = $venta->id;

        ClinicSetting::query()->firstOrFail()->update([
            'emite_comprobantes_sunat' => true,
            'nubefact_configurado' => true,
            'nubefact_token_enc' => Crypt::encryptString('token-fel-test-2'),
        ]);

        $venta->update(['fel_estado' => Venta::FEL_PENDIENTE]);
    });

    Http::fake([
        '*' => Http::response([
            'aceptada_por_sunat' => true,
            'codigo_unico' => 'NUBEFACT-MANUAL-002',
            'enlace_del_pdf' => 'https://example.test/manual.pdf',
        ], 200),
    ]);

    $this->actingAs($this->cajero)
        ->post($this->baseUrl.'/caja/ventas/'.$ventaId.'/emitir-fel')
        ->assertRedirect();

    TenantContext::runForSlug($this->slug, function () use ($ventaId): void {
        $venta = Venta::query()->findOrFail($ventaId);
        expect($venta->fel_estado)->toBe(Venta::FEL_EMITIDO);
        $doc = FelDocument::query()->where('venta_id', $ventaId)->first();
        expect($doc?->nubefact_id)->toBe('NUBEFACT-MANUAL-002');
    });
});
