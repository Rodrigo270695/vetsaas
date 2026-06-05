<?php

declare(strict_types=1);

use App\Models\Paciente;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Producto;
use App\Models\Propietario;
use App\Models\Sede;
use App\Models\User;
use App\Models\Subscription;
use App\Support\Plan\PlanLimits;
use App\Tenancy\Facades\Tenant as TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Límites de plan con schema tenant requieren PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();

    $this->plan = Plan::query()->create([
        'codigo' => 'TEST-LIMITS-'.Str::upper(Str::random(6)),
        'nombre' => 'Plan límites test',
        'descripcion' => null,
        'precio_mensual' => '0.00',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => 998,
        'es_publico' => false,
        'activo' => true,
    ]);

    PlanFeature::query()->create([
        'plan_id' => $this->plan->id,
        'feature' => 'max_propietarios',
        'valor_int' => 1,
        'valor_bool' => null,
        'valor_str' => null,
    ]);

    Subscription::query()->create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->testTenant->id,
        'plan_id' => $this->plan->id,
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
    ]);
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('rechaza crear un propietario cuando se alcanza max_propietarios del plan', function (): void {
    TenantContext::runForSlug($this->testTenantSlug, function (): void {
        Propietario::query()->create([
            'nombres' => 'Dueño',
            'apellidos' => 'Uno',
            'activo' => true,
        ]);
    });

    TenantContext::runForSlug($this->testTenantSlug, function (): void {
        expect(PlanLimits::intLimit($this->testTenant, 'max_propietarios'))->toBe(1)
            ->and(PlanLimits::currentCount('max_propietarios'))->toBe(1)
            ->and(PlanLimits::wouldExceed('max_propietarios'))->toBeTrue();
    });

    $this->actingAs($this->testTenantAdmin);

    $response = $this->post('http://'.$this->testTenantHost.'/clinica/propietarios', [
        'nombres' => 'Dueño',
        'apellidos' => 'Dos',
        'activo' => true,
    ]);

    $response->assertSessionHasErrors('plan_limit');

    TenantContext::runForSlug($this->testTenantSlug, function (): void {
        expect(Propietario::query()->count())->toBe(1);
    });
});

it('permite crear el primer propietario dentro del límite', function (): void {
    $this->actingAs($this->testTenantAdmin);

    $response = $this->post('http://'.$this->testTenantHost.'/clinica/propietarios', [
        'nombres' => 'Dueño',
        'apellidos' => 'Uno',
        'activo' => true,
    ]);

    $response->assertSessionHasNoErrors();

    TenantContext::runForSlug($this->testTenantSlug, function (): void {
        expect(Propietario::query()->count())->toBe(1);
    });
});

it('rechaza crear una sede cuando se alcanza max_sedes del plan', function (): void {
    PlanFeature::query()->create([
        'plan_id' => $this->plan->id,
        'feature' => 'max_sedes',
        'valor_int' => 1,
        'valor_bool' => null,
        'valor_str' => null,
    ]);

    Sede::query()->create([
        'tenant_id' => $this->testTenant->id,
        'nombre' => 'Sede única',
        'codigo' => 'SED-01',
        'activa' => true,
    ]);

    TenantContext::runForSlug($this->testTenantSlug, function (): void {
        expect(PlanLimits::intLimit($this->testTenant, 'max_sedes'))->toBe(1)
            ->and(PlanLimits::currentCount('max_sedes'))->toBe(1)
            ->and(PlanLimits::wouldExceed('max_sedes'))->toBeTrue();
    });

    $this->actingAs($this->testTenantAdmin);

    $response = $this->post('http://'.$this->testTenantHost.'/configuracion/sedes', [
        'nombre' => 'Segunda sede',
        'activa' => true,
    ]);

    $response->assertSessionHasErrors('plan_limit');

    expect(Sede::query()->where('tenant_id', $this->testTenant->id)->count())->toBe(1);
});

it('rechaza crear un paciente cuando se alcanza max_pacientes del plan', function (): void {
    PlanFeature::query()->create([
        'plan_id' => $this->plan->id,
        'feature' => 'max_pacientes',
        'valor_int' => 1,
        'valor_bool' => null,
        'valor_str' => null,
    ]);

    $propietarioId = null;

    TenantContext::runForSlug($this->testTenantSlug, function () use (&$propietarioId): void {
        $propietario = Propietario::query()->create([
            'nombres' => 'Dueño',
            'apellidos' => 'Test',
            'activo' => true,
        ]);

        $propietarioId = $propietario->id;

        Paciente::query()->create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Firulais',
            'activo' => true,
        ]);
    });

    TenantContext::runForSlug($this->testTenantSlug, function (): void {
        expect(PlanLimits::intLimit($this->testTenant, 'max_pacientes'))->toBe(1)
            ->and(PlanLimits::currentCount('max_pacientes'))->toBe(1)
            ->and(PlanLimits::wouldExceed('max_pacientes'))->toBeTrue();
    });

    $this->actingAs($this->testTenantAdmin);

    $response = $this->post('http://'.$this->testTenantHost.'/clinica/pacientes', [
        'propietario_id' => $propietarioId,
        'nombre' => 'Michi',
        'activo' => true,
    ]);

    $response->assertSessionHasErrors('plan_limit');

    TenantContext::runForSlug($this->testTenantSlug, function (): void {
        expect(Paciente::query()->count())->toBe(1);
    });
});

it('rechaza crear un usuario cuando se alcanza max_usuarios del plan', function (): void {
    PlanFeature::query()->create([
        'plan_id' => $this->plan->id,
        'feature' => 'max_usuarios',
        'valor_int' => 1,
        'valor_bool' => null,
        'valor_str' => null,
    ]);

    TenantContext::runForSlug($this->testTenantSlug, function (): void {
        expect(PlanLimits::intLimit($this->testTenant, 'max_usuarios'))->toBe(1)
            ->and(PlanLimits::currentCount('max_usuarios'))->toBe(1)
            ->and(PlanLimits::wouldExceed('max_usuarios'))->toBeTrue();
    });

    $this->actingAs($this->testTenantAdmin);

    $response = $this->post('http://'.$this->testTenantHost.'/configuracion/usuarios', [
        'name' => 'Recepción Dos',
        'email' => 'recepcion-dos-'.Str::random(4).'@test.local',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'is_active' => true,
        'role' => 'recepcionista',
    ]);

    $response->assertSessionHasErrors('plan_limit');

    expect(User::query()->where('tenant_id', $this->testTenant->id)->count())->toBe(1);
});

it('rechaza crear un producto cuando se alcanza max_productos del plan', function (): void {
    PlanFeature::query()->create([
        'plan_id' => $this->plan->id,
        'feature' => 'max_productos',
        'valor_int' => 1,
        'valor_bool' => null,
        'valor_str' => null,
    ]);

    TenantContext::runForSlug($this->testTenantSlug, function (): void {
        Producto::query()->create([
            'nombre' => 'Vacuna antirrábica',
            'unidad' => 'UND',
            'activo' => true,
        ]);
    });

    TenantContext::runForSlug($this->testTenantSlug, function (): void {
        expect(PlanLimits::intLimit($this->testTenant, 'max_productos'))->toBe(1)
            ->and(PlanLimits::currentCount('max_productos'))->toBe(1)
            ->and(PlanLimits::wouldExceed('max_productos'))->toBeTrue();
    });

    $this->actingAs($this->testTenantAdmin);

    $response = $this->post('http://'.$this->testTenantHost.'/inventario/productos', [
        'nombre' => 'Desparasitante',
        'unidad' => 'UND',
        'activo' => true,
    ]);

    $response->assertSessionHasErrors('plan_limit');

    TenantContext::runForSlug($this->testTenantSlug, function (): void {
        expect(Producto::query()->count())->toBe(1);
    });
});

it('trata valor_int -1 como ilimitado', function (): void {
    PlanFeature::query()
        ->where('plan_id', $this->plan->id)
        ->where('feature', 'max_propietarios')
        ->update(['valor_int' => -1]);

    TenantContext::runForSlug($this->testTenantSlug, function (): void {
        Propietario::query()->create([
            'nombres' => 'Dueño',
            'apellidos' => 'Uno',
            'activo' => true,
        ]);

        expect(PlanLimits::intLimit($this->testTenant, 'max_propietarios'))->toBeNull()
            ->and(PlanLimits::wouldExceed('max_propietarios'))->toBeFalse();
    });

    $this->actingAs($this->testTenantAdmin);

    $response = $this->post('http://'.$this->testTenantHost.'/clinica/propietarios', [
        'nombres' => 'Dueño',
        'apellidos' => 'Dos',
        'activo' => true,
    ]);

    $response->assertSessionHasNoErrors();

    TenantContext::runForSlug($this->testTenantSlug, function (): void {
        expect(Propietario::query()->count())->toBe(2);
    });
});
