<?php

declare(strict_types=1);

use App\Models\InAppAssistantAnnouncement;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->superadmin = $this->createTestSuperadmin();
});

function makeNovedad(string $title, bool $active = true): InAppAssistantAnnouncement
{
    return InAppAssistantAnnouncement::query()->create([
        'title' => $title,
        'body' => 'Texto de prueba de la novedad.',
        'features' => ['Punto A'],
        'is_active' => $active,
        'version' => 1,
        'published_at' => now(),
    ]);
}

it('permite hasta tres novedades activas a la vez', function (): void {
    makeNovedad('Uno');
    makeNovedad('Dos');
    makeNovedad('Tres');

    expect(InAppAssistantAnnouncement::liveCount())->toBe(3)
        ->and(InAppAssistantAnnouncement::tenantPayloads())->toHaveCount(3);

    $this->actingAs($this->superadmin)
        ->post('http://127.0.0.1/plataforma/configuracion/novedades', [
            'title' => 'Cuatro',
            'body' => 'No debería publicarse activa.',
            'features' => ['x'],
            'publish_now' => true,
        ])
        ->assertSessionHasErrors('publish_now');

    expect(InAppAssistantAnnouncement::liveCount())->toBe(3);
});

it('republicar una novedad no apaga las otras', function (): void {
    $primera = makeNovedad('Portal');
    makeNovedad('Chat');

    $this->actingAs($this->superadmin)
        ->post('http://127.0.0.1/plataforma/configuracion/novedades/'.$primera->id.'/republicar')
        ->assertRedirect();

    expect(InAppAssistantAnnouncement::liveCount())->toBe(2)
        ->and($primera->fresh()?->version)->toBe(2)
        ->and($primera->fresh()?->is_active)->toBeTrue();
});
