<?php

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Auth\PasswordResetLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Fase 2.6 — Aislamiento del flujo de password reset entre tenants.
 *
 * El driver de auth `tenant-eloquent` y el repositorio de tokens
 * `TenantAwarePasswordTokenRepository` garantizan que:
 *
 *   - Dos usuarios con el MISMO email en clínicas distintas (o uno
 *     central + uno en clínica) pueden coexistir y resetear su
 *     contraseña sin invalidarse el token entre ellos.
 *   - El reset hecho desde un host SOLO afecta al usuario que
 *     pertenece a ese host.
 *
 * Requiere PostgreSQL porque el índice composite
 * `password_reset_tokens_tenant_email_unique` y el filtrado por
 * `tenant_id` dependen de columnas que solo añadimos en la migración
 * de Fase 2.6. La suite de SQLite los omite.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('TenantPasswordReset solo aplica a PostgreSQL.');
    }

    config([
        'tenant.central_domains' => ['localhost', '127.0.0.1', 'vetsaas.test'],
        'tenant.root_domain' => 'vetsaas.test',
        'tenant.schema_prefix' => 'vet_',
        'tenant.allowed_states' => ['active', 'trial', 'grace'],
        'tenant.cache_ttl' => 0,
    ]);

    $this->slugA = 'clinica-a-'.Str::lower(Str::random(4));
    $this->slugB = 'clinica-b-'.Str::lower(Str::random(4));

    $this->schemaA = 'vet_test_'.Str::lower(Str::random(6));
    $this->schemaB = 'vet_test_'.Str::lower(Str::random(6));

    DB::statement('CREATE SCHEMA IF NOT EXISTS "'.$this->schemaA.'"');
    DB::statement('CREATE SCHEMA IF NOT EXISTS "'.$this->schemaB.'"');

    $this->tenantA = Tenant::create([
        'slug' => $this->slugA,
        'schema_name' => $this->schemaA,
        'razon_social' => 'Clínica A',
        'email_admin' => 'a-'.Str::random(4).'@test.local',
        'timezone' => 'America/Lima',
        'locale' => 'es',
        'estado' => 'active',
    ]);
    $this->tenantB = Tenant::create([
        'slug' => $this->slugB,
        'schema_name' => $this->schemaB,
        'razon_social' => 'Clínica B',
        'email_admin' => 'b-'.Str::random(4).'@test.local',
        'timezone' => 'America/Lima',
        'locale' => 'es',
        'estado' => 'active',
    ]);

    $this->sharedEmail = 'maria@gmail.com';

    $this->userA = User::factory()->create([
        'email' => $this->sharedEmail,
        'tenant_id' => $this->tenantA->id,
        'password' => Hash::make('clave-A'),
    ]);
    $this->userB = User::factory()->create([
        'email' => $this->sharedEmail,
        'tenant_id' => $this->tenantB->id,
        'password' => Hash::make('clave-B'),
    ]);
    $this->userCentral = User::factory()->create([
        'email' => $this->sharedEmail,
        'tenant_id' => null,
        'password' => Hash::make('clave-central'),
    ]);
});

afterEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        return;
    }

    foreach ([$this->tenantA ?? null, $this->tenantB ?? null] as $tenant) {
        $tenant?->forceDelete();
    }
    foreach ([$this->schemaA ?? null, $this->schemaB ?? null] as $schema) {
        if ($schema) {
            DB::statement('DROP SCHEMA IF EXISTS "'.$schema.'" CASCADE');
        }
    }
    DB::statement('SET search_path TO public');
});

it('forgot-password desde el host de un tenant SOLO afecta al user de ese tenant', function (): void {
    Notification::fake();

    $this->post('http://'.$this->slugA.'.vetsaas.test/forgot-password', [
        'email' => $this->sharedEmail,
    ])->assertSessionHasNoErrors();

    Notification::assertSentTo($this->userA, PasswordResetLinkNotification::class);
    Notification::assertNotSentTo($this->userB, PasswordResetLinkNotification::class);
    Notification::assertNotSentTo($this->userCentral, PasswordResetLinkNotification::class);
});

it('forgot-password desde el host central SOLO afecta al user central', function (): void {
    Notification::fake();

    $this->post('http://vetsaas.test/forgot-password', [
        'email' => $this->sharedEmail,
    ])->assertSessionHasNoErrors();

    Notification::assertSentTo($this->userCentral, PasswordResetLinkNotification::class);
    Notification::assertNotSentTo($this->userA, PasswordResetLinkNotification::class);
    Notification::assertNotSentTo($this->userB, PasswordResetLinkNotification::class);
});

it('los tokens de reset de los dos tenants conviven sin pisarse', function (): void {
    Notification::fake();

    // El broker tiene throttle (60s por defecto) compartido por broker —
    // sin embargo cada usuario debería tener su propia ventana, porque
    // el repositorio scopea por (email, tenant_id). Para evitar falsos
    // positivos por throttle global usamos el broker directamente.
    $rA = $this->post('http://'.$this->slugA.'.vetsaas.test/forgot-password', [
        'email' => $this->sharedEmail,
    ]);
    $rB = $this->post('http://'.$this->slugB.'.vetsaas.test/forgot-password', [
        'email' => $this->sharedEmail,
    ]);

    // Ambas respuestas deben ser sin errores de validación; si una está
    // throttled, el broker devuelve un status que Fortify enruta como
    // session flash sin error de validación pero con `errors.email`.
    $rA->assertSessionHasNoErrors();
    $rB->assertSessionHasNoErrors();

    $rows = DB::table('password_reset_tokens')
        ->where('email', $this->sharedEmail)
        ->orderBy('tenant_id')
        ->get();

    expect($rows)->toHaveCount(2);
    $tenantIds = $rows->pluck('tenant_id')->all();
    expect($tenantIds)->toContain($this->tenantA->id);
    expect($tenantIds)->toContain($this->tenantB->id);
});

it('un token emitido para el tenant A no resetea el password del tenant B', function (): void {
    $broker = Password::broker();
    $tokenForA = $broker->createToken($this->userA);

    $response = $this->post('http://'.$this->slugB.'.vetsaas.test/reset-password', [
        'token' => $tokenForA,
        'email' => $this->sharedEmail,
        'password' => 'nueva-clave-segura',
        'password_confirmation' => 'nueva-clave-segura',
    ]);

    $response->assertSessionHasErrors('email');

    expect(Hash::check('clave-B', $this->userB->fresh()->password))->toBeTrue();
    expect(Hash::check('clave-A', $this->userA->fresh()->password))->toBeTrue();
});

it('un token emitido para el tenant A SÍ resetea el password del user A en su host', function (): void {
    $broker = Password::broker();
    $tokenForA = $broker->createToken($this->userA);

    $response = $this->post('http://'.$this->slugA.'.vetsaas.test/reset-password', [
        'token' => $tokenForA,
        'email' => $this->sharedEmail,
        'password' => 'clave-recien-creada',
        'password_confirmation' => 'clave-recien-creada',
    ]);

    $response->assertSessionHasNoErrors();

    expect(Hash::check('clave-recien-creada', $this->userA->fresh()->password))->toBeTrue();
    expect(Hash::check('clave-B', $this->userB->fresh()->password))->toBeTrue();
    expect(Hash::check('clave-central', $this->userCentral->fresh()->password))->toBeTrue();
});
