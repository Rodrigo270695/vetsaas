<?php

declare(strict_types=1);

use App\Models\BotIaAnnouncement;
use Inertia\Inertia;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->superadmin = $this->createTestSuperadmin();
});

it('lista novedades del asistente ia en plataforma', function (): void {
    BotIaAnnouncement::query()->create([
        'title' => 'Novedad de prueba',
        'badge' => BotIaAnnouncement::BADGE_NUEVO,
        'bullet_1' => 'Punto uno',
        'bullet_2' => 'Punto dos',
        'bullet_3' => 'Punto tres',
        'is_active' => true,
        'published_at' => now(),
    ]);

    $this->actingAs($this->superadmin)
        ->get('http://127.0.0.1/plataforma/bot-ia-announcements')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('plataforma/bot-ia-announcements/index')
            ->has('entries.data', 1));
});

it('publica una novedad y desactiva las anteriores', function (): void {
    $previous = BotIaAnnouncement::query()->create([
        'title' => 'Anterior',
        'badge' => BotIaAnnouncement::BADGE_MEJORA,
        'bullet_1' => 'Vieja uno',
        'bullet_2' => 'Vieja dos',
        'bullet_3' => 'Vieja tres',
        'is_active' => true,
        'published_at' => now()->subDay(),
    ]);

    $this->actingAs($this->superadmin)
        ->post('http://127.0.0.1/plataforma/bot-ia-announcements', [
            'title' => 'Nueva',
            'badge' => BotIaAnnouncement::BADGE_NUEVO,
            'bullet_1' => 'Actual uno',
            'bullet_2' => 'Actual dos',
            'bullet_3' => 'Actual tres',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($previous->fresh()->is_active)->toBeFalse()
        ->and(BotIaAnnouncement::query()->where('title', 'Nueva')->value('is_active'))->toBeTrue();
});

it('filtra novedades por estado y busqueda', function (): void {
    BotIaAnnouncement::query()->create([
        'title' => 'Activa publicada',
        'badge' => BotIaAnnouncement::BADGE_NUEVO,
        'bullet_1' => 'Punto activo',
        'bullet_2' => 'Punto dos',
        'bullet_3' => 'Punto tres',
        'is_active' => true,
        'published_at' => now()->subHour(),
    ]);

    BotIaAnnouncement::query()->create([
        'title' => 'Borrador WhatsApp',
        'badge' => BotIaAnnouncement::BADGE_IMPORTANTE,
        'bullet_1' => 'Punto inactivo',
        'bullet_2' => 'Punto dos',
        'bullet_3' => 'Punto tres',
        'is_active' => false,
    ]);

    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => Inertia::getVersion(),
        'X-Inertia-Partial-Data' => 'entries,filters,active_announcement_id',
        'X-Inertia-Partial-Component' => 'plataforma/bot-ia-announcements/index',
    ];

    $this->actingAs($this->superadmin)
        ->get('http://127.0.0.1/plataforma/bot-ia-announcements?status=activo&page=1', $headers)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
            ->where('filters.status', 'activo'));

    $this->actingAs($this->superadmin)
        ->get('http://127.0.0.1/plataforma/bot-ia-announcements?status=inactivo&page=1', $headers)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
            ->where('filters.status', 'inactivo'));

    $this->actingAs($this->superadmin)
        ->get('http://127.0.0.1/plataforma/bot-ia-announcements?search=WhatsApp&page=1', $headers)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
            ->where('filters.search', 'WhatsApp'));
});

it('activa una novedad existente desde plataforma', function (): void {
    $draft = BotIaAnnouncement::query()->create([
        'title' => 'Borrador',
        'badge' => BotIaAnnouncement::BADGE_IMPORTANTE,
        'bullet_1' => 'Pendiente uno',
        'bullet_2' => 'Pendiente dos',
        'bullet_3' => 'Pendiente tres',
        'is_active' => false,
    ]);

    $this->actingAs($this->superadmin)
        ->post("http://127.0.0.1/plataforma/bot-ia-announcements/{$draft->id}/activate")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($draft->fresh()->is_active)->toBeTrue()
        ->and($draft->fresh()->published_at)->not->toBeNull();
});
