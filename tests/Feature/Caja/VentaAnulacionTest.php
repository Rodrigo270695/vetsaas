<?php

declare(strict_types=1);

use App\Models\ClinicSetting;
use App\Models\ConsultaCargo;
use App\Models\FelDocument;
use App\Models\MovimientoInventario;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\CajaConsultaCargoScenario;
use Tests\Support\TenantMigrateTestGuards;

beforeEach(function (): void {
    TenantMigrateTestGuards::guardIfUnsafePgsql($this);

    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Anulación de ventas requiere PostgreSQL.');
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

    $this->slug = 'anul-venta-'.Str::lower(Str::random(4));
    $this->schema = 'vet_test_'.Str::lower(Str::random(6));

    Artisan::call('vetsaas:tenant-migrate', ['schema' => $this->schema]);

    $this->tenant = Tenant::query()->create([
        'slug' => $this->slug,
        'schema_name' => $this->schema,
        'razon_social' => 'Clínica Anulación Test',
        'nombre_comercial' => 'Anulación Test',
        'email_admin' => 'anul-'.Str::lower(Str::random(6)).'@test.local',
        'timezone' => 'America/Lima',
        'locale' => 'es',
        'estado' => 'active',
    ]);

    $this->cajero = User::factory()->create([
        'email' => $this->tenant->email_admin,
        'tenant_id' => $this->tenant->id,
        'password' => Hash::make('password'),
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
});

afterEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        return;
    }

    Http::fake();

    if (isset($this->schema)) {
        DB::statement('DROP SCHEMA IF EXISTS "'.$this->schema.'" CASCADE');
    }

    DB::statement('SET search_path TO public');

    if (isset($this->scenario['sede'])) {
        $this->scenario['sede']->forceDelete();
    }

    if (isset($this->tenant)) {
        $this->tenant->forceDelete();
    }

    if (isset($this->cajero)) {
        $this->cajero->forceDelete();
    }
});

it('anula venta pagada, revierte stock y libera pre-cuenta', function (): void {
    $payload = CajaConsultaCargoScenario::ventaPayloadFromCargo($this->scenario);

    $this->actingAs($this->cajero)
        ->post($this->baseUrl.'/caja/ventas', $payload)
        ->assertRedirect();

    $ventaId = null;
    $productoId = (string) $this->scenario['producto']->id;
    $sedeId = (string) $this->scenario['sede']->id;
    $cargoId = (string) $this->scenario['cargo']->id;

    TenantContext::runForSlug($this->slug, function () use (&$ventaId, $productoId, $sedeId, $cargoId): void {
        $venta = Venta::query()->orderByDesc('created_at')->firstOrFail();
        $ventaId = $venta->id;

        expect($venta->estado)->toBe(Venta::ESTADO_PAGADO);
        expect(
            ConsultaCargo::query()->whereKey($cargoId)->value('venta_id'),
        )->toBe($venta->id);

        $stockTrasVenta = (float) (string) DB::table('existencias_sede')
            ->where('producto_id', $productoId)
            ->where('sede_id', $sedeId)
            ->value('cantidad');
        expect($stockTrasVenta)->toBe(49.0);

        expect(
            MovimientoInventario::query()
                ->where('venta_id', $venta->id)
                ->where('tipo', MovimientoInventario::TIPO_SALIDA)
                ->count(),
        )->toBe(1);
    });

    $this->actingAs($this->cajero)
        ->post($this->baseUrl.'/caja/ventas/'.$ventaId.'/anular', [
            'motivo' => 'Cobro duplicado por error de caja',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    TenantContext::runForSlug($this->slug, function () use ($ventaId, $productoId, $sedeId, $cargoId): void {
        $venta = Venta::query()->findOrFail($ventaId);
        expect($venta->estado)->toBe(Venta::ESTADO_ANULADO);
        expect($venta->anulado_at)->not->toBeNull();
        expect($venta->motivo_anulacion)->toContain('duplicado');

        expect(
            ConsultaCargo::query()->whereKey($cargoId)->value('venta_id'),
        )->toBeNull();

        $stockTrasAnulacion = (float) (string) DB::table('existencias_sede')
            ->where('producto_id', $productoId)
            ->where('sede_id', $sedeId)
            ->value('cantidad');
        expect($stockTrasAnulacion)->toBe(50.0);

        expect(
            MovimientoInventario::query()
                ->where('venta_id', $ventaId)
                ->where('tipo', MovimientoInventario::TIPO_ENTRADA)
                ->count(),
        )->toBe(1);
    });
});

it('anula comprobante FEL emitido vía Nubefact', function (): void {
    $plan = Plan::query()->firstOrCreate(
        ['codigo' => 'clinica'],
        [
            'nombre' => 'Plan Clínica test anulación FEL',
            'descripcion' => null,
            'precio_mensual' => '0.00',
            'precio_anual' => null,
            'trial_days' => 0,
            'orden' => 999,
            'es_publico' => false,
            'activo' => true,
        ],
    );

    PlanFeature::query()->updateOrCreate(
        ['plan_id' => $plan->id, 'feature' => 'factura_electronica'],
        [
            'valor_int' => null,
            'valor_bool' => true,
            'valor_str' => null,
        ],
    );

    Subscription::query()->create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenant->id,
        'plan_id' => $plan->id,
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

    Http::fake([
        '*' => Http::sequence()
            ->push([
                'aceptada_por_sunat' => true,
                'codigo_unico' => 'NUBEFACT-ANUL-EMIT',
                'enlace_del_pdf' => 'https://example.test/cpe.pdf',
            ], 200)
            ->push(['anulado' => true], 200),
    ]);

    TenantContext::runForSlug($this->slug, function (): void {
        ClinicSetting::query()->firstOrFail()->update([
            'emite_comprobantes_sunat' => true,
            'nubefact_configurado' => true,
            'nubefact_ruc' => '20600655571',
            'nubefact_api_ruta' => 'https://api.nubefact.com/api/v1/anul-test-local',
            'nubefact_token_enc' => Crypt::encryptString('token-anul-test'),
        ]);
    });

    $payload = CajaConsultaCargoScenario::ventaPayloadFromCargo($this->scenario);

    $this->actingAs($this->cajero)
        ->post($this->baseUrl.'/caja/ventas', $payload)
        ->assertRedirect();

    $ventaId = null;
    TenantContext::runForSlug($this->slug, function () use (&$ventaId): void {
        $venta = Venta::query()->orderByDesc('created_at')->firstOrFail();
        $ventaId = $venta->id;
        expect($venta->fel_estado)->toBe(Venta::FEL_EMITIDO);
    });

    $this->actingAs($this->cajero)
        ->post($this->baseUrl.'/caja/ventas/'.$ventaId.'/anular', [
            'motivo' => 'Cliente solicitó anulación del comprobante',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    TenantContext::runForSlug($this->slug, function () use ($ventaId): void {
        $venta = Venta::query()->findOrFail($ventaId);
        expect($venta->estado)->toBe(Venta::ESTADO_ANULADO);
        expect($venta->fel_estado)->toBe(Venta::FEL_ANULADO);

        $doc = FelDocument::query()->where('venta_id', $ventaId)->first();
        expect($doc)->not->toBeNull();
        expect($doc->estado)->toBe(FelDocument::ESTADO_ANULADO);
        expect($doc->anulado_at)->not->toBeNull();
    });

    Http::assertSentCount(2);
});
