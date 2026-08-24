<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\RecordatorioTemplate;
use App\Models\Subscription;
use App\Support\Notifications\RecordatorioTemplateCatalog;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Plantillas requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
    $this->superadmin = $this->createTestSuperadmin();

    $this->plan = Plan::query()->create([
        'codigo' => 'starter',
        'nombre' => 'Starter',
        'descripcion' => null,
        'precio_mensual' => '39.90',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => 10,
        'es_publico' => true,
        'activo' => true,
    ]);

    $this->subscription = Subscription::withoutEvents(function (): Subscription {
        return Subscription::query()->create([
            'tenant_id' => $this->testTenant->id,
            'plan_id' => $this->plan->id,
            'estado' => 'active',
            'ciclo' => 'mensual',
            'precio_pactado' => '39.90',
        ]);
    });
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('lista plantillas sembradas con el mensaje por defecto', function (): void {
    $this->actingAs($this->testTenantAdmin)
        ->get('http://'.$this->testTenantHost.'/comunicaciones/plantillas')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('comunicaciones/plantillas/index')
            ->has('groups.citas')
            ->has('groups.vacunas')
            ->has('groups.grooming')
            ->has('groups.hotel'));

    expect(RecordatorioTemplate::query()->count())
        ->toBe(count(RecordatorioTemplateCatalog::definitions()));
});

it('actualiza el cuerpo de una plantilla', function (): void {
    RecordatorioTemplateCatalog::ensureSeeded();
    $plantilla = RecordatorioTemplate::query()->where('tipo', 'cita_creada')->firstOrFail();

    $this->actingAs($this->testTenantAdmin)
        ->put('http://'.$this->testTenantHost.'/comunicaciones/plantillas/'.$plantilla->id, [
            'cuerpo' => 'Hola {{propietario}}, cita de {{mascota}} en {{clinica}} el {{fecha}}.',
            'activo' => true,
        ])
        ->assertRedirect();

    expect($plantilla->fresh()->cuerpo)
        ->toContain('Hola {{propietario}}');
});

it('restaura el texto de fábrica', function (): void {
    RecordatorioTemplateCatalog::ensureSeeded();
    $plantilla = RecordatorioTemplate::query()->where('tipo', 'cita_2h')->firstOrFail();
    $default = RecordatorioTemplateCatalog::defaultBody('cita_2h');

    $plantilla->update(['cuerpo' => 'TEXTO TEMPORAL {{propietario}}']);

    $this->actingAs($this->testTenantAdmin)
        ->post('http://'.$this->testTenantHost.'/comunicaciones/plantillas/'.$plantilla->id.'/restaurar')
        ->assertRedirect();

    expect($plantilla->fresh()->cuerpo)->toBe($default)
        ->and($plantilla->fresh()->activo)->toBeTrue();
});

it('el builder usa el cuerpo personalizado y cae a fábrica si está inactiva', function (): void {
    RecordatorioTemplateCatalog::ensureSeeded();

    RecordatorioTemplate::query()->where('tipo', 'cita_creada')->update([
        'cuerpo' => 'CUSTOM {{propietario}} / {{mascota}} @ {{clinica}}',
        'activo' => true,
    ]);

    $builder = new \App\Services\Notifications\ReminderMessageBuilder;
    $inicio = \Carbon\Carbon::parse('2026-08-20 09:00:00', config('app.timezone'));

    expect($builder->citaCreada('OpenVet', 'Jairo', 'Kaizer', $inicio, null))
        ->toBe('CUSTOM Jairo / Kaizer @ OpenVet');

    RecordatorioTemplate::query()->where('tipo', 'cita_creada')->update([
        'activo' => false,
    ]);

    expect($builder->citaCreada('OpenVet', 'Jairo', 'Kaizer', $inicio, null))
        ->toContain('Registramos la cita de *Kaizer*')
        ->and($builder->citaCreada('OpenVet', 'Jairo', 'Kaizer', $inicio, null))
        ->not->toContain('CUSTOM');
});
