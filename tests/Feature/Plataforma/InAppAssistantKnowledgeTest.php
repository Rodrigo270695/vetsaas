<?php

declare(strict_types=1);

use App\Models\InAppAssistantKnowledge;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->superadmin = $this->createTestSuperadmin();
});

function knowledgePayload(array $overrides = []): array
{
    return array_replace([
        'slug' => 'agenda-de-citas',
        'scope' => InAppAssistantKnowledge::SCOPE_CLINIC,
        'section' => InAppAssistantKnowledge::SECTION_WORKFLOW,
        'title' => 'Agenda de citas',
        'content' => 'Explica cómo administrar la agenda.',
        'keywords' => ['agenda', 'citas'],
        'url_patterns' => ['/clinica/citas*'],
        'component_patterns' => ['clinica/citas/*'],
        'required_permissions' => ['citas.view'],
        'permission_mode' => InAppAssistantKnowledge::PERMISSION_ALL,
        'allowed_roles' => ['admin_clinica'],
        'actions' => [
            [
                'type' => 'navigate',
                'label' => 'Abrir citas',
                'url' => '/clinica/citas',
            ],
            [
                'type' => 'start_tour',
                'label' => 'Ver recorrido',
                'tour_id' => 'citas',
            ],
        ],
        'priority' => 20,
        'sort_order' => 1,
        'is_active' => true,
    ], $overrides);
}

it('cataloga permisos dedicados y los asigna al superadmin', function (): void {
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        $permission = "in-app-assistant-knowledge.{$action}";

        expect(PermissionsSeeder::expand())->toContain($permission)
            ->and($this->superadmin->can($permission))->toBeTrue();
    }
});

it('lista y filtra las guias internas paginadas', function (): void {
    InAppAssistantKnowledge::query()->create(knowledgePayload());
    InAppAssistantKnowledge::query()->create(knowledgePayload([
        'slug' => 'configuracion-plataforma',
        'scope' => InAppAssistantKnowledge::SCOPE_PLATFORM,
        'section' => InAppAssistantKnowledge::SECTION_SCREEN,
        'title' => 'Configuración central',
        'is_active' => false,
    ]));

    $this->actingAs($this->superadmin)
        ->get('http://127.0.0.1/plataforma/in-app-assistant-knowledge?search=agenda&scope=clinic&section=workflow&status=active')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('plataforma/in-app-assistant-knowledge/index')
            ->has('entries.data', 1)
            ->where('entries.data.0.slug', 'agenda-de-citas')
            ->where('filters.scope', 'clinic')
            ->where('filters.section', 'workflow')
            ->where('filters.status', 'active'));
});

it('crea actualiza activa desactiva y elimina invalidando cache', function (): void {
    Cache::put(InAppAssistantKnowledge::CACHE_KEY, [['slug' => 'obsoleto']], 60);

    $this->actingAs($this->superadmin)
        ->post('http://127.0.0.1/plataforma/in-app-assistant-knowledge', knowledgePayload())
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Cache::has(InAppAssistantKnowledge::CACHE_KEY))->toBeFalse();

    $entry = InAppAssistantKnowledge::query()->where('slug', 'agenda-de-citas')->firstOrFail();
    expect($entry->actions)->toHaveCount(2);

    Cache::put(InAppAssistantKnowledge::CACHE_KEY, [['slug' => 'obsoleto']], 60);
    $this->actingAs($this->superadmin)
        ->patch(
            "http://127.0.0.1/plataforma/in-app-assistant-knowledge/{$entry->id}",
            knowledgePayload(['title' => 'Agenda actualizada', 'is_active' => false]),
        )
        ->assertRedirect();

    expect($entry->fresh()->title)->toBe('Agenda actualizada')
        ->and($entry->fresh()->is_active)->toBeFalse()
        ->and(Cache::has(InAppAssistantKnowledge::CACHE_KEY))->toBeFalse();

    Cache::put(InAppAssistantKnowledge::CACHE_KEY, [['slug' => 'obsoleto']], 60);
    $this->actingAs($this->superadmin)
        ->delete("http://127.0.0.1/plataforma/in-app-assistant-knowledge/{$entry->id}")
        ->assertRedirect();

    expect(InAppAssistantKnowledge::query()->find($entry->id))->toBeNull()
        ->and(Cache::has(InAppAssistantKnowledge::CACHE_KEY))->toBeFalse();
});

it('rechaza acciones inseguras y campos arbitrarios', function (array $actions, string $error): void {
    $payload = knowledgePayload([
        'actions' => $actions,
        'unexpected' => 'ignored',
    ]);

    $this->actingAs($this->superadmin)
        ->post('http://127.0.0.1/plataforma/in-app-assistant-knowledge', $payload)
        ->assertSessionHasErrors($error);

    expect(InAppAssistantKnowledge::query()->count())->toBe(0);
})->with([
    'url externa' => [[[
        'type' => 'navigate',
        'label' => 'Sitio externo',
        'url' => 'https://example.com',
    ]], 'actions.0.url'],
    'ruta interna fuera del catálogo' => [[[
        'type' => 'navigate',
        'label' => 'Ruta inventada',
        'url' => '/configuracion/ruta-inventada',
    ]], 'actions.0.url'],
    'ruta de plataforma en guía clínica' => [[[
        'type' => 'navigate',
        'label' => 'Cobros de plataforma',
        'url' => '/plataforma/cobros',
    ]], 'actions.0.url'],
    'tour fuera de allowlist' => [[[
        'type' => 'start_tour',
        'label' => 'Tour inventado',
        'tour_id' => 'inventado',
    ]], 'actions.0.tour_id'],
    'tipo desconocido' => [[[
        'type' => 'execute',
        'label' => 'Ejecutar',
    ]], 'actions.0.type'],
]);

it('aplica permisos independientes por accion', function (): void {
    $user = User::factory()->create([
        'tenant_id' => null,
        'is_active' => true,
        'must_change_password' => false,
        'email_verified_at' => now(),
    ]);
    $entry = InAppAssistantKnowledge::query()->create(knowledgePayload());

    $this->actingAs($user)
        ->get('http://127.0.0.1/plataforma/in-app-assistant-knowledge')
        ->assertForbidden();
    $this->actingAs($user)
        ->post('http://127.0.0.1/plataforma/in-app-assistant-knowledge', knowledgePayload([
            'slug' => 'otra-guia',
        ]))
        ->assertForbidden();
    $this->actingAs($user)
        ->patch(
            "http://127.0.0.1/plataforma/in-app-assistant-knowledge/{$entry->id}",
            knowledgePayload(),
        )
        ->assertForbidden();
    $this->actingAs($user)
        ->delete("http://127.0.0.1/plataforma/in-app-assistant-knowledge/{$entry->id}")
        ->assertForbidden();
});
