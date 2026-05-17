<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Propietario;
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
