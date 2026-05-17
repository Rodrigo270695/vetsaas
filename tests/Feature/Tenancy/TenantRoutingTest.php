<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tests end-to-end del routing por subdominio (Fase 2).
 *
 * Verifican que:
 *   - Una ruta del panel SaaS NO responda desde un subdominio de tenant.
 *   - La ruta del tenant SÍ responda desde su subdominio (cuando existe).
 *   - Un subdominio inexistente recibe la página de error `not-found`.
 *   - Un subdominio cuyo tenant está suspendido recibe `blocked` (403).
 *
 * Requieren PostgreSQL (search_path es exclusivo de Postgres). Se saltan
 * automáticamente cuando la suite corre en SQLite (modo CI por defecto).
 */
beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Tenant routing solo aplica a PostgreSQL.');
    }

    config([
        'tenant.central_domains' => ['localhost', '127.0.0.1', 'vetsaas.test'],
        'tenant.root_domain' => 'vetsaas.test',
        'tenant.schema_prefix' => 'vet_',
        'tenant.allowed_states' => ['active', 'trial', 'grace'],
        'tenant.cache_ttl' => 0,
    ]);

    $this->schema = 'vet_test_'.Str::lower(Str::random(8));
    DB::statement('CREATE SCHEMA IF NOT EXISTS "'.$this->schema.'"');

    $this->slug = 'test-route-'.Str::lower(Str::random(6));
    $this->tenant = Tenant::create([
        'slug' => $this->slug,
        'schema_name' => $this->schema,
        'razon_social' => 'Test Routing Clinic',
        'email_admin' => 'route-'.Str::random(4).'@test.local',
        'timezone' => 'America/Lima',
        'locale' => 'es',
        'estado' => 'active',
    ]);
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

it('la ruta tenant.home responde 200 desde el subdominio correcto', function (): void {
    $response = $this->get('http://'.$this->slug.'.vetsaas.test/');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('tenant/welcome'));
});

it('comparte el tenant resuelto como prop de Inertia', function (): void {
    $response = $this->get('http://'.$this->slug.'.vetsaas.test/');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('tenant.slug', $this->slug)
        ->where('tenant.estado', 'active')
    );
});

it('un subdominio inexistente renderiza la página "not-found" con 404', function (): void {
    $response = $this->get('http://subdominio-inexistente.vetsaas.test/');

    $response->assertStatus(404);
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/errors/not-found')
        ->where('slug', 'subdominio-inexistente')
    );
});

it('un tenant suspendido renderiza la página "blocked" con 403', function (): void {
    $this->tenant->update([
        'estado' => 'suspended',
        'suspended_at' => now(),
        'suspension_reason' => 'Falta de pago',
    ]);

    $response = $this->get('http://'.$this->slug.'.vetsaas.test/');

    $response->assertStatus(403);
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/errors/blocked')
        ->where('estado', 'suspended')
        ->where('reason', 'Falta de pago')
    );
});

it('/plataforma/tenants devuelve 404 desde un subdominio de tenant', function (): void {
    // Sin estar autenticado el comportamiento normal sería redirect a
    // login; con `tenant.none` queremos un 404 desde subdominios,
    // independientemente de la autenticación.
    $response = $this->get('http://'.$this->slug.'.vetsaas.test/plataforma/tenants');

    $response->assertStatus(404);
});

it('/plataforma/tenants existe (como ruta) desde el dominio central', function (): void {
    // No nos importa el código exacto (puede ser 302 por auth, 403 por
    // permiso). Lo único que verificamos es que la ruta no es 404,
    // demostrando que sigue accesible desde el host central.
    $response = $this->get('http://vetsaas.test/plataforma/tenants');

    expect($response->getStatusCode())->not->toBe(404);
});
