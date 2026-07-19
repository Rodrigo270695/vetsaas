<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\Tenant;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Aislamiento de roles por tenant requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
});

afterEach(function (): void {
    if (isset($this->otherTenant)) {
        $this->otherTenant->forceDelete();
    }

    $this->tearDownTestTenant();
});

it('cambia permisos de admin_clinica solo en el tenant actual', function (): void {
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

    (new TenantRolesSeeder)->seedForTenant((string) $this->otherTenant->id);

    $roleA = Role::query()
        ->where('tenant_id', $this->testTenant->id)
        ->where('name', 'admin_clinica')
        ->firstOrFail();

    $roleB = Role::query()
        ->where('tenant_id', $this->otherTenant->id)
        ->where('name', 'admin_clinica')
        ->firstOrFail();

    expect($roleA->id)->not->toBe($roleB->id);

    $permsBBefore = $roleB->permissions()->pluck('name')->sort()->values()->all();

    $this->actingAs($this->testTenantAdmin);

    $response = $this->put(
        'http://'.$this->testTenantHost.'/configuracion/roles/'.$roleA->id.'/permissions',
        ['permissions' => ['dashboard.view', 'pacientes.view']],
    );

    $response->assertRedirect();

    $roleA->refresh();
    $roleB->refresh();

    expect($roleA->permissions()->pluck('name')->sort()->values()->all())
        ->toBe(['dashboard.view', 'pacientes.view']);

    expect($roleB->permissions()->pluck('name')->sort()->values()->all())
        ->toBe($permsBBefore);
});

it('no lista roles de otro tenant en el catálogo de la clínica', function (): void {
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

    $prev = getPermissionsTeamId();
    setPermissionsTeamId((string) $this->otherTenant->id);
    try {
        Role::query()->create([
            'name' => 'custom_solo_otra_'.Str::lower(Str::random(4)),
            'guard_name' => 'web',
            'tenant_id' => $this->otherTenant->id,
            'description' => 'Solo otra clínica',
        ]);
    } finally {
        setPermissionsTeamId($prev);
    }

    $this->actingAs($this->testTenantAdmin);

    $response = $this->get('http://'.$this->testTenantHost.'/configuracion/roles');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('roles.data', function ($roles): bool {
            foreach ($roles as $role) {
                if (str_starts_with((string) $role['name'], 'custom_solo_otra_')) {
                    return false;
                }
            }

            return true;
        })
    );
});
