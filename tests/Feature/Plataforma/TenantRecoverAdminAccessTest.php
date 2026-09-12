<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\Tenancy\TenantAdminAccessRecoveredNotification;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Recuperar acceso admin requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
    $this->testTenant->update([
        'telefono' => '999888777',
        'estado' => 'active',
    ]);
    $this->superadmin = $this->createTestSuperadmin();
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('actualiza el admin, deja enlace copiable y notifica correo y whatsapp', function (): void {
    Notification::fake();

    $this->mock(PlatformWhatsAppMessenger::class, function ($mock): void {
        $mock->shouldReceive('isReady')->once()->andReturn(true);
        $mock->shouldReceive('sendText')->once();
    });

    $newEmail = 'nuevo-admin-'.$this->testTenantSlug.'@test.local';

    $this->actingAs($this->superadmin)
        ->post('http://127.0.0.1/plataforma/tenants/'.$this->testTenant->id.'/recover-admin-access', [
            'email' => $newEmail,
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'must_change_password' => true,
            'notify_client' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success')
        ->assertSessionHas('copy_url')
        ->assertSessionHas('info');

    $admin = User::query()->findOrFail($this->testTenantAdmin->id);
    expect($admin->email)->toBe($newEmail)
        ->and(Hash::check('Password1!', $admin->password))->toBeTrue()
        ->and($admin->bootstrap_login_token)->not->toBeNull();

    $copyUrl = session('copy_url');
    expect($copyUrl)->toBeString()->toContain('/auth/bienvenida/');

    Notification::assertSentOnDemand(
        TenantAdminAccessRecoveredNotification::class,
        function (string $channel, mixed $notifiable, TenantAdminAccessRecoveredNotification $notification) use ($newEmail): bool {
            return $channel === 'mail'
                && $notifiable->routes['mail'] === $newEmail
                && $notification->adminEmail === $newEmail
                && is_string($notification->bootstrapUrl)
                && $notification->bootstrapUrl !== '';
        },
    );
});

it('no notifica al cliente si se desmarca el envío', function (): void {
    Notification::fake();

    $this->mock(PlatformWhatsAppMessenger::class, function ($mock): void {
        $mock->shouldReceive('isReady')->never();
        $mock->shouldReceive('sendText')->never();
    });

    $this->actingAs($this->superadmin)
        ->post('http://127.0.0.1/plataforma/tenants/'.$this->testTenant->id.'/recover-admin-access', [
            'email' => $this->testTenant->email_admin,
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'notify_client' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('copy_url')
        ->assertSessionMissing('info');

    Notification::assertNothingSent();
});
