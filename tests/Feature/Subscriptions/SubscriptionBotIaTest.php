<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Bot IA requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
    $this->superadmin = $this->createTestSuperadmin();

    $this->plan = Plan::query()->create([
        'codigo' => 'starter',
        'nombre' => 'Starter',
        'descripcion' => null,
        'precio_mensual' => '39.90',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => 10,
        'es_publico' => true,
        'activo' => true,
    ]);

    $this->subscription = Subscription::withoutEvents(function (): Subscription {
        return Subscription::query()->create([
            'tenant_id' => $this->testTenant->id,
            'plan_id' => $this->plan->id,
            'estado' => 'active',
            'ciclo' => 'mensual',
            'precio_pactado' => '39.90',
        ]);
    });
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('activa el add-on bot ia desde plataforma', function (): void {
    expect($this->subscription->fresh()->bot_ia_activo)->toBeFalse();

    $this->actingAs($this->superadmin)
        ->post('http://127.0.0.1/plataforma/suscripciones/'.$this->subscription->id.'/toggle-bot-ia', [
            'activo' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $fresh = $this->subscription->fresh();

    expect($fresh->bot_ia_activo)->toBeTrue()
        ->and($fresh->bot_ia_precio_mensual)->toBe('15.00')
        ->and($fresh->bot_ia_activado_at)->not->toBeNull();
});

it('desactiva el add-on bot ia desde plataforma', function (): void {
    $this->subscription->update([
        'bot_ia_activo' => true,
        'bot_ia_precio_mensual' => '15.00',
        'bot_ia_activado_at' => now(),
    ]);

    $this->actingAs($this->superadmin)
        ->post('http://127.0.0.1/plataforma/suscripciones/'.$this->subscription->id.'/toggle-bot-ia', [
            'activo' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $fresh = $this->subscription->fresh();

    expect($fresh->bot_ia_activo)->toBeFalse()
        ->and($fresh->bot_ia_activado_at)->toBeNull();
});

it('muestra bot ia activo en mi suscripción del tenant', function (): void {
    $this->subscription->update([
        'bot_ia_activo' => true,
        'bot_ia_precio_mensual' => '15.00',
        'bot_ia_activado_at' => now(),
    ]);

    $this->actingAs($this->testTenantAdmin)
        ->get('http://'.$this->testTenantHost.'/configuracion/suscripcion')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('configuracion/suscripcion/index')
            ->where('subscription.bot_ia.activo', true)
            ->where('subscription.bot_ia.precio_mensual', '15.00'));
});

it('muestra la vista de asistente ia en comunicaciones cuando está activo', function (): void {
    \App\Models\BotIaAnnouncement::query()->create([
        'title' => 'Nueva mejora del Asistente IA',
        'badge' => \App\Models\BotIaAnnouncement::BADGE_NUEVO,
        'bullet_1' => 'Registra clientes por WhatsApp.',
        'bullet_2' => 'Agenda citas desde el chat.',
        'bullet_3' => 'Revisa conversaciones en Chats.',
        'is_active' => true,
        'published_at' => now(),
    ]);

    $this->subscription->update([
        'bot_ia_activo' => true,
        'bot_ia_precio_mensual' => '15.00',
        'bot_ia_activado_at' => now(),
    ]);

    $this->actingAs($this->testTenantAdmin)
        ->get('http://'.$this->testTenantHost.'/comunicaciones/bot-ia')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('comunicaciones/bot-ia/index')
            ->where('bot_ia.activo', true)
            ->where('announcement', null));
});

it('muestra la novedad solo cuando el add-on bot ia no está contratado', function (): void {
    $announcement = \App\Models\BotIaAnnouncement::query()->create([
        'title' => 'Activa el Asistente IA en tu clínica',
        'badge' => \App\Models\BotIaAnnouncement::BADGE_NUEVO,
        'bullet_1' => 'Responde consultas 24/7 por WhatsApp.',
        'bullet_2' => 'Registra clientes y agenda citas desde el chat.',
        'bullet_3' => 'Precio desde S/. 15 en tu renovación de plan.',
        'is_active' => true,
        'published_at' => now(),
    ]);

    $this->actingAs($this->testTenantAdmin)
        ->get('http://'.$this->testTenantHost.'/comunicaciones/bot-ia')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('comunicaciones/bot-ia/index')
            ->where('bot_ia.activo', false)
            ->where('announcement.id', $announcement->id)
            ->has('activation_contact.whatsapp_url')
            ->where('activation_contact.whatsapp_display', '976 809 804'));
});

it('incluye mensaje de activación por whatsapp con el nombre de la clínica', function (): void {
    $this->actingAs($this->testTenantAdmin)
        ->get('http://'.$this->testTenantHost.'/comunicaciones/bot-ia')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('comunicaciones/bot-ia/index')
            ->where('bot_ia.activo', false)
            ->where(
                'activation_contact.whatsapp_url',
                fn (string $url) => str_contains($url, 'https://wa.me/51976809804?text=')
                    && str_contains(urldecode($url), (string) $this->testTenant->nombre_comercial)
            ));
});

it('permite ver la página promocional de bot ia con permiso de cola saliente', function (): void {
    \App\Models\BotIaAnnouncement::query()->create([
        'title' => 'Activa el Asistente IA en tu clínica',
        'badge' => \App\Models\BotIaAnnouncement::BADGE_NUEVO,
        'bullet_1' => 'Responde consultas 24/7 por WhatsApp.',
        'bullet_2' => 'Registra clientes y agenda citas desde el chat.',
        'bullet_3' => 'Precio desde S/. 15 en tu renovación de plan.',
        'is_active' => true,
        'published_at' => now(),
    ]);

    $recepcionista = User::factory()->create([
        'email' => 'recep-'.$this->testTenantSlug.'@test.local',
        'tenant_id' => $this->testTenant->id,
        'password' => Hash::make('password'),
        'is_active' => true,
        'must_change_password' => false,
        'email_verified_at' => now(),
    ]);
    $recepcionista->assignRole('recepcionista');

    $this->actingAs($recepcionista)
        ->get('http://'.$this->testTenantHost.'/comunicaciones/bot-ia')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('comunicaciones/bot-ia/index')
            ->where('bot_ia.activo', false)
            ->has('announcement'));
});

it('muestra vista bloqueada de asistente ia cuando no está contratado', function (): void {
    $this->actingAs($this->testTenantAdmin)
        ->get('http://'.$this->testTenantHost.'/comunicaciones/bot-ia')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('comunicaciones/bot-ia/index')
            ->where('bot_ia.activo', false)
            ->has('announcement')
            ->where('announcement.title', 'Tu recepción virtual en WhatsApp, 24/7'));
});

it('permite acceder a asistente ia sin permiso explícito si administra la clínica', function (): void {
    $this->subscription->update([
        'bot_ia_activo' => true,
        'bot_ia_precio_mensual' => '15.00',
        'bot_ia_activado_at' => now(),
    ]);

    $this->testTenantAdmin->revokePermissionTo([
        'comunicaciones-bot-ia.view',
        'comunicaciones-bot-ia.manage',
    ]);

    $this->actingAs($this->testTenantAdmin)
        ->get('http://'.$this->testTenantHost.'/comunicaciones/bot-ia')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('comunicaciones/bot-ia/index')
            ->where('bot_ia.activo', true)
            ->where('activation_contact', null));
});

it('rechaza toggle bot ia en suscripción cancelada', function (): void {
    $this->subscription->update(['estado' => 'cancelled', 'cancelled_at' => now()]);

    $this->actingAs($this->superadmin)
        ->post('http://127.0.0.1/plataforma/suscripciones/'.$this->subscription->id.'/toggle-bot-ia', [
            'activo' => true,
        ])
        ->assertSessionHasErrors('activo');
});
