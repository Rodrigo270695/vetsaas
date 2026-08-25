<?php

declare(strict_types=1);

use App\Models\PlatformSupportThread;
use App\Services\Chat\PlatformSupportChatService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Chat soporte plataforma requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
    $this->superadmin = $this->createTestSuperadmin();
});

it('lista plantillas builtin o de BD', function (): void {
    $service = app(PlatformSupportChatService::class);
    $templates = $service->listTemplates(true);

    expect($templates)->not->toBeEmpty()
        ->and($templates[0])->toHaveKeys(['id', 'label', 'body']);
});

it('crea plantillas personalizadas cuando la tabla existe', function (): void {
    if (! Schema::hasTable('platform_support_templates')) {
        $this->markTestSkipped('Migración platform_support_templates no aplicada');
    }

    $service = app(PlatformSupportChatService::class);
    $created = $service->upsertTemplate(
        null,
        'Aviso QR',
        'Por favor escanea el QR de nuevo.',
        $this->superadmin,
        10,
    );

    expect($created['label'])->toBe('Aviso QR');
    expect(collect($service->listTemplates(false))->pluck('id'))->toContain($created['id']);
});

it('asigna agente, silencia y expone SLA en listTenants', function (): void {
    if (! Schema::hasColumn('platform_support_threads', 'assigned_agent_id')) {
        $this->markTestSkipped('Columnas ops no aplicadas');
    }

    $tenant = $this->testTenant;

    PlatformSupportThread::query()->create([
        'tenant_id' => $tenant->id,
        'conversation_id' => (string) Str::uuid(),
        'support_user_id' => (string) Str::uuid(),
        'from_clinic' => true,
        'clinic_waiting_since' => now()->subMinutes(12),
        'last_message_at' => now(),
        'last_preview' => 'Hola',
    ]);

    $service = app(PlatformSupportChatService::class);
    $assigned = $service->assignAgent($tenant, (string) $this->superadmin->id);
    expect($assigned['assigned_agent_id'])->toBe((string) $this->superadmin->id);

    expect($service->setMuted($tenant, true)['muted'])->toBeTrue();

    $row = collect($service->listTenants('all'))->firstWhere('id', (string) $tenant->id);
    expect($row)->not->toBeNull()
        ->and($row['thread']['needs_response'] ?? false)->toBeTrue()
        ->and($row['thread']['muted'] ?? false)->toBeTrue()
        ->and($row['thread']['waiting_minutes'] ?? null)->toBeGreaterThanOrEqual(11);
});

it('guarda notas internas', function (): void {
    if (! Schema::hasTable('platform_support_notes')) {
        $this->markTestSkipped('Tabla notes no aplicada');
    }

    $service = app(PlatformSupportChatService::class);
    $note = $service->addNote($this->testTenant, $this->superadmin, 'Cliente pedirá factura.');

    expect($note['body'])->toBe('Cliente pedirá factura.');
    expect($service->listNotes($this->testTenant))->toHaveCount(1);
});

it('endpoint templates responde autenticado', function (): void {
    $this->getJson('/plataforma/chat-soporte/templates')->assertUnauthorized();

    $this->actingAs($this->superadmin)
        ->getJson('/plataforma/chat-soporte/templates')
        ->assertOk()
        ->assertJsonStructure(['templates']);
});
