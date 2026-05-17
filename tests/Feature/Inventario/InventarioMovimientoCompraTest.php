<?php

declare(strict_types=1);

use App\Models\Compra;
use App\Models\MovimientoInventario;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\InventarioScenario;
use Tests\Support\TenantMigrateTestGuards;

beforeEach(function (): void {
    TenantMigrateTestGuards::guardIfUnsafePgsql($this);

    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Inventario requiere PostgreSQL.');
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

    $this->slug = 'inv-'.Str::lower(Str::random(4));
    $this->schema = 'vet_test_'.Str::lower(Str::random(6));

    Artisan::call('vetsaas:tenant-migrate', ['schema' => $this->schema]);

    $this->tenant = Tenant::query()->create([
        'slug' => $this->slug,
        'schema_name' => $this->schema,
        'razon_social' => 'Clínica Inventario Test',
        'nombre_comercial' => 'Inventario Test',
        'email_admin' => 'inv-'.Str::lower(Str::random(6)).'@test.local',
        'timezone' => 'America/Lima',
        'locale' => 'es',
        'estado' => 'active',
    ]);

    $this->admin = User::factory()->create([
        'email' => $this->tenant->email_admin,
        'tenant_id' => $this->tenant->id,
        'password' => Hash::make('password'),
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $this->admin->assignRole('admin_clinica');

    $this->host = $this->slug.'.vetsaas.test';

    $this->scenario = InventarioScenario::seed(
        $this->tenant,
        $this->slug,
        (string) $this->admin->id,
        10.0,
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

    if (isset($this->admin)) {
        $this->admin->forceDelete();
    }
});

it('registra movimiento manual de entrada y actualiza existencia', function (): void {
    $productoId = (string) $this->scenario['producto']->id;
    $sedeId = (string) $this->scenario['sede']->id;

    $this->actingAs($this->admin)
        ->post('http://'.$this->host.'/inventario/movimientos', [
            'producto_id' => $productoId,
            'sede_id' => $sedeId,
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'cantidad' => '5',
            'notas' => 'Ajuste test',
        ])
        ->assertRedirect();

    expect(InventarioScenario::stockEnSede($productoId, $sedeId))->toBe(15.0);

    expect(
        MovimientoInventario::query()
            ->where('producto_id', $productoId)
            ->where('tipo', MovimientoInventario::TIPO_ENTRADA)
            ->count(),
    )->toBeGreaterThanOrEqual(1);
});

it('registra compra con entrada de stock y revierte al anular', function (): void {
    $productoId = (string) $this->scenario['producto']->id;
    $sedeId = (string) $this->scenario['sede']->id;
    $proveedorId = (string) $this->scenario['proveedor']->id;

    $this->actingAs($this->admin)
        ->post('http://'.$this->host.'/inventario/compras', [
            'sede_id' => $sedeId,
            'proveedor_id' => $proveedorId,
            'fecha_documento' => now()->toDateString(),
            'numero_documento' => 'F001-'.Str::upper(Str::random(5)),
            'serie' => 'F001',
            'moneda' => 'PEN',
            'lineas' => [
                [
                    'producto_id' => $productoId,
                    'cantidad' => '3',
                    'costo_unitario' => '12.50',
                ],
            ],
        ])
        ->assertRedirect();

    expect(InventarioScenario::stockEnSede($productoId, $sedeId))->toBe(13.0);

    $compra = Compra::query()->latest('created_at')->first();
    expect($compra)->not->toBeNull();
    expect($compra?->anulada_at)->toBeNull();

    $this->actingAs($this->admin)
        ->delete('http://'.$this->host.'/inventario/compras/'.$compra->id)
        ->assertRedirect();

    expect(InventarioScenario::stockEnSede($productoId, $sedeId))->toBe(10.0);
    expect($compra->fresh()?->anulada_at)->not->toBeNull();
});
