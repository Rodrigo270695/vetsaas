<?php

use App\Models\ConsultaCargo;
use App\Models\MovimientoInventario;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Venta;
use App\Tenancy\Facades\Tenant as TenantContext;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CajaConsultaCargoScenario;
use Tests\Support\TenantMigrateTestGuards;

/**
 * Cobro integrado: pre-cuenta confirmada → venta en caja.
 *
 * Requiere PostgreSQL (multi-schema). Con SQLite se omiten.
 *
 * Ejemplo:
 * `DB_CONNECTION=pgsql DB_DATABASE=vetsaas_test php artisan test tests/Feature/Caja/VentaDesdeConsultaCargoTest.php`
 */
beforeEach(function (): void {
    TenantMigrateTestGuards::guardIfUnsafePgsql($this);

    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Caja/consulta-cargos usa schemas PostgreSQL.');
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

    $this->slug = 'caja-cobro-'.Str::lower(Str::random(4));
    $this->schema = 'vet_test_'.Str::lower(Str::random(6));

    Artisan::call('vetsaas:tenant-migrate', [
        'schema' => $this->schema,
    ]);

    $this->tenant = Tenant::create([
        'slug' => $this->slug,
        'schema_name' => $this->schema,
        'razon_social' => 'Clínica Caja Test',
        'nombre_comercial' => 'Caja Test',
        'email_admin' => 'caja@test.local',
        'timezone' => 'America/Lima',
        'locale' => 'es',
        'estado' => 'active',
    ]);

    $this->cajero = User::factory()->create([
        'email' => 'caja@test.local',
        'tenant_id' => $this->tenant->id,
        'password' => Hash::make('clave-caja'),
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

    if (isset($this->cajero)) {
        $this->cajero->forceDelete();
    }

    if (isset($this->tenant)) {
        $this->tenant->forceDelete();
    }

    if (isset($this->schema)) {
        DB::statement('DROP SCHEMA IF EXISTS "'.$this->schema.'" CASCADE');
    }

    DB::statement('SET search_path TO public');

    if (isset($this->scenario['sede'])) {
        $this->scenario['sede']->forceDelete();
    }
});

it('registra venta desde pre-cuenta confirmada y vincula consulta y cargo', function (): void {
    $payload = CajaConsultaCargoScenario::ventaPayloadFromCargo($this->scenario);

    $this->actingAs($this->cajero)
        ->post($this->baseUrl.'/caja/ventas', $payload)
        ->assertRedirect();

    TenantContext::runForSlug($this->slug, function (): void {
        $cargo = ConsultaCargo::query()->findOrFail($this->scenario['cargo']->id);
        expect($cargo->venta_id)->not->toBeNull();

        $venta = Venta::query()->with('lineas')->findOrFail($cargo->venta_id);
        expect($venta->estado)->toBe(Venta::ESTADO_PAGADO);
        expect((string) $venta->consulta_id)->toBe((string) $this->scenario['consulta']->id);
        expect((string) $venta->consulta_cargo_id)->toBe((string) $cargo->id);
        expect($venta->lineas)->toHaveCount(2);

        $lineasProducto = $venta->lineas->whereNotNull('producto_id');
        $lineasServicio = $venta->lineas->whereNull('producto_id');
        expect($lineasProducto)->toHaveCount(1);
        expect($lineasServicio)->toHaveCount(1);

        $movimientos = MovimientoInventario::query()
            ->where('venta_id', $venta->id)
            ->get();
        expect($movimientos)->toHaveCount(1);
        expect((string) $movimientos->first()->producto_id)->toBe((string) $this->scenario['producto']->id);

        $stock = (float) DB::table('existencias_sede')
            ->where('producto_id', $this->scenario['producto']->id)
            ->where('sede_id', $this->scenario['sede']->id)
            ->value('cantidad');
        expect($stock)->toBe(49.0);
    });
});

it('rechaza un segundo cobro de la misma pre-cuenta', function (): void {
    $payload = CajaConsultaCargoScenario::ventaPayloadFromCargo($this->scenario);

    $this->actingAs($this->cajero)
        ->post($this->baseUrl.'/caja/ventas', $payload)
        ->assertRedirect();

    $this->actingAs($this->cajero)
        ->from($this->baseUrl.'/caja/ventas/nuevo')
        ->post($this->baseUrl.'/caja/ventas', $payload)
        ->assertSessionHasErrors('consulta_cargo_id');
});

it('rechaza cobro si la pre-cuenta no está confirmada', function (): void {
    TenantContext::runForSlug($this->slug, function (): void {
        ConsultaCargo::query()
            ->whereKey($this->scenario['cargo']->id)
            ->update(['estado' => ConsultaCargo::ESTADO_BORRADOR]);
    });

    $payload = CajaConsultaCargoScenario::ventaPayloadFromCargo($this->scenario);

    $this->actingAs($this->cajero)
        ->from($this->baseUrl.'/caja/ventas/nuevo')
        ->post($this->baseUrl.'/caja/ventas', $payload)
        ->assertSessionHasErrors('consulta_cargo_id');
});

it('muestra POS precargado desde consulta con sesión abierta', function (): void {
    $consultaId = $this->scenario['consulta']->id;

    $this->actingAs($this->cajero)
        ->get($this->baseUrl.'/caja/ventas/desde-consulta/'.$consultaId)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('caja/ventas/create')
            ->has('desde_cargo', fn (Assert $dc) => $dc
                ->where('consulta_id', $consultaId)
                ->where('consulta_cargo_id', $this->scenario['cargo']->id)
                ->has('lineas_iniciales', 2)
                ->etc()
            )
            ->where('puede_vender', true));
});

it('redirige a cargos si no hay sesión de caja al abrir cobro desde consulta', function (): void {
    TenantContext::runForSlug($this->slug, function (): void {
        DB::table('caja_sesiones')->delete();
    });

    $consultaId = $this->scenario['consulta']->id;

    $this->actingAs($this->cajero)
        ->get($this->baseUrl.'/caja/ventas/desde-consulta/'.$consultaId)
        ->assertRedirect()
        ->assertSessionHasErrors('caja');
});

it('expone ticket de venta vinculada a consulta', function (): void {
    $payload = CajaConsultaCargoScenario::ventaPayloadFromCargo($this->scenario);

    $response = $this->actingAs($this->cajero)
        ->post($this->baseUrl.'/caja/ventas', $payload);

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->not->toBeNull();

    $this->actingAs($this->cajero)
        ->get($location.'/ticket')
        ->assertOk()
        ->assertSee('VTA-', false);
});
