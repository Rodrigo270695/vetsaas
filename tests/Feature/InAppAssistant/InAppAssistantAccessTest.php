<?php

declare(strict_types=1);

use App\Models\Role;
use App\Services\InAppAssistant\InAppAssistantToolExecutor;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('La autorización tenant del asistente requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('permite el asistente al rol tenant autorizado', function (): void {
    $this->actingAs($this->testTenantAdmin);

    $this->getJson('http://'.$this->testTenantHost.'/asistente/status')
        ->assertOk()
        ->assertJsonPath('scope', 'clinic');
});

it('bloquea endpoints y oculta el asistente sin el permiso de uso', function (): void {
    $role = Role::query()
        ->where('tenant_id', $this->testTenant->id)
        ->where('name', 'admin_clinica')
        ->firstOrFail();
    $role->revokePermissionTo('in-app-assistant.use');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->testTenantAdmin->unsetRelation('roles')->unsetRelation('permissions');

    $this->actingAs($this->testTenantAdmin);

    $this->getJson('http://'.$this->testTenantHost.'/asistente/status')
        ->assertForbidden();

    $this->get('http://'.$this->testTenantHost.'/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('in_app_assistant', null));
});

it('bloquea el CRUD global de conocimiento desde un tenant aun con permiso directo', function (): void {
    $role = Role::query()
        ->where('tenant_id', $this->testTenant->id)
        ->where('name', 'admin_clinica')
        ->firstOrFail();
    $role->givePermissionTo('in-app-assistant-knowledge.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->testTenantAdmin->unsetRelation('roles')->unsetRelation('permissions');

    $this->actingAs($this->testTenantAdmin)
        ->get('http://'.$this->testTenantHost.'/plataforma/in-app-assistant-knowledge')
        ->assertNotFound();
});

it('ejecuta quien atiende hoy sin invocar un método inexistente', function (): void {
    $executor = new InAppAssistantToolExecutor;
    $executor->setUser($this->testTenantAdmin);
    $executor->setPageContext(['scope' => 'clinic']);

    $result = json_decode($executor->execute('quien_atiende_hoy', ['fecha' => 'hoy']), true);

    expect($result)->toBeArray()
        ->and($result['ok'] ?? false)->toBeTrue()
        ->and($result)->toHaveKeys(['fecha', 'items']);
});
