<?php

use App\Models\HotelEstancia;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Venta\VentaDesdeCargoPrefill;
use App\Tenancy\Facades\Tenant as TenantContext;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\HotelServiciosScenario;
use Tests\Support\TenantMigrateTestGuards;

/**
 * Hotel/guardería: tarifa prefill caja + API JSON bitácora diaria.
 *
 * Requiere PostgreSQL (`*_test` / `_testing` o `VETSAAS_ALLOW_TENANT_MIGRATE_TESTS`).
 */
beforeEach(function (): void {
    TenantMigrateTestGuards::guardIfUnsafePgsql($this);

    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Hotel tenant usa schemas PostgreSQL.');
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

    $this->slug = 'svc-hotel-'.Str::lower(Str::random(4));
    $this->schema = 'vet_test_'.Str::lower(Str::random(6));

    Artisan::call('vetsaas:tenant-migrate', [
        'schema' => $this->schema,
    ]);

    $this->tenant = Tenant::create([
        'slug' => $this->slug,
        'schema_name' => $this->schema,
        'razon_social' => 'Clínica Hotel Test',
        'nombre_comercial' => 'Hotel Test',
        'email_admin' => 'admin-hotel@test.local',
        'timezone' => 'America/Lima',
        'locale' => 'es',
        'estado' => 'active',
    ]);

    $this->admin = User::factory()->create([
        'email' => 'admin-hotel@test.local',
        'tenant_id' => $this->tenant->id,
        'password' => Hash::make('clave-hotel'),
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $this->admin->assignRole('admin_clinica');

    $this->host = $this->slug.'.vetsaas.test';

    $this->scenario = HotelServiciosScenario::seed(
        $this->tenant,
        $this->slug,
        (string) $this->admin->id,
    );
});

afterEach(function (): void {
    TenantContext::forget();

    if (DB::getDriverName() !== 'pgsql') {
        return;
    }

    if (isset($this->admin)) {
        $this->admin->forceDelete();
    }

    if (isset($this->tenant)) {
        $this->tenant->forceDelete();
    }

    if (isset($this->schema)) {
        DB::statement('DROP SCHEMA IF EXISTS "'.$this->schema.'" CASCADE');
    }

    DB::statement('SET search_path TO public');

    if (isset($this->scenario) && isset($this->scenario['sede'])) {
        $this->scenario['sede']->forceDelete();
    }
});


function hotelServiciosActingAs(object $test, User $user): void
{
    $test->actingAs($user, 'web')
        ->withServerVariables(['HTTP_HOST' => $test->host]);
}

test('prefill venta desde hotel usa tarifa por noche y noches sugeridas', function (): void {
    Auth::loginUsingId($this->admin->id);

    TenantContext::runForSlug($this->slug, function (): void {
        $estancia = HotelEstancia::query()->firstOrFail();
        expect($estancia->nochesSugeridasParaVenta())->toBe(2);

        $data = app(VentaDesdeCargoPrefill::class)->buildFromHotelEstancia($estancia);

        expect($data['hotel_estancia_id'])->toBe($estancia->id);
        expect($data['lineas_iniciales'][0]['precio_lista'])->toBe('30.00');
        expect($data['lineas_iniciales'][0]['cantidad'])->toBe('2.00');
        expect($data['cargo_total'])->toBe('60.00');
    });
});

test('API bitácora: listar vacío crear y eliminar', function (): void {
    hotelServiciosActingAs($this, $this->admin);

    $id = $this->scenario['estancia']->id;
    $base = 'http://'.$this->host.'/servicios/hotel/'.$id.'/diarios';

    $this->getJson($base)
        ->assertOk()
        ->assertJsonPath('data', []);

    $created = $this->postJson($base, [
        'fecha' => '2026-03-11',
        'notas' => 'Comió bien, animado.',
    ])->assertCreated()
        ->assertJsonPath('data.fecha', '2026-03-11');

    $diarioId = $created->json('data.id');
    expect($diarioId)->not->toBeNull();

    $this->getJson($base)
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->deleteJson($base.'/'.$diarioId)
        ->assertOk();

    $this->getJson($base)
        ->assertOk()
        ->assertJsonPath('data', []);
});
