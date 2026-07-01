<?php

declare(strict_types=1);

use App\Models\ClinicBotConversation;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TenantWhatsAppSession;
use App\Services\OpenWa\OpenWaClient;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('ClinicBot webhook requiere PostgreSQL.');
    }

    config([
        'bot-ia.enabled' => true,
        'bot-ia.webhook_secret' => 'test-clinic-bot-secret',
        'bot-ia.openai_api_key' => 'sk-test',
    ]);

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();

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

    Subscription::withoutEvents(function (): void {
        Subscription::query()->create([
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

    $this->waSession = TenantWhatsAppSession::query()->create([
        'tenant_id' => $this->testTenant->id,
        'openwa_session_id' => 'session-clinic-bot-001',
        'openwa_session_name' => $this->testTenant->slug,
        'status' => 'ready',
        'phone' => '51976709811',
    ]);
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('responde a un mensaje entrante vía webhook clinic-bot', function (): void {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Atendemos los lunes de 9 a 10 am.']],
            ],
        ]),
    ]);

    $this->mock(OpenWaClient::class, function ($mock): void {
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('sendText')
            ->once()
            ->with('session-clinic-bot-001', '51999999999@c.us', 'Atendemos los lunes de 9 a 10 am.')
            ->andReturn(['messageId' => 'msg-1']);
    });

    $this->postJson('http://127.0.0.1/api/webhooks/clinic-bot', [
        'event' => 'message.received',
        'sessionId' => 'session-clinic-bot-001',
        'data' => [
            'id' => 'wamid.test001',
            'body' => '¿Qué horarios atienden?',
            'from' => '51999999999@c.us',
            'fromMe' => false,
            'type' => 'chat',
        ],
    ], [
        'X-Webhook-Secret' => 'test-clinic-bot-secret',
    ])->assertOk()->assertJson(['ok' => true, 'replied' => true]);

    app(TenantManager::class)->runForSlug($this->testTenant->slug, function (): void {
        expect(ClinicBotConversation::query()->count())->toBe(1);
    });
});

it('rechaza webhook sin secreto cuando está configurado', function (): void {
    $this->postJson('http://127.0.0.1/api/webhooks/clinic-bot', [
        'event' => 'message.received',
        'sessionId' => 'session-clinic-bot-001',
        'data' => ['body' => 'hola', 'from' => '51999999999@c.us', 'fromMe' => false],
    ])->assertUnauthorized();
});
