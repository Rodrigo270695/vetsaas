<?php

declare(strict_types=1);

use App\Models\ChatConversation;
use App\Models\ChatParticipant;
use App\Models\ImpersonationAuditLog;
use App\Models\User;
use App\Services\Chat\TenantChatService;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Impersonación requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
    $this->superadmin = $this->createTestSuperadmin();
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('inicia impersonación con Inertia location hacia el subdominio del tenant', function (): void {
    $this->actingAs($this->superadmin);

    $response = $this->post(
        'http://127.0.0.1/plataforma/tenants/'.$this->testTenant->id.'/impersonate',
        [],
        [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ],
    );

    $response->assertStatus(409);
    $location = (string) $response->headers->get('X-Inertia-Location');
    expect($location)->toContain($this->testTenantHost);
    expect($location)->toContain('/impersonate/accept?token=');
});

it('acepta el token y establece sesión de impersonación en el tenant', function (): void {
    $this->actingAs($this->superadmin);

    $start = $this->post(
        'http://127.0.0.1/plataforma/tenants/'.$this->testTenant->id.'/impersonate',
        [],
        ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'],
    );

    $location = (string) $start->headers->get('X-Inertia-Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $token = (string) ($query['token'] ?? '');
    expect($token)->not->toBe('');

    $accept = $this->get('http://'.$this->testTenantHost.'/impersonate/accept?token='.$token);

    $accept->assertRedirect(route('dashboard'));
    $accept->assertSessionHas('tenant_impersonation.tenant_id', (string) $this->testTenant->id);
    $accept->assertSessionHas('tenant_impersonation.audit_log_id');

    $auditId = session('tenant_impersonation.audit_log_id');
    expect(ImpersonationAuditLog::query()->whereKey($auditId)->exists())->toBeTrue();
    expect(ImpersonationAuditLog::query()->find($auditId)?->ended_at)->toBeNull();
});

it('permite al superadmin ver el dashboard del tenant tras entrar como soporte', function (): void {
    $this->actingAs($this->superadmin);

    $start = $this->post(
        'http://127.0.0.1/plataforma/tenants/'.$this->testTenant->id.'/impersonate',
        [],
        ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'],
    );

    $location = (string) $start->headers->get('X-Inertia-Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    $this->get('http://'.$this->testTenantHost.'/impersonate/accept?token='.($query['token'] ?? ''))
        ->assertRedirect(route('dashboard'));

    $dashboard = $this->get('http://'.$this->testTenantHost.'/dashboard');

    $dashboard->assertOk();
    $dashboard->assertInertia(fn ($page) => $page
        ->component('dashboard/index')
        ->where('auth.roles', fn ($roles) => collect($roles)->contains('superadmin'))
    );
});

it('permite al superadmin ver stock y caja en modo soporte', function (): void {
    $this->actingAs($this->superadmin);

    $start = $this->post(
        'http://127.0.0.1/plataforma/tenants/'.$this->testTenant->id.'/impersonate',
        [],
        ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'],
    );

    $location = (string) $start->headers->get('X-Inertia-Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    $this->get('http://'.$this->testTenantHost.'/impersonate/accept?token='.($query['token'] ?? ''))
        ->assertRedirect(route('dashboard'));

    $this->get('http://'.$this->testTenantHost.'/inventario/stock')
        ->assertOk();

    $this->get('http://'.$this->testTenantHost.'/caja/sesiones')
        ->assertOk();
});

it('rechaza token expirado o reutilizado', function (): void {
    Cache::put('tenant_impersonate:fake-token-'.str_repeat('a', 48), [
        'superadmin_id' => (string) $this->superadmin->id,
        'tenant_id' => (string) $this->testTenant->id,
        'central_origin' => 'http://127.0.0.1:8000',
    ], now()->addMinutes(5));

    $token = 'fake-token-'.str_repeat('a', 48);

    $first = $this->get('http://'.$this->testTenantHost.'/impersonate/accept?token='.$token);
    $first->assertRedirect(route('dashboard'));

    $second = $this->get('http://'.$this->testTenantHost.'/impersonate/accept?token='.$token);
    $second->assertRedirect(route('login'));
    $second->assertSessionHas('error');
});

it('sale del modo soporte con Inertia location al login central', function (): void {
    $this->actingAs($this->superadmin);

    $start = $this->post(
        'http://127.0.0.1/plataforma/tenants/'.$this->testTenant->id.'/impersonate',
        [],
        ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'],
    );
    $location = (string) $start->headers->get('X-Inertia-Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $this->get('http://'.$this->testTenantHost.'/impersonate/accept?token='.($query['token'] ?? ''));
    $auditId = session('tenant_impersonation.audit_log_id');
    expect($auditId)->toBeString()->not->toBe('');

    $leave = $this->post(
        'http://'.$this->testTenantHost.'/impersonate/leave',
        [],
        ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'],
    );

    $leave->assertStatus(409);
    expect((string) $leave->headers->get('X-Inertia-Location'))->toContain('/login');
    expect(ImpersonationAuditLog::query()->find($auditId)?->ended_at)->not->toBeNull();
});

it('en modo soporte lista los chats del equipo, no solo Soporte VetSaaS', function (): void {
    $peer = User::factory()->create([
        'tenant_id' => $this->testTenant->id,
        'is_active' => true,
        'must_change_password' => false,
        'email_verified_at' => now(),
    ]);

    $teamConversationId = app(TenantManager::class)->runForSlug(
        $this->testTenant->slug,
        function () use ($peer): string {
            $conversation = ChatConversation::query()->create([
                'type' => ChatConversation::TYPE_DIRECT,
                'kind' => ChatConversation::KIND_TEAM,
                'direct_key' => TenantChatService::directKey((string) $this->testTenantAdmin->id, (string) $peer->id),
                'created_by_id' => $this->testTenantAdmin->id,
            ]);

            foreach ([$this->testTenantAdmin->id, $peer->id] as $uid) {
                ChatParticipant::query()->create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $uid,
                    'joined_at' => now(),
                ]);
            }

            return (string) $conversation->id;
        },
    );

    $this->actingAs($this->superadmin);

    $start = $this->post(
        'http://127.0.0.1/plataforma/tenants/'.$this->testTenant->id.'/impersonate',
        [],
        ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'],
    );
    $location = (string) $start->headers->get('X-Inertia-Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $this->get('http://'.$this->testTenantHost.'/impersonate/accept?token='.($query['token'] ?? ''))
        ->assertRedirect(route('dashboard'));

    $this->get('http://'.$this->testTenantHost.'/comunicaciones/chat')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('comunicaciones/chat/index')
            ->has('conversations')
            ->where(
                'conversations',
                fn ($rows) => collect($rows)->contains(
                    fn ($row) => (string) ($row['id'] ?? '') === $teamConversationId
                        && ($row['can_write'] ?? true) === false,
                ),
            ));
});
