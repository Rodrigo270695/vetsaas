<?php

declare(strict_types=1);

use App\Models\MovimientoInventario;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaLinea;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\TenantRolesSeeder;
use Tests\Support\TenantRbac;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\CajaConsultaCargoScenario;
use Tests\Support\TenantMigrateTestGuards;

beforeEach(function (): void {
    TenantMigrateTestGuards::guardIfUnsafePgsql($this);

    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('POS servicios requiere PostgreSQL.');
    }

    config([
        'tenant.central_domains' => ['localhost', '127.0.0.1', 'vetsaas.test'],
        'tenant.root_domain' => 'vetsaas.test',
        'tenant.schema_prefix' => 'vet_',
        'tenant.allowed_states' => ['active', 'trial', 'grace'],
        'tenant.cache_ttl' => 0,
    ]);

    $this->seed(PermissionsSeeder::class);

    $this->slug = 'pos-srv-'.Str::lower(Str::random(4));
    $this->schema = 'vet_test_'.Str::lower(Str::random(6));

    Artisan::call('vetsaas:tenant-migrate', ['schema' => $this->schema]);

    $this->tenant = Tenant::query()->create([
        'slug' => $this->slug,
        'schema_name' => $this->schema,
        'razon_social' => 'Clínica POS Test',
        'nombre_comercial' => 'POS Test',
        'email_admin' => 'pos-srv-'.Str::lower(Str::random(6)).'@test.local',
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

it('registra venta POS solo con línea de servicio sin movimiento de inventario', function (): void {
    $this->actingAs($this->cajero);

    $response = $this->post('http://'.$this->host.'/caja/ventas', [
        'caja_sesion_id' => $this->scenario['sesion']->id,
        'propietario_id' => $this->scenario['propietario']->id,
        'paciente_id' => $this->scenario['paciente']->id,
        'lineas' => [
            [
                'producto_id' => null,
                'concepto' => 'Consulta de urgencia',
                'precio_lista' => '80.00',
                'tipo_linea' => 'servicio',
                'cantidad' => 1,
            ],
        ],
        'metodo_pago' => 'tarjeta',
        'notas' => 'Test POS servicio',
    ]);

    $response->assertRedirect();

    $venta = Venta::query()->latest('created_at')->first();
    expect($venta)->not->toBeNull();
    expect((float) (string) $venta?->total)->toBeGreaterThan(0);

    $linea = VentaLinea::query()->where('venta_id', $venta?->id)->first();
    expect($linea)->not->toBeNull();
    expect($linea?->producto_id)->toBeNull();
    expect($linea?->tipo_linea)->toBe('servicio');
    expect($linea?->descripcion_snapshot)->toBe('Consulta de urgencia');

    expect(
        MovimientoInventario::query()->where('venta_id', $venta?->id)->count(),
    )->toBe(0);
});

it('expone tarifas de servicio para el POS', function (): void {
    $this->actingAs($this->cajero);

    $this->getJson('http://'.$this->host.'/caja/ventas/buscar-servicios?q=bano')
        ->assertOk()
        ->assertJsonStructure(['data']);
});
