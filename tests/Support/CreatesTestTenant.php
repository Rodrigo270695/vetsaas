<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Arranque mínimo de un tenant con schema migrado para tests Feature en PostgreSQL.
 */
trait CreatesTestTenant
{
    protected Tenant $testTenant;

    protected string $testTenantSlug;

    protected string $testTenantSchema;

    protected string $testTenantHost;

    protected User $testTenantAdmin;

    protected function configureTenancyForTests(): void
    {
        config([
            'tenant.central_domains' => ['localhost', '127.0.0.1', 'vetsaas.test'],
            'tenant.root_domain' => 'vetsaas.test',
            'tenant.schema_prefix' => 'vet_',
            'tenant.allowed_states' => ['active', 'trial', 'grace'],
            'tenant.cache_ttl' => 0,
            'app.url' => 'http://127.0.0.1:8000',
        ]);
    }

    protected function createTestTenantWithSchema(): void
    {
        $this->testTenantSlug = 't-'.Str::lower(Str::random(6));
        $this->testTenantSchema = 'vet_test_'.Str::lower(Str::random(6));
        $this->testTenantHost = $this->testTenantSlug.'.vetsaas.test';

        Artisan::call('vetsaas:tenant-migrate', [
            'schema' => $this->testTenantSchema,
        ]);

        $this->testTenant = Tenant::query()->create([
            'slug' => $this->testTenantSlug,
            'schema_name' => $this->testTenantSchema,
            'razon_social' => 'Clínica Test',
            'nombre_comercial' => 'Test Clinic',
            'email_admin' => 'admin-'.$this->testTenantSlug.'@test.local',
            'timezone' => 'America/Lima',
            'locale' => 'es',
            'estado' => 'active',
        ]);

        $this->testTenantAdmin = User::factory()->create([
            'email' => 'admin-'.$this->testTenantSlug.'@test.local',
            'tenant_id' => $this->testTenant->id,
            'password' => Hash::make('password'),
            'is_active' => true,
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);
        $this->testTenantAdmin->assignRole('admin_clinica');
    }

    protected function seedPermissionsAndRoles(): void
    {
        $this->seed(PermissionsSeeder::class);
        $this->seed(TenantRolesSeeder::class);
    }

    protected function tearDownTestTenant(): void
    {
        if (! isset($this->testTenant)) {
            return;
        }

        $this->testTenant->forceDelete();

        if (isset($this->testTenantSchema)) {
            DB::statement('DROP SCHEMA IF EXISTS "'.$this->testTenantSchema.'" CASCADE');
        }

        DB::statement('SET search_path TO public');
    }

    protected function createTestSuperadmin(): User
    {
        config([
            'platform.superadmin.email' => 'superadmin-'.Str::random(6).'@test.local',
            'platform.superadmin.password' => 'password',
            'platform.superadmin.name' => 'Super Test',
        ]);

        $this->seed(\Database\Seeders\SuperadminSeeder::class);

        return User::query()->where('email', config('platform.superadmin.email'))->firstOrFail();
    }
}
