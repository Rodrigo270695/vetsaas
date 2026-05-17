<?php

use App\Models\Tenant;
use App\Tenancy\Exceptions\TenantNotFoundException;
use App\Tenancy\Exceptions\TenantSuspendedException;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pruebas sobre el comportamiento del TenantManager.
 *
 * Crea/destruye un tenant + schema físico de prueba (`vet_test_<rand>`)
 * para validar de extremo a extremo:
 *   - Que resolveBySlug aplique `SET search_path` correctamente.
 *   - Que `forget()` revierta a `public`.
 *   - Que `runForSlug` restaure el estado anterior aunque la callback falle.
 *   - Que estados no permitidos lancen `TenantSuspendedException`.
 *   - Que slugs inexistentes lancen `TenantNotFoundException`.
 *   - Que `flushCacheFor` invalide el cache correctamente.
 */
beforeEach(function (): void {
    // El aislamiento por schema es exclusivo de PostgreSQL.
    // En suites que corren con SQLite en memoria omitimos toda
    // la batería: estos tests deben ejecutarse contra una BD real.
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('TenantManager solo aplica a PostgreSQL.');
    }

    Cache::flush();

    config([
        'tenant.allowed_states' => ['active', 'trial', 'grace'],
        'tenant.schema_prefix' => 'vet_',
        'tenant.cache_ttl' => 0,
    ]);

    $this->schema = 'vet_test_'.Str::lower(Str::random(8));
    DB::statement('CREATE SCHEMA IF NOT EXISTS "'.$this->schema.'"');

    $this->tenant = Tenant::create([
        'slug' => 'test-'.Str::lower(Str::random(6)),
        'schema_name' => $this->schema,
        'razon_social' => 'Test Clinic',
        'email_admin' => 'admin-'.Str::random(4).'@test.local',
        'timezone' => 'America/Lima',
        'locale' => 'es',
        'estado' => 'active',
    ]);
});

afterEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        return;
    }
    app(TenantManager::class)->forget();
    if (isset($this->tenant)) {
        $this->tenant->forceDelete();
    }
    if (isset($this->schema)) {
        DB::statement('DROP SCHEMA IF EXISTS "'.$this->schema.'" CASCADE');
    }
    DB::statement('SET search_path TO public');
});

it('aplica SET search_path al resolver un slug válido', function (): void {
    $manager = app(TenantManager::class);

    $context = $manager->resolveBySlug($this->tenant->slug);

    expect($manager->check())->toBeTrue();
    expect($context->slug)->toBe($this->tenant->slug);
    expect($context->schema)->toBe($this->schema);

    $currentSearchPath = DB::selectOne('SHOW search_path')->search_path;
    expect($currentSearchPath)->toContain($this->schema);
});

it('forget() limpia el contexto y restaura search_path', function (): void {
    $manager = app(TenantManager::class);
    $manager->resolveBySlug($this->tenant->slug);

    $manager->forget();

    expect($manager->check())->toBeFalse();
    expect($manager->current())->toBeNull();

    $currentSearchPath = DB::selectOne('SHOW search_path')->search_path;
    expect($currentSearchPath)->not->toContain($this->schema);
});

it('lanza TenantNotFoundException para slugs inexistentes', function (): void {
    $manager = app(TenantManager::class);

    $manager->resolveBySlug('slug-que-no-existe');
})->throws(TenantNotFoundException::class);

it('lanza TenantSuspendedException si el estado no está permitido', function (): void {
    $this->tenant->update(['estado' => 'suspended', 'suspended_at' => now()]);

    $manager = app(TenantManager::class);

    $manager->resolveBySlug($this->tenant->slug);
})->throws(TenantSuspendedException::class);

it('runForSlug restaura el estado previo aunque la callback falle', function (): void {
    $manager = app(TenantManager::class);

    expect($manager->check())->toBeFalse();

    try {
        $manager->runForSlug($this->tenant->slug, function (): void {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // esperado
    }

    expect($manager->check())->toBeFalse();
    $currentSearchPath = DB::selectOne('SHOW search_path')->search_path;
    expect($currentSearchPath)->not->toContain($this->schema);
});

it('flushCacheFor invalida la entrada por slug', function (): void {
    config(['tenant.cache_ttl' => 60]);
    $manager = app(TenantManager::class);

    $manager->resolveBySlug($this->tenant->slug);
    expect(Cache::has("tenant:slug:{$this->tenant->slug}"))->toBeTrue();

    $manager->flushCacheFor($this->tenant);

    expect(Cache::has("tenant:slug:{$this->tenant->slug}"))->toBeFalse();
});
