<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\InAppAssistant\InAppAssistantNavigation;
use App\Services\InAppAssistant\InAppAssistantToolExecutor;
use App\Services\InAppAssistant\InAppAssistantTools;
use App\Services\InAppAssistant\InAppAssistantUsageLimiter;
use App\Support\Tenancy\ClinicAdminScope;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

uses(TestCase::class);

it('solo ofrece herramientas clínicas autorizadas', function (): void {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isPlatformSuperadmin')->andReturnFalse();
    $user->shouldReceive('can')->andReturnUsing(
        static fn (string $permission): bool => in_array($permission, [
            'in-app-assistant.use',
            'citas.view',
        ], true),
    );

    $names = array_map(
        static fn (array $definition): string => $definition['function']['name'],
        InAppAssistantTools::definitions('clinic', $user),
    );

    expect($names)->toContain('agenda_citas', 'quien_atiende_hoy', 'resolver_navegacion', 'explicar_pantalla')
        ->and($names)->not->toContain('buscar_pacientes', 'buscar_productos', 'caja_del_dia', 'buscar_venta');
});

it('rechaza una herramienta antes de consultar datos cuando falta permiso', function (): void {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isPlatformSuperadmin')->andReturnFalse();
    $user->shouldReceive('can')->with('pacientes.view')->andReturnFalse();

    $executor = new InAppAssistantToolExecutor;
    $executor->setUser($user);
    $executor->setPageContext(['scope' => 'clinic']);

    $result = json_decode($executor->execute('buscar_pacientes', ['q' => 'Firulais']), true);

    expect($result)->toMatchArray([
        'ok' => false,
        'status' => 403,
    ]);
});

it('no resuelve ni ofrece navegación a módulos sin permiso', function (): void {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isPlatformSuperadmin')->andReturnFalse();
    $user->shouldReceive('can')->andReturnUsing(
        static fn (string $permission): bool => $permission === 'citas.view',
    );

    expect(InAppAssistantNavigation::resolve('abre caja', $user))->toBeNull()
        ->and(InAppAssistantNavigation::resolve('abre citas', $user))->toMatchArray([
            'id' => 'citas',
            'url' => '/clinica/citas',
        ])
        ->and(InAppAssistantNavigation::allowsUrl('/caja/ventas', $user))->toBeFalse()
        ->and(InAppAssistantNavigation::allowsUrl('/clinica/citas/123', $user))->toBeTrue();
});

it('no ofrece navegación hacia módulos todavía no implementados', function (): void {
    expect(InAppAssistantNavigation::resolve('bloqueos'))->toBeNull()
        ->and(
            collect(InAppAssistantNavigation::destinations())
                ->contains('url', '/configuracion/bloqueos'),
        )->toBeFalse();
});

it('no ofrece herramientas de plataforma a usuarios tenant', function (): void {
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isPlatformSuperadmin')->andReturnFalse();

    expect(InAppAssistantTools::definitions('platform', $user))->toBe([]);
});

it('cataloga y asigna el permiso de uso a todos los roles base', function (): void {
    expect(PermissionsSeeder::expand())->toContain('in-app-assistant.use');

    foreach (TenantRolesSeeder::ROLES as $definition) {
        expect($definition['permissions'])->toContain('in-app-assistant.use');
    }
});

it('nunca permite asignar el CRUD global de conocimiento a roles tenant', function (): void {
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        expect(ClinicAdminScope::isTenantAssignablePermission(
            "in-app-assistant-knowledge.{$action}",
        ))->toBeFalse();
    }
});

it('reserva la cuota diaria antes del trabajo sin duplicar consumo', function (): void {
    config()->set('in-app-assistant.daily_message_limit', 2);

    $user = Mockery::mock(User::class);
    $user->shouldReceive('isPlatformSuperadmin')->andReturnFalse();
    $user->shouldReceive('getAuthIdentifier')->andReturn('user-limit-test');

    $limiter = new InAppAssistantUsageLimiter;
    RateLimiter::clear($limiter->keyFor($user));

    expect($limiter->reserve($user))->toBeTrue()
        ->and($limiter->reserve($user))->toBeTrue()
        ->and($limiter->reserve($user))->toBeFalse()
        ->and($limiter->snapshot($user))->toMatchArray([
            'limit' => 2,
            'used' => 2,
            'remaining' => 0,
        ]);
});

it('aplica throttles específicos a status y chat', function (): void {
    $routes = app('router')->getRoutes();

    expect($routes->getByName('asistente.status')?->gatherMiddleware())
        ->toContain('throttle:30,1')
        ->and($routes->getByName('asistente.chat')?->gatherMiddleware())
        ->toContain('throttle:10,1');
});

it('exige dominio central en todo el CRUD global de conocimiento', function (): void {
    $routes = app('router')->getRoutes();

    foreach (['index', 'store', 'update', 'destroy'] as $action) {
        expect($routes->getByName(
            "plataforma.in-app-assistant-knowledge.{$action}",
        )?->gatherMiddleware())->toContain('tenant.none');
    }
});
