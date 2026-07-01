<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Paciente;
use App\Models\Propietario;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Tenancy\TenantManager;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Auditoría tenant requiere PostgreSQL (schemas).');
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

    $this->slug = 'audit-'.Str::lower(Str::random(4));
    $this->schema = 'vet_test_'.Str::lower(Str::random(6));

    Artisan::call('vetsaas:tenant-migrate', [
        'schema' => $this->schema,
    ]);

    $this->tenant = Tenant::query()->create([
        'slug' => $this->slug,
        'schema_name' => $this->schema,
        'razon_social' => 'Clínica Audit Test',
        'nombre_comercial' => 'Audit Vet',
        'email_admin' => 'admin-audit@test.local',
        'timezone' => 'America/Lima',
        'locale' => 'es',
        'estado' => 'active',
    ]);

    $this->admin = User::factory()->create([
        'email' => 'admin-audit@test.local',
        'tenant_id' => $this->tenant->id,
        'password' => Hash::make('password'),
        'is_active' => true,
        'must_change_password' => false,
        'email_verified_at' => now(),
    ]);
    $this->admin->assignRole('admin_clinica');

    $this->host = $this->slug.'.vetsaas.test';
});

afterEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        return;
    }

    if (isset($this->tenant)) {
        $this->tenant->forceDelete();
    }

    if (isset($this->schema)) {
        DB::statement('DROP SCHEMA IF EXISTS "'.$this->schema.'" CASCADE');
    }

    DB::statement('SET search_path TO public');
});

it('registra creación de paciente vía observer', function (): void {
    $this->actingAs($this->admin);

    app(TenantManager::class)->runForSlug($this->slug, function (): void {
        $propietario = Propietario::query()->create([
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
            'activo' => true,
            'created_by_id' => $this->admin->id,
        ]);

        Paciente::query()->create([
            'propietario_id' => $propietario->id,
            'nombre' => 'Firulais',
            'especie' => 'canino',
            'activo' => true,
            'created_by_id' => $this->admin->id,
        ]);

        expect(AuditLog::query()->where('accion', AuditLog::ACCION_CREATED)->where('modulo', 'pacientes')->count())
            ->toBe(1);
    });
});

it('muestra el listado de auditoría al admin de la clínica', function (): void {
    app(TenantManager::class)->runForSlug($this->slug, function (): void {
        AuditLogger::log(
            accion: AuditLog::ACCION_EXPORTED,
            modulo: 'pacientes',
            registroLabel: 'pacientes-test.xlsx',
        );
    });

    $response = $this->actingAs($this->admin)
        ->get('http://'.$this->host.'/auditoria/logs');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('auditoria/logs/index')
        ->has('logs.data', 1)
        ->where('logs.data.0.accion', AuditLog::ACCION_EXPORTED)
    );
});

it('deniega acceso sin permiso auditoria-logs.view', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
        'must_change_password' => false,
        'email_verified_at' => now(),
    ]);
    $user->assignRole('recepcionista');

    $response = $this->actingAs($user)
        ->get('http://'.$this->host.'/auditoria/logs');

    $response->assertForbidden();
});
