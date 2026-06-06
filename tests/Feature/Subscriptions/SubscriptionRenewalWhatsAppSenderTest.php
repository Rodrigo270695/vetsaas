<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionRenewalReminder;
use App\Models\Tenant;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Services\Subscriptions\SubscriptionRenewalWhatsAppSender;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Recordatorios de renovación requieren PostgreSQL.');
    }
});

it('envía manualmente el link aunque no sea día de aviso', function (): void {
    $tenantSlug = 'manual-'.Str::lower(Str::random(6));

    $messenger = Mockery::mock(PlatformWhatsAppMessenger::class);
    $messenger->shouldReceive('isReady')->andReturn(true);
    $messenger->shouldReceive('sendText')
        ->once()
        ->withArgs(fn (string $chatId, string $text): bool => str_contains($chatId, '@c.us')
            && str_contains($text, 'vence el')
            && str_contains($text, 'tenant='.$tenantSlug))
        ->andReturn(['id' => 'wa-manual-1']);
    app()->instance(PlatformWhatsAppMessenger::class, $messenger);

    $plan = Plan::query()->create([
        'codigo' => 'MAN-'.Str::lower(Str::random(4)),
        'nombre' => 'Plan manual',
        'descripcion' => null,
        'precio_mensual' => '99.90',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => 80,
        'es_publico' => true,
        'activo' => true,
    ]);

    $tenant = Tenant::query()->create([
        'slug' => $tenantSlug,
        'schema_name' => 'vet_'.Str::lower(Str::random(6)),
        'razon_social' => 'Clínica Manual',
        'nombre_comercial' => 'Clínica Manual',
        'telefono' => '987654321',
        'email_admin' => Str::lower(Str::random(8)).'@manual.test',
        'estado' => 'active',
    ]);

    $subscription = Subscription::withoutEvents(function () use ($tenant, $plan) {
        return Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'estado' => 'active',
            'ciclo' => 'mensual',
            'proximo_cobro_at' => now()->addDays(12)->startOfDay(),
            'precio_pactado' => '99.90',
        ]);
    });

    $result = app(SubscriptionRenewalWhatsAppSender::class)->sendManual($subscription);

    expect($result['ok'])->toBeTrue()
        ->and(SubscriptionRenewalReminder::query()->count())->toBe(1);

    $reminder = SubscriptionRenewalReminder::query()->first();
    expect($reminder?->reminder_kind)->toBe(SubscriptionRenewalReminder::KIND_MANUAL);
});

it('usa texto de vencido cuando el período ya pasó', function (): void {
    $tenantSlug = 'expired-'.Str::lower(Str::random(6));

    $messenger = Mockery::mock(PlatformWhatsAppMessenger::class);
    $messenger->shouldReceive('isReady')->andReturn(true);
    $messenger->shouldReceive('sendText')
        ->once()
        ->withArgs(fn (string $chatId, string $text): bool => str_contains($text, 'venció el'))
        ->andReturn(['id' => 'wa-expired-1']);
    app()->instance(PlatformWhatsAppMessenger::class, $messenger);

    $plan = Plan::query()->create([
        'codigo' => 'EXP-'.Str::lower(Str::random(4)),
        'nombre' => 'Plan expired',
        'descripcion' => null,
        'precio_mensual' => '59.90',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => 81,
        'es_publico' => true,
        'activo' => true,
    ]);

    $tenant = Tenant::query()->create([
        'slug' => $tenantSlug,
        'schema_name' => 'vet_'.Str::lower(Str::random(6)),
        'razon_social' => 'Clínica Vencida',
        'nombre_comercial' => 'Clínica Vencida',
        'telefono' => '912345678',
        'email_admin' => Str::lower(Str::random(8)).'@exp.test',
        'estado' => 'active',
    ]);

    $subscription = Subscription::withoutEvents(function () use ($tenant, $plan) {
        return Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'estado' => 'grace',
            'ciclo' => 'mensual',
            'proximo_cobro_at' => now()->subDays(3)->startOfDay(),
            'precio_pactado' => '59.90',
        ]);
    });

    $result = app(SubscriptionRenewalWhatsAppSender::class)->sendManual($subscription);

    expect($result['ok'])->toBeTrue();
});

it('rechaza envío manual a suscripción cancelada', function (): void {
    $messenger = Mockery::mock(PlatformWhatsAppMessenger::class);
    $messenger->shouldReceive('isReady')->never();
    $messenger->shouldReceive('sendText')->never();
    app()->instance(PlatformWhatsAppMessenger::class, $messenger);

    $plan = Plan::query()->create([
        'codigo' => 'CAN-'.Str::lower(Str::random(4)),
        'nombre' => 'Plan cancel',
        'descripcion' => null,
        'precio_mensual' => '59.90',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => 82,
        'es_publico' => true,
        'activo' => true,
    ]);

    $tenant = Tenant::query()->create([
        'slug' => 'cancel-'.Str::lower(Str::random(6)),
        'schema_name' => 'vet_'.Str::lower(Str::random(6)),
        'razon_social' => 'Clínica Cancel',
        'nombre_comercial' => 'Clínica Cancel',
        'telefono' => '987654321',
        'email_admin' => Str::lower(Str::random(8)).'@can.test',
        'estado' => 'active',
    ]);

    $subscription = Subscription::withoutEvents(function () use ($tenant, $plan) {
        return Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'estado' => 'cancelled',
            'ciclo' => 'mensual',
            'proximo_cobro_at' => now()->addDays(5),
            'precio_pactado' => '59.90',
            'cancelled_at' => now(),
        ]);
    });

    $result = app(SubscriptionRenewalWhatsAppSender::class)->sendManual($subscription);

    expect($result['ok'])->toBeFalse()
        ->and($result['error'])->toContain('cancelada');
});
