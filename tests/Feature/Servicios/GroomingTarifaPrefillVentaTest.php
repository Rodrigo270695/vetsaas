<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\GroomingServiciosScenario;
use Tests\Support\TenantMigrateTestGuards;

beforeEach(function (): void {
    TenantMigrateTestGuards::guardIfUnsafePgsql($this);

    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Grooming tarifa requiere PostgreSQL.');
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

    $this->slug = 'gr-tar-'.Str::lower(Str::random(4));
    $this->schema = 'vet_test_'.Str::lower(Str::random(6));

    Artisan::call('vetsaas:tenant-migrate', ['schema' => $this->schema]);

    $this->tenant = Tenant::query()->create([
        'slug' => $this->slug,
        'schema_name' => $this->schema,
        'razon_social' => 'Clínica Grooming Tarifa',
        'nombre_comercial' => 'Grooming Tarifa',
        'email_admin' => 'gr-tar-'.Str::lower(Str::random(6)).'@test.local',
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

    $this->scenario = GroomingServiciosScenario::seed(
        $this->tenant,
        $this->slug,
        (string) $this->cajero->id,
    );
});

afterEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        return;
    }

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

it('prefill de venta desde grooming usa precio de tarifario activo', function (): void {
    $turnoId = $this->scenario['turno']->id;

    $this->actingAs($this->cajero)
        ->get('http://'.$this->host.'/caja/ventas/desde-grooming/'.$turnoId)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('caja/ventas/create')
            ->where('desde_cargo.lineas_iniciales.0.precio_lista', '55.00')
            ->where('desde_cargo.grooming_turno_id', $turnoId));
});

it('expone tarifas grooming en búsqueda del POS', function (): void {
    $this->actingAs($this->cajero)
        ->getJson('http://'.$this->host.'/caja/ventas/buscar-servicios?q=bano')
        ->assertOk()
        ->assertJsonFragment(['nombre' => 'bano_higienico', 'precio_lista' => '55.00']);
});
