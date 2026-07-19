<?php

declare(strict_types=1);

use App\Models\ClinicSetting;
use App\Models\GroomingServicio;
use App\Models\GroomingServicioTarifa;
use App\Models\HotelEstanciaTarifa;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\TenantRolesSeeder;
use Tests\Support\TenantRbac;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\TenantMigrateTestGuards;

beforeEach(function (): void {
    TenantMigrateTestGuards::guardIfUnsafePgsql($this);

    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Tarifas servicios requiere PostgreSQL.');
    }

    config([
        'tenant.central_domains' => ['localhost', '127.0.0.1', 'vetsaas.test'],
        'tenant.root_domain' => 'vetsaas.test',
        'tenant.schema_prefix' => 'vet_',
        'tenant.allowed_states' => ['active', 'trial', 'grace'],
        'tenant.cache_ttl' => 0,
    ]);

    $this->seed(PermissionsSeeder::class);

    $this->slug = 'tarifas-'.Str::lower(Str::random(4));
    $this->schema = 'vet_test_'.Str::lower(Str::random(6));

    Artisan::call('vetsaas:tenant-migrate', ['schema' => $this->schema]);

    $this->tenant = Tenant::query()->create([
        'slug' => $this->slug,
        'schema_name' => $this->schema,
        'razon_social' => 'Clínica Tarifas Test',
        'nombre_comercial' => 'Tarifas Test',
        'email_admin' => 'tarifas-'.Str::lower(Str::random(6)).'@test.local',
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
    TenantRbac::seedAndAssign($this->admin);

    $this->host = $this->slug.'.vetsaas.test';
});

afterEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        return;
    }

    if (isset($this->schema)) {
        DB::statement('DROP SCHEMA IF EXISTS "'.$this->schema.'" CASCADE');
    }

    DB::statement('SET search_path TO public');

    if (isset($this->tenant)) {
        $this->tenant->forceDelete();
    }

    if (isset($this->admin)) {
        $this->admin->forceDelete();
    }
});

it('permite CRUD de tarifa grooming en configuración', function (): void {
    $this->actingAs($this->admin)
        ->get('http://'.$this->host.'/configuracion/tarifas')
        ->assertOk();

    $this->actingAs($this->admin)
        ->post('http://'.$this->host.'/configuracion/tarifas/grooming', [
            'servicio' => 'bano_higienico',
            'precio_lista' => '45.00',
            'moneda' => 'PEN',
            'activo' => true,
        ])
        ->assertRedirect();

    $tarifa = GroomingServicioTarifa::query()->where('servicio', 'bano_higienico')->first();
    expect($tarifa)->not->toBeNull();
    expect((float) (string) $tarifa?->precio_lista)->toBe(45.0);

    $this->actingAs($this->admin)
        ->put('http://'.$this->host.'/configuracion/tarifas/grooming/'.$tarifa->id, [
            'servicio' => 'bano_higienico',
            'precio_lista' => '50.00',
            'moneda' => 'PEN',
            'activo' => true,
        ])
        ->assertRedirect();

    expect((float) (string) $tarifa->fresh()?->precio_lista)->toBe(50.0);

    $this->actingAs($this->admin)
        ->delete('http://'.$this->host.'/configuracion/tarifas/grooming/'.$tarifa->id)
        ->assertRedirect();

    expect(GroomingServicioTarifa::query()->whereKey($tarifa->id)->exists())->toBeFalse();
});

it('permite CRUD de servicio grooming personalizado en configuración', function (): void {
    ClinicSetting::query()->update(['grooming_catalogo_personalizado' => true]);

    $this->actingAs($this->admin)
        ->post('http://'.$this->host.'/configuracion/tarifas/grooming', [
            'nombre' => 'Baño premium',
            'categoria' => 'Baños',
            'precio_lista' => '55.00',
            'moneda' => 'PEN',
            'duracion_minutos' => 75,
            'activo' => true,
        ])
        ->assertRedirect();

    $servicio = GroomingServicio::query()->where('nombre', 'Baño premium')->first();
    expect($servicio)->not->toBeNull();
    expect((float) (string) $servicio?->precio_lista)->toBe(55.0);

    $this->actingAs($this->admin)
        ->put('http://'.$this->host.'/configuracion/tarifas/grooming/'.$servicio->id, [
            'nombre' => 'Baño premium plus',
            'categoria' => 'Baños',
            'precio_lista' => '60.00',
            'moneda' => 'PEN',
            'duracion_minutos' => 80,
            'activo' => true,
        ])
        ->assertRedirect();

    expect($servicio->fresh()?->nombre)->toBe('Baño premium plus');
    expect((float) (string) $servicio->fresh()?->precio_lista)->toBe(60.0);

    $this->actingAs($this->admin)
        ->delete('http://'.$this->host.'/configuracion/tarifas/grooming/'.$servicio->id)
        ->assertRedirect();

    expect(GroomingServicio::query()->whereKey($servicio->id)->exists())->toBeFalse();
});

it('permite CRUD de tarifa hotel en configuración', function (): void {
    $this->actingAs($this->admin)
        ->post('http://'.$this->host.'/configuracion/tarifas/hotel', [
            'tipo_estancia' => 'guarderia_dia',
            'precio_lista' => '80.00',
            'moneda' => 'PEN',
            'activo' => true,
        ])
        ->assertRedirect();

    $tarifa = HotelEstanciaTarifa::query()->where('tipo_estancia', 'guarderia_dia')->first();
    expect($tarifa)->not->toBeNull();

    $this->actingAs($this->admin)
        ->delete('http://'.$this->host.'/configuracion/tarifas/hotel/'.$tarifa->id)
        ->assertRedirect();

    expect(HotelEstanciaTarifa::query()->whereKey($tarifa->id)->exists())->toBeFalse();
});
