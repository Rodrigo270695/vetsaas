<?php

declare(strict_types=1);

use App\Models\ClinicBotKnowledge;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Clinic bot knowledge requiere PostgreSQL.');
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
            'bot_ia_activo' => true,
            'bot_ia_precio_mensual' => '15.00',
            'bot_ia_activado_at' => now(),
        ]);
    });
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('muestra la base de conocimiento en asistente ia', function (): void {
    ClinicBotKnowledge::query()->create([
        'section' => 'faq',
        'slug' => 'faq-horario',
        'title' => '¿Atienden sábados?',
        'content' => 'Sí, de 9 a 13 h.',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($this->testTenantAdmin)
        ->get('http://'.$this->testTenantHost.'/comunicaciones/bot-ia')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('comunicaciones/bot-ia/index')
            ->has('knowledge.data', 1)
            ->where('knowledge.data.0.title', '¿Atienden sábados?'));
});

it('crea una entrada de conocimiento', function (): void {
    $this->actingAs($this->testTenantAdmin)
        ->post('http://'.$this->testTenantHost.'/comunicaciones/bot-ia/conocimiento', [
            'section' => 'politica',
            'slug' => 'politica-cancelacion',
            'title' => 'Cancelación de citas',
            'content' => 'Avisar con 24 h de anticipación.',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(ClinicBotKnowledge::query()->count())->toBe(1);
});

it('actualiza una entrada de conocimiento', function (): void {
    $entry = ClinicBotKnowledge::query()->create([
        'section' => 'faq',
        'slug' => 'faq-pago',
        'title' => 'Formas de pago',
        'content' => 'Efectivo y tarjeta.',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($this->testTenantAdmin)
        ->put('http://'.$this->testTenantHost.'/comunicaciones/bot-ia/conocimiento/'.$entry->id, [
            'section' => 'faq',
            'slug' => 'faq-pago',
            'title' => 'Formas de pago',
            'content' => 'Efectivo, tarjeta y Yape.',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($entry->fresh()->content)->toBe('Efectivo, tarjeta y Yape.');
});

it('elimina una entrada de conocimiento', function (): void {
    $entry = ClinicBotKnowledge::query()->create([
        'section' => 'contacto',
        'slug' => 'contacto-direccion',
        'title' => 'Dirección',
        'content' => 'Av. Principal 123.',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($this->testTenantAdmin)
        ->delete('http://'.$this->testTenantHost.'/comunicaciones/bot-ia/conocimiento/'.$entry->id)
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(ClinicBotKnowledge::query()->count())->toBe(0);
});

it('rechaza crear conocimiento si el add-on no está activo', function (): void {
    $this->subscription->update([
        'bot_ia_activo' => false,
        'bot_ia_activado_at' => null,
    ]);

    $this->actingAs($this->testTenantAdmin)
        ->post('http://'.$this->testTenantHost.'/comunicaciones/bot-ia/conocimiento', [
            'section' => 'faq',
            'slug' => 'faq-test',
            'title' => 'Test',
            'content' => 'Contenido',
            'is_active' => true,
        ])
        ->assertForbidden();
});
