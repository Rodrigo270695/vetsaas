<?php

declare(strict_types=1);

use App\Models\InAppAssistantKnowledge;
use App\Models\User;
use App\Services\InAppAssistant\InAppAssistantKnowledgeRepository;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config()->set('cache.default', 'array');
    Cache::flush();
});

it('rankea conocimiento y filtra entradas y acciones por permisos', function (): void {
    Cache::put(InAppAssistantKnowledge::CACHE_KEY, [
        knowledgeRow(
            slug: 'citas',
            title: 'Clínica · Citas',
            permission: 'citas.view',
            actions: [
                ['type' => 'navigate', 'url' => '/clinica/citas', 'label' => 'Ir a Citas', 'required_permissions' => ['citas.view']],
                ['type' => 'navigate', 'url' => 'https://evil.test', 'label' => 'Externa'],
                ['type' => 'navigate', 'url' => '/configuracion/usuarios', 'label' => 'Usuarios sin permiso'],
            ],
        ),
        knowledgeRow(
            slug: 'ventas',
            title: 'Caja · Ventas',
            permission: 'ventas.view',
            actions: [
                ['type' => 'navigate', 'url' => '/caja/ventas', 'label' => 'Ir a Ventas', 'required_permissions' => ['ventas.view']],
            ],
        ),
    ]);

    $user = Mockery::mock(User::class);
    $user->shouldReceive('isPlatformSuperadmin')->andReturnFalse();
    $user->shouldReceive('can')->andReturnUsing(
        static fn (string $permission): bool => $permission === 'citas.view',
    );
    $user->shouldReceive('getAllPermissions')->andReturn(collect([(object) ['name' => 'citas.view']]));
    $user->shouldReceive('getRoleNames')->andReturn(collect(['recepcionista']));

    $result = (new InAppAssistantKnowledgeRepository(6, 10000))
        ->search('¿Cómo reviso las citas?', 'clinic', ['url' => '/clinica/citas'], $user);

    expect(array_column($result['entries'], 'slug'))->toBe(['citas'])
        ->and($result['context'])->toContain('Clínica · Citas')
        ->and($result['context'])->not->toContain('Caja · Ventas')
        ->and($result['actions'])->toBe([
            ['type' => 'navigate', 'url' => '/clinica/citas', 'label' => 'Ir a Citas'],
        ]);
});

it('respeta los límites configurados de entradas y caracteres', function (): void {
    Cache::put(InAppAssistantKnowledge::CACHE_KEY, [
        knowledgeRow('uno', 'Citas uno', null, content: str_repeat('a', 200)),
        knowledgeRow('dos', 'Citas dos', null, content: str_repeat('b', 200)),
    ]);

    $user = Mockery::mock(User::class);
    $user->shouldReceive('isPlatformSuperadmin')->andReturnFalse();
    $user->shouldReceive('getAllPermissions')->andReturn(collect());
    $user->shouldReceive('getRoleNames')->andReturn(collect());

    $result = (new InAppAssistantKnowledgeRepository(1, 80))
        ->search('citas', 'clinic', null, $user);

    expect($result['entries'])->toHaveCount(1)
        ->and(mb_strlen($result['context']))->toBeLessThanOrEqual(80);
});

it('valida allowlist permisos roles y deduplicación de start_tour', function (): void {
    $tour = [
        'type' => 'start_tour',
        'tour_id' => 'citas',
        'label' => 'Ver tour de Citas',
    ];

    Cache::put(InAppAssistantKnowledge::CACHE_KEY, [
        knowledgeRow(
            slug: 'tours',
            title: 'Tours de citas',
            permission: null,
            actions: [
                $tour,
                $tour,
                [
                    'type' => 'start_tour',
                    'tour_id' => 'inventado',
                    'label' => 'Tour inválido',
                ],
                [
                    'type' => 'start_tour',
                    'tour_id' => 'pacientes',
                    'label' => 'Tour sin permiso',
                ],
                [
                    'type' => 'start_tour',
                    'tour_id' => 'citas',
                    'label' => 'Solo administradores',
                    'allowed_roles' => ['admin_clinica'],
                ],
            ],
        ),
    ]);

    $user = Mockery::mock(User::class);
    $user->shouldReceive('isPlatformSuperadmin')->andReturnFalse();
    $user->shouldReceive('getAllPermissions')->andReturn(collect([(object) ['name' => 'citas.view']]));
    $user->shouldReceive('getRoleNames')->andReturn(collect(['recepcionista']));

    $result = (new InAppAssistantKnowledgeRepository(6, 10000))
        ->search('tour de citas', 'clinic', null, $user);

    expect($result['actions'])->toBe([[
        'type' => 'start_tour',
        'tour_id' => 'citas',
        'label' => 'Ver tour de Citas',
    ]]);

    $withoutPermission = Mockery::mock(User::class);
    $withoutPermission->shouldReceive('isPlatformSuperadmin')->andReturnFalse();
    $withoutPermission->shouldReceive('getAllPermissions')->andReturn(collect());
    $withoutPermission->shouldReceive('getRoleNames')->andReturn(collect(['recepcionista']));

    $denied = (new InAppAssistantKnowledgeRepository(6, 10000))
        ->search('tour de citas', 'clinic', null, $withoutPermission);

    expect($denied['actions'])->toBe([]);
});

it('limita acciones de plataforma al catálogo cerrado', function (): void {
    $row = knowledgeRow(
        slug: 'plataforma',
        title: 'Cobros de plataforma',
        permission: null,
        actions: [
            ['type' => 'navigate', 'url' => '/plataforma/cobros?estado=pendiente', 'label' => 'Cobros'],
            ['type' => 'navigate', 'url' => '/plataforma/ruta-inventada', 'label' => 'Desconocida'],
        ],
    );
    $row['scope'] = 'platform';
    Cache::put(InAppAssistantKnowledge::CACHE_KEY, [$row]);

    $user = Mockery::mock(User::class);
    $user->shouldReceive('isPlatformSuperadmin')->andReturnTrue();

    $result = (new InAppAssistantKnowledgeRepository(6, 10000))
        ->search('cobros', 'platform', null, $user);

    expect($result['actions'])->toBe([[
        'type' => 'navigate',
        'url' => '/plataforma/cobros?estado=pendiente',
        'label' => 'Cobros',
    ]]);
});

/**
 * @param  list<array<string, mixed>>  $actions
 * @return array<string, mixed>
 */
function knowledgeRow(
    string $slug,
    string $title,
    ?string $permission,
    array $actions = [],
    string $content = 'Documentación de prueba.',
): array {
    return [
        'slug' => $slug,
        'scope' => 'clinic',
        'section' => 'module',
        'title' => $title,
        'content' => $content,
        'keywords' => ['citas'],
        'url_patterns' => [],
        'component_patterns' => [],
        'required_permissions' => $permission === null ? [] : [$permission],
        'permission_mode' => 'any',
        'allowed_roles' => [],
        'actions' => $actions,
        'priority' => 10,
        'sort_order' => 10,
        'is_active' => true,
    ];
}
