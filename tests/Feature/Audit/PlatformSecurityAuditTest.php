<?php

declare(strict_types=1);

use App\Models\PlatformSecurityAuditLog;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Auditoría de seguridad requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('registra borrado de rol personalizado en auditoría de plataforma', function (): void {
    $custom = Role::query()->create([
        'name' => 'custom_audit_'.strtolower(Str::random(4)),
        'guard_name' => 'web',
        'description' => 'Temporal',
    ]);

    $this->actingAs($this->testTenantAdmin);

    $response = $this->delete(
        'http://'.$this->testTenantHost.'/configuracion/roles/'.$custom->id,
    );

    $response->assertRedirect();

    expect(PlatformSecurityAuditLog::query()
        ->where('action', 'roles.deleted')
        ->where('subject_label', $custom->name)
        ->exists())->toBeTrue();
});

it('registra intento de bulk delete solo de roles protegidos', function (): void {
    $admin = Role::query()->where('name', 'admin_clinica')->firstOrFail();

    $this->actingAs($this->testTenantAdmin);

    $response = $this->delete(
        'http://'.$this->testTenantHost.'/configuracion/roles/bulk',
        ['ids' => [$admin->id]],
    );

    $response->assertRedirect();

    expect(PlatformSecurityAuditLog::query()
        ->where('action', 'roles.bulk_deleted_blocked')
        ->where('actor_email', $this->testTenantAdmin->email)
        ->exists())->toBeTrue();
});
