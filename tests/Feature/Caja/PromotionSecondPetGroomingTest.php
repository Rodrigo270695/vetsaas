<?php

declare(strict_types=1);

use App\Models\GroomingTurno;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Venta;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\CajaConsultaCargoScenario;
use Tests\Support\TenantMigrateTestGuards;

beforeEach(function (): void {
    TenantMigrateTestGuards::guardIfUnsafePgsql($this);

    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Promociones requiere PostgreSQL.');
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

    $this->slug = 'promo-'.Str::lower(Str::random(4));
    $this->schema = 'vet_test_'.Str::lower(Str::random(6));

    Artisan::call('vetsaas:tenant-migrate', ['schema' => $this->schema]);

    $this->tenant = Tenant::query()->create([
        'slug' => $this->slug,
        'schema_name' => $this->schema,
        'razon_social' => 'Clínica Promo Test',
        'nombre_comercial' => 'Promo Test',
        'email_admin' => 'promo-'.Str::lower(Str::random(6)).'@test.local',
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

it('aplica 50% en segunda mascota grooming el mismo día', function (): void {
    $this->actingAs($this->cajero);

    $promo = Promotion::query()->where('condition_type', Promotion::CONDITION_SECOND_PET_GROOMING)->first();
    expect($promo)->not->toBeNull();

    $turno1 = GroomingTurno::query()->create([
        'paciente_id' => $this->scenario['paciente']->id,
        'responsable_id' => $this->cajero->id,
        'sede_id' => $this->scenario['sede']->id,
        'inicio_at' => now()->subHours(2),
        'duracion_minutos' => 60,
        'estado' => GroomingTurno::ESTADO_COMPLETADA,
        'servicio' => 'bano',
        'created_by_id' => $this->cajero->id,
    ]);

    $this->post('http://'.$this->host.'/caja/ventas', [
        'caja_sesion_id' => $this->scenario['sesion']->id,
        'propietario_id' => $this->scenario['propietario']->id,
        'paciente_id' => $this->scenario['paciente']->id,
        'grooming_turno_id' => $turno1->id,
        'lineas' => [[
            'concepto' => 'Baño grooming',
            'precio_lista' => '100.00',
            'tipo_linea' => 'servicio',
            'cantidad' => '1',
        ]],
        'metodo_pago' => 'yape',
    ])->assertRedirect();

    $venta1 = Venta::query()->latest('created_at')->first();
    expect($venta1)->not->toBeNull();
    expect((float) $venta1->descuento_monto)->toBe(0.0);
    expect((float) $venta1->total)->toBe(100.0);

    $turno1->refresh();
    expect($turno1->venta_id)->toBe($venta1->id);

    $paciente2 = \App\Models\Paciente::query()->create([
        'propietario_id' => $this->scenario['propietario']->id,
        'nombre' => 'Mascota 2',
        'especie' => 'canino',
        'activo' => true,
    ]);

    $turno2 = GroomingTurno::query()->create([
        'paciente_id' => $paciente2->id,
        'responsable_id' => $this->cajero->id,
        'sede_id' => $this->scenario['sede']->id,
        'inicio_at' => now()->subHour(),
        'duracion_minutos' => 60,
        'estado' => GroomingTurno::ESTADO_COMPLETADA,
        'servicio' => 'bano',
        'created_by_id' => $this->cajero->id,
    ]);

    $this->post('http://'.$this->host.'/caja/ventas', [
        'caja_sesion_id' => $this->scenario['sesion']->id,
        'propietario_id' => $this->scenario['propietario']->id,
        'paciente_id' => $paciente2->id,
        'grooming_turno_id' => $turno2->id,
        'lineas' => [[
            'concepto' => 'Baño grooming 2',
            'precio_lista' => '100.00',
            'tipo_linea' => 'servicio',
            'cantidad' => '1',
        ]],
        'metodo_pago' => 'yape',
    ])->assertRedirect();

    $venta2 = Venta::query()->whereKeyNot($venta1->id)->latest('created_at')->first();
    expect($venta2)->not->toBeNull();
    expect((float) $venta2->descuento_monto)->toBe(50.0);
    expect((float) $venta2->total)->toBe(50.0);
    expect($venta2->promotion_id)->toBe($promo->id);
});
