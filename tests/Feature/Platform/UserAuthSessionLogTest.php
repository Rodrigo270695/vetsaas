<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserAuthSessionLog;
use App\Services\Platform\UserAuthSessionLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Historial de sesiones de login requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

function createPlanForAuthSessionTests(string $codigo, string $nombre): Plan
{
    return Plan::query()->create([
        'codigo' => $codigo,
        'nombre' => $nombre,
        'descripcion' => null,
        'precio_mensual' => $codigo === Plan::CODIGO_FREE ? '0' : '149',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => $codigo === Plan::CODIGO_FREE ? 1 : 2,
        'es_publico' => true,
        'activo' => true,
    ]);
}

function attachSubscription(Tenant $tenant, Plan $plan): void
{
    Subscription::withoutEvents(function () use ($tenant, $plan): void {
        Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'estado' => 'active',
            'ciclo' => 'mensual',
            'current_period_start' => now()->subDay(),
            'current_period_end' => now()->addMonth(),
            'proximo_cobro_at' => now()->addMonth(),
            'precio_pactado' => $plan->precio_mensual,
        ]);
    });
}

it('crea una fila abierta al iniciar sesión con el plan de la clínica', function (): void {
    $plan = createPlanForAuthSessionTests(Plan::CODIGO_FREE, 'Free');
    attachSubscription($this->testTenant, $plan);

    $this->actingAs($this->testTenantAdmin);
    Event::dispatch(new Login('web', $this->testTenantAdmin, false));

    $log = UserAuthSessionLog::query()
        ->where('user_id', $this->testTenantAdmin->id)
        ->latest('logged_in_at')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->tenant_id)->toBe((string) $this->testTenant->id);
    expect($log->plan_codigo)->toBe(Plan::CODIGO_FREE);
    expect($log->logged_out_at)->toBeNull();
    expect($log->user_email)->toBe($this->testTenantAdmin->email);
});

it('cierra la fila con motivo logout al cerrar sesión', function (): void {
    $plan = createPlanForAuthSessionTests('starter', 'Starter');
    attachSubscription($this->testTenant, $plan);

    $this->actingAs($this->testTenantAdmin);
    Event::dispatch(new Login('web', $this->testTenantAdmin, false));

    $sessionId = session()->getId();

    // Simula SessionGuard::logout: destruye la cookie anterior y regenera id.
    DB::table('sessions')->where('id', $sessionId)->delete();
    session()->migrate(true);

    Event::dispatch(new Logout('web', $this->testTenantAdmin));

    $log = UserAuthSessionLog::query()
        ->where('user_id', $this->testTenantAdmin->id)
        ->where('session_id', $sessionId)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->logged_out_at)->not->toBeNull();
    expect($log->logout_reason)->toBe(UserAuthSessionLog::REASON_LOGOUT);
});

it('marca como expired las sesiones sin cookie Laravel vigente', function (): void {
    $plan = createPlanForAuthSessionTests(Plan::CODIGO_FREE, 'Free');
    attachSubscription($this->testTenant, $plan);

    $sessionId = 'test-session-'.Str::lower(Str::random(12));

    UserAuthSessionLog::query()->create([
        'user_id' => $this->testTenantAdmin->id,
        'tenant_id' => $this->testTenant->id,
        'session_id' => $sessionId,
        'user_name' => $this->testTenantAdmin->name,
        'user_email' => $this->testTenantAdmin->email,
        'tenant_slug' => $this->testTenant->slug,
        'plan_codigo' => Plan::CODIGO_FREE,
        'logged_in_at' => now()->subHours(3),
    ]);

    Artisan::call('vetsaas:auth-sessions-expire-stale');

    $log = UserAuthSessionLog::query()->where('session_id', $sessionId)->first();

    expect($log?->logged_out_at)->not->toBeNull();
    expect($log?->logout_reason)->toBe(UserAuthSessionLog::REASON_EXPIRED);
});

it('permite al superadmin filtrar historial free vs de pago', function (): void {
    $freePlan = createPlanForAuthSessionTests(Plan::CODIGO_FREE, 'Free');
    $paidPlan = createPlanForAuthSessionTests('starter', 'Starter');
    attachSubscription($this->testTenant, $freePlan);

    $paidTenant = Tenant::query()->create([
        'slug' => 'paid-'.Str::lower(Str::random(6)),
        'schema_name' => 'vet_paid_'.Str::lower(Str::random(6)),
        'razon_social' => 'Clínica Paid',
        'nombre_comercial' => 'Paid Clinic',
        'email_admin' => 'paid-'.Str::lower(Str::random(6)).'@test.local',
        'estado' => 'active',
    ]);
    attachSubscription($paidTenant, $paidPlan);

    $paidUser = User::factory()->create([
        'tenant_id' => $paidTenant->id,
        'email' => 'user-'.$paidTenant->slug.'@test.local',
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    app(UserAuthSessionLogger::class)->openFromLogin($this->testTenantAdmin);
    app(UserAuthSessionLogger::class)->openFromLogin($paidUser);

    // Ajusta snapshots de plan (openFromLogin lee suscripción activa).
    UserAuthSessionLog::query()
        ->where('user_id', $this->testTenantAdmin->id)
        ->update(['plan_codigo' => Plan::CODIGO_FREE]);
    UserAuthSessionLog::query()
        ->where('user_id', $paidUser->id)
        ->update(['plan_codigo' => 'starter']);

    $superadmin = $this->createTestSuperadmin();
    $this->actingAs($superadmin);

    $freeResponse = $this->get('http://127.0.0.1/plataforma/sesiones-login?plan_grupo=free');
    $freeResponse->assertOk();
    $freeResponse->assertInertia(fn ($page) => $page
        ->component('plataforma/sesiones-login/index')
        ->where('filters.plan_grupo', 'free')
        ->has('logs.data', 1)
        ->where('logs.data.0.plan_codigo', Plan::CODIGO_FREE));

    $paidResponse = $this->get('http://127.0.0.1/plataforma/sesiones-login?plan_grupo=paid');
    $paidResponse->assertOk();
    $paidResponse->assertInertia(fn ($page) => $page
        ->component('plataforma/sesiones-login/index')
        ->where('filters.plan_grupo', 'paid')
        ->has('logs.data', 1)
        ->where('logs.data.0.plan_codigo', 'starter'));
});

it('exige permiso de operaciones para ver el historial', function (): void {
    $this->actingAs($this->testTenantAdmin);

    $this->get('http://127.0.0.1/plataforma/sesiones-login')
        ->assertForbidden();
});
