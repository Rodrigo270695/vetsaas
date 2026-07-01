<?php

declare(strict_types=1);

use App\Models\ClinicBotConversation;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Clinic bot conversations requiere PostgreSQL.');
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

it('lista conversaciones en asistente ia', function (): void {
    ClinicBotConversation::query()->create([
        'phone' => '51999999999',
        'wa_chat_id' => '51999999999@c.us',
        'client_name' => 'Juan Pérez',
        'messages' => [
            ['role' => 'user', 'content' => '¿Horarios?'],
            ['role' => 'assistant', 'content' => 'Lunes 9-10 am.'],
        ],
        'turn_count' => 1,
        'bot_active' => true,
        'bot_paused_manually' => false,
        'last_message_at' => now(),
    ]);

    $this->actingAs($this->testTenantAdmin)
        ->get('http://'.$this->testTenantHost.'/comunicaciones/bot-ia')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('comunicaciones/bot-ia/index')
            ->has('conversations.data', 1)
            ->where('conversations.data.0.phone', '51999999999')
            ->where('conversation_stats.total', 1));
});

it('pausa y reanuda el asistente para un chat', function (): void {
    $conversation = ClinicBotConversation::query()->create([
        'phone' => '51988887777',
        'wa_chat_id' => '51988887777@c.us',
        'client_name' => null,
        'messages' => [],
        'turn_count' => 0,
        'bot_active' => true,
        'bot_paused_manually' => false,
        'last_message_at' => now(),
    ]);

    $this->actingAs($this->testTenantAdmin)
        ->post('http://'.$this->testTenantHost.'/comunicaciones/bot-ia/conversaciones/'.$conversation->id.'/pause')
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($conversation->fresh())
        ->bot_active->toBeFalse()
        ->bot_paused_manually->toBeTrue();

    $this->actingAs($this->testTenantAdmin)
        ->post('http://'.$this->testTenantHost.'/comunicaciones/bot-ia/conversaciones/'.$conversation->id.'/resume')
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($conversation->fresh())
        ->bot_active->toBeTrue()
        ->bot_paused_manually->toBeFalse();
});
