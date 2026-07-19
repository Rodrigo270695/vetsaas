<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\ClinicAdminScope;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Alcance usuarios/roles tenant requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();

    config([
        'platform.superadmin.email' => 'superadmin-seed@vetsaas.test',
        'platform.superadmin.password' => 'password',
        'platform.superadmin.name' => 'Super Administrador',
    ]);
    $this->seed(SuperadminSeeder::class);

    $this->createTestTenantWithSchema();

    $plan = Plan::query()->create([
        'codigo' => 'TEST-SCOPE-'.Str::upper(Str::random(4)),
        'nombre' => 'Plan scope',
        'descripcion' => null,
        'precio_mensual' => '0.00',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => 997,
        'es_publico' => false,
        'activo' => true,
    ]);

    Subscription::query()->create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->testTenant->id,
        'plan_id' => $plan->id,
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

    $this->centralSuperadmin = User::query()
        ->where('email', 'superadmin-seed@vetsaas.test')
        ->firstOrFail();
});

afterEach(function (): void {
    if (isset($this->otherTenant)) {
        $this->otherTenant->forceDelete();
    }

    $this->tearDownTestTenant();
});

it('no lista superadmin ni usuarios de otras clínicas en el subdominio del tenant', function (): void {
    $this->otherTenant = Tenant::query()->create([
        'slug' => 'otra-'.Str::lower(Str::random(4)),
        'schema_name' => 'vet_otra_'.Str::lower(Str::random(4)),
        'razon_social' => 'Otra clínica',
        'nombre_comercial' => 'Otra',
        'email_admin' => 'otra@test.local',
        'timezone' => 'America/Lima',
        'locale' => 'es',
        'estado' => 'active',
    ]);

    $otherTenantAdmin = User::factory()->create([
        'email' => 'otra-clinica-'.Str::random(4).'@test.local',
        'tenant_id' => $this->otherTenant->id,
        'password' => Hash::make('password'),
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
    $otherTenantAdmin->assignRole('admin_clinica');

    $this->actingAs($this->testTenantAdmin);

    $response = $this->get('http://'.$this->testTenantHost.'/configuracion/usuarios');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('configuracion/usuarios/index')
        ->has('users.data', 1)
        ->where('users.data.0.email', $this->testTenantAdmin->email)
    );
});

it('no muestra el rol superadmin en el catálogo de roles del tenant', function (): void {
    $this->actingAs($this->testTenantAdmin);

    $response = $this->get('http://'.$this->testTenantHost.'/configuracion/roles');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('configuracion/roles/index')
        ->where('roles.data', function ($roles): bool {
            $names = collect($roles)->pluck('name')->all();

            return ! in_array('superadmin', $names, true);
        })
    );
});

it('rechaza asignar rol superadmin al crear usuario en clínica', function (): void {
    $this->actingAs($this->testTenantAdmin);

    $response = $this->post('http://'.$this->testTenantHost.'/configuracion/usuarios', [
        'name' => 'Intento Super',
        'email' => 'intento-super-'.Str::random(4).'@test.local',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'is_active' => true,
        'role' => 'superadmin',
    ]);

    $response->assertSessionHasErrors('role');
});

it('filtra permisos de plataforma del catálogo de roles en clínica', function (): void {
    $this->actingAs($this->testTenantAdmin);

    $response = $this->get('http://'.$this->testTenantHost.'/configuracion/roles');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('permissions_catalog', function ($catalog): bool {
            $allNames = collect($catalog)
                ->flatMap(fn ($g) => collect($g['permissions'])->pluck('name'))
                ->all();

            foreach ($allNames as $name) {
                if (! ClinicAdminScope::isTenantAssignablePermission($name)) {
                    return false;
                }
            }

            return count($allNames) > 0;
        })
    );
});

it('marca admin_clinica como rol protegido y lo mantiene visible en clínica', function (): void {
    $this->actingAs($this->testTenantAdmin);

    $response = $this->get('http://'.$this->testTenantHost.'/configuracion/roles');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('roles.data', function ($roles): bool {
            $admin = collect($roles)->firstWhere('name', 'admin_clinica');

            return $admin !== null && ($admin['is_system'] ?? false) === true;
        })
    );
});

it('rechaza eliminar un rol base de clínica', function (): void {
    $this->actingAs($this->testTenantAdmin);

    $role = Role::query()->where('name', 'admin_clinica')->firstOrFail();

    $response = $this->delete('http://'.$this->testTenantHost.'/configuracion/roles/'.$role->id);

    $response->assertSessionHasErrors('name');
    expect(Role::query()->where('name', 'admin_clinica')->exists())->toBeTrue();
});

it('rechaza vaciar permisos de un rol base de clínica', function (): void {
    $this->actingAs($this->testTenantAdmin);

    $role = Role::query()->where('name', 'admin_clinica')->firstOrFail();
    expect($role->permissions()->count())->toBeGreaterThan(0);

    $response = $this->put(
        'http://'.$this->testTenantHost.'/configuracion/roles/'.$role->id.'/permissions',
        ['permissions' => []],
    );

    $response->assertSessionHasErrors('permissions');
    expect($role->fresh()->permissions()->count())->toBeGreaterThan(0);
});

it('omite roles base al intentar borrado masivo', function (): void {
    $this->actingAs($this->testTenantAdmin);

    $custom = Role::query()->create([
        'name' => 'custom_scope_'.Str::lower(Str::random(4)),
        'guard_name' => 'web',
        'description' => 'Temporal',
    ]);
    $admin = Role::query()->where('name', 'admin_clinica')->firstOrFail();

    $response = $this->delete('http://'.$this->testTenantHost.'/configuracion/roles/bulk', [
        'ids' => [$custom->id, $admin->id],
    ]);

    $response->assertRedirect();
    expect(Role::query()->whereKey($custom->id)->exists())->toBeFalse();
    expect(Role::query()->where('name', 'admin_clinica')->exists())->toBeTrue();
});
