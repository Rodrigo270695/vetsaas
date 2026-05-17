<?php

use App\Models\Consulta;
use App\Models\HistoriaClinica;
use App\Models\ConsultaCargo;
use App\Models\Internamiento;
use App\Models\Paciente;
use App\Models\Propietario;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\Facades\Tenant as TenantContext;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\TenantMigrateTestGuards;

/**
 * CRUD de hospitalización (internamientos).
 *
 * Requiere PostgreSQL. Con SQLite en `.env.testing` los casos se omiten.
 */
beforeEach(function (): void {
    TenantMigrateTestGuards::guardIfUnsafePgsql($this);

    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Hospitalización tenant usa schemas PostgreSQL.');
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

    $this->slug = 'clin-hosp-'.Str::lower(Str::random(4));
    $this->schema = 'vet_test_'.Str::lower(Str::random(6));

    Artisan::call('vetsaas:tenant-migrate', [
        'schema' => $this->schema,
    ]);

    $this->tenant = Tenant::create([
        'slug' => $this->slug,
        'schema_name' => $this->schema,
        'razon_social' => 'Clínica Hosp Test',
        'nombre_comercial' => 'Hosp Test',
        'email_admin' => 'admin-hosp@test.local',
        'timezone' => 'America/Lima',
        'locale' => 'es',
        'estado' => 'active',
    ]);

    $this->admin = User::factory()->create([
        'email' => 'admin-hosp@test.local',
        'tenant_id' => $this->tenant->id,
        'password' => Hash::make('clave-admin'),
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $this->admin->assignRole('admin_clinica');

    $this->host = $this->slug.'.vetsaas.test';
});

afterEach(function (): void {
    TenantContext::forget();
});

function hospitalizacionActingAs(object $test, User $user): void
{
    $test->actingAs($user, 'web')
        ->withServerVariables(['HTTP_HOST' => $test->host]);
}

test('listado hospitalización requiere permiso y renderiza internamientos', function (): void {
    hospitalizacionActingAs($this, $this->admin);

    $prop = Propietario::query()->create([
        'nombres' => 'Ana',
        'apellidos' => 'Pérez',
        'tipo_documento' => 'dni',
        'numero_documento' => 'HOSP'.Str::random(6),
        'activo' => true,
    ]);
    $paciente = Paciente::query()->create([
        'propietario_id' => $prop->id,
        'nombre' => 'Firulais',
        'especie' => 'canino',
        'activo' => true,
    ]);

    Internamiento::query()->create([
        'paciente_id' => $paciente->id,
        'ingreso_at' => now(),
        'estado' => Internamiento::ESTADO_ACTIVO,
        'motivo_ingreso' => 'Observación post IQ',
        'created_by_id' => $this->admin->id,
        'updated_by_id' => $this->admin->id,
    ]);

    $this->get('http://'.$this->host.'/clinica/hospitalizacion')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clinica/hospitalizacion/index')
            ->has('internamientos.data', 1)
            ->where('internamientos.data.0.motivo_ingreso', 'Observación post IQ'));
});

test('puede registrar internamiento y dar de alta', function (): void {
    hospitalizacionActingAs($this, $this->admin);

    $prop = Propietario::query()->create([
        'nombres' => 'Luis',
        'apellidos' => 'Gómez',
        'tipo_documento' => 'dni',
        'numero_documento' => 'HOSP2'.Str::random(6),
        'activo' => true,
    ]);
    $paciente = Paciente::query()->create([
        'propietario_id' => $prop->id,
        'nombre' => 'Michi',
        'especie' => 'felino',
        'activo' => true,
    ]);

    $ingreso = now()->format('Y-m-d\TH:i');

    $this->post('http://'.$this->host.'/clinica/hospitalizacion', [
        'paciente_id' => $paciente->id,
        'ingreso_at' => $ingreso,
        'estado' => Internamiento::ESTADO_ACTIVO,
        'motivo_ingreso' => 'Gastroenteritis',
        'ubicacion' => 'Jaula 2',
    ])->assertRedirect();

    $internamiento = Internamiento::query()->where('paciente_id', $paciente->id)->first();
    expect($internamiento)->not->toBeNull()
        ->and($internamiento->estado)->toBe(Internamiento::ESTADO_ACTIVO)
        ->and($internamiento->ubicacion)->toBe('Jaula 2');

    $alta = now()->addDay()->format('Y-m-d\TH:i');

    $this->put('http://'.$this->host.'/clinica/hospitalizacion/'.$internamiento->id, [
        'paciente_id' => $paciente->id,
        'ingreso_at' => $ingreso,
        'alta_at' => $alta,
        'estado' => Internamiento::ESTADO_ALTA,
        'motivo_ingreso' => 'Gastroenteritis',
    ])->assertRedirect();

    $internamiento->refresh();
    expect($internamiento->estado)->toBe(Internamiento::ESTADO_ALTA)
        ->and($internamiento->alta_at)->not->toBeNull();
});

test('detalle permite registrar evolución con signos vitales', function (): void {
    hospitalizacionActingAs($this, $this->admin);

    $prop = Propietario::query()->create([
        'nombres' => 'Tom',
        'apellidos' => 'Lee',
        'tipo_documento' => 'dni',
        'numero_documento' => 'HOSP4'.Str::random(6),
        'activo' => true,
    ]);
    $paciente = Paciente::query()->create([
        'propietario_id' => $prop->id,
        'nombre' => 'Coco',
        'especie' => 'canino',
        'activo' => true,
    ]);

    $internamiento = Internamiento::query()->create([
        'paciente_id' => $paciente->id,
        'ingreso_at' => now(),
        'estado' => Internamiento::ESTADO_ACTIVO,
        'motivo_ingreso' => 'Parvovirus',
        'created_by_id' => $this->admin->id,
        'updated_by_id' => $this->admin->id,
    ]);

    $this->get('http://'.$this->host.'/clinica/hospitalizacion/'.$internamiento->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('clinica/hospitalizacion/show'));

    $this->post('http://'.$this->host.'/clinica/hospitalizacion/'.$internamiento->id.'/evoluciones', [
        'registrado_at' => now()->format('Y-m-d\TH:i'),
        'evolucion' => 'Mejoría del apetito',
        'peso_kg' => 12.5,
        'temperatura_c' => 38.5,
        'fc_lpm' => 90,
        'fr_rpm' => 24,
    ])->assertRedirect();

    $this->get('http://'.$this->host.'/clinica/hospitalizacion/'.$internamiento->id)
        ->assertInertia(fn (Assert $page) => $page
            ->has('internamiento.evoluciones', 1)
            ->where('internamiento.evoluciones.0.evolucion', 'Mejoría del apetito'));
});

test('cargos de internamiento sin consulta y cobro desde caja', function (): void {
    hospitalizacionActingAs($this, $this->admin);

    $prop = Propietario::query()->create([
        'nombres' => 'Cob',
        'apellidos' => 'Hosp',
        'tipo_documento' => 'dni',
        'numero_documento' => 'HOSP5'.Str::random(6),
        'activo' => true,
    ]);
    $paciente = Paciente::query()->create([
        'propietario_id' => $prop->id,
        'nombre' => 'Bolt',
        'especie' => 'canino',
        'activo' => true,
    ]);

    $internamiento = Internamiento::query()->create([
        'paciente_id' => $paciente->id,
        'ingreso_at' => now(),
        'estado' => Internamiento::ESTADO_ACTIVO,
        'motivo_ingreso' => 'Observación',
        'created_by_id' => $this->admin->id,
        'updated_by_id' => $this->admin->id,
    ]);

    $this->get('http://'.$this->host.'/clinica/hospitalizacion/'.$internamiento->id.'/cargos')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('clinica/hospitalizacion/cargos'));

    $this->put('http://'.$this->host.'/clinica/hospitalizacion/'.$internamiento->id.'/cargos', [
        'notas' => null,
        'lineas' => [
            [
                'tipo_linea' => 'servicio',
                'concepto' => 'Día hospitalización',
                'cantidad' => 2,
                'precio_unitario' => 80,
                'descuento_importe' => 0,
            ],
        ],
    ])->assertRedirect();

    $cargo = ConsultaCargo::query()->where('internamiento_id', $internamiento->id)->first();
    expect($cargo)->not->toBeNull();

    $this->post('http://'.$this->host.'/clinica/hospitalizacion/'.$internamiento->id.'/cargos/confirmar')
        ->assertRedirect();

    $cargo->refresh();
    expect($cargo->estado)->toBe(ConsultaCargo::ESTADO_CONFIRMADO);
});

test('historial paciente incluye vínculo de internamiento', function (): void {
    hospitalizacionActingAs($this, $this->admin);

    $prop = Propietario::query()->create([
        'nombres' => 'Eva',
        'apellidos' => 'Ruiz',
        'tipo_documento' => 'dni',
        'numero_documento' => 'HOSP3'.Str::random(6),
        'activo' => true,
    ]);
    $paciente = Paciente::query()->create([
        'propietario_id' => $prop->id,
        'nombre' => 'Rocky',
        'especie' => 'canino',
        'activo' => true,
    ]);
    $hc = HistoriaClinica::query()->create(['paciente_id' => $paciente->id]);
    $consulta = Consulta::query()->create([
        'historia_clinica_id' => $hc->id,
        'atendido_at' => now(),
        'motivo' => 'Control',
    ]);

    Internamiento::query()->create([
        'paciente_id' => $paciente->id,
        'consulta_id' => $consulta->id,
        'ingreso_at' => now(),
        'estado' => Internamiento::ESTADO_ACTIVO,
        'motivo_ingreso' => 'Monitoreo',
        'created_by_id' => $this->admin->id,
        'updated_by_id' => $this->admin->id,
    ]);

    $this->get('http://'.$this->host.'/clinica/pacientes/'.$paciente->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('timeline', 1)
            ->where('timeline.0.detalle.vinculos.internamientos.0.titulo', 'Monitoreo'));
});
