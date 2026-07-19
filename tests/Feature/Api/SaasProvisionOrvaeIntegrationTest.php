<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\TenantRolesSeeder;
use Tests\Support\TenantRbac;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\OrvaeProvisionTestHelper;
use Tests\Support\TenantMigrateTestGuards;

beforeEach(function (): void {
    TenantMigrateTestGuards::guardIfUnsafePgsql($this);

    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Provisión Orvae requiere PostgreSQL.');
    }

    $this->secret = 'test-orvae-hmac-'.Str::random(8);
    config([
        'orvae.provision.hmac_secret' => $this->secret,
        'orvae.provision.max_skew_seconds' => 300,
        'orvae.tenant.domain' => 'vetsaas.test',
        'orvae.tenant.scheme' => 'http',
    ]);

    $this->seed(PermissionsSeeder::class);
    $this->seed(PlansAndFeaturesSeeder::class);
    $this->plan = Plan::query()->where('codigo', 'starter')->where('activo', true)->firstOrFail();
});

afterEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        return;
    }

    if (isset($this->createdSlug)) {
        $tenant = Tenant::query()->where('slug', $this->createdSlug)->first();
        if ($tenant !== null) {
            SubscriptionPayment::query()->where('tenant_id', $tenant->id)->delete();
            Subscription::query()->where('tenant_id', $tenant->id)->delete();
            User::query()->where('tenant_id', $tenant->id)->forceDelete();
            DB::statement('DROP SCHEMA IF EXISTS "'.$tenant->schema_name.'" CASCADE');
            $tenant->forceDelete();
        }
    }

    DB::table('provision_idempotency_keys')->where('key', 'like', 'test-orvae-prov-%')->delete();
});

it('provisiona tenant con payload anidado estilo Orvae checkout', function (): void {
    $slug = 'clinica-orvae-'.Str::lower(Str::random(5));
    $this->createdSlug = $slug;

    $payload = [
        'external_order_id' => 'order-'.Str::uuid(),
        'order_number' => 'ORV-'.Str::upper(Str::random(6)),
        'customer' => [
            'email' => 'admin+'.$slug.'@orvae.test',
            'first_name' => 'Ana',
            'last_name' => 'Veterinaria',
            'phone' => '999111222',
        ],
        'tenant' => [
            'name' => 'Clínica '.$slug,
            'slug' => $slug,
        ],
        'subscription' => [
            'plan_slug' => 'starter',
            'amount_paid' => '149.00',
            'currency' => 'PEN',
            'payment_method' => 'culqi',
            'payment_reference' => 'chg_test_001',
            'started_at' => now()->toIso8601String(),
        ],
    ];

    $signed = OrvaeProvisionTestHelper::signedJsonRequest(
        $payload,
        $this->secret,
        'test-orvae-prov-'.Str::random(8),
    );

    $response = $this->call(
        'POST',
        '/api/internal/saas/provision',
        [],
        [],
        [],
        $signed['server'],
        $signed['body'],
    );

    $response->assertCreated()
        ->assertJsonPath('tenant.slug', $slug)
        ->assertJsonStructure(['login_url', 'academy_url', 'tenant_slug']);

    $tenant = Tenant::query()->where('slug', $slug)->first();
    expect($tenant)->not->toBeNull();
    expect($tenant->email_admin)->toBe('admin+'.$slug.'@orvae.test');

    $user = User::query()
        ->where('tenant_id', $tenant->id)
        ->where('email', $tenant->email_admin)
        ->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('admin_clinica'))->toBeTrue();
});

it('es idempotente con la misma X-Idempotency-Key', function (): void {
    $slug = 'idem-orvae-'.Str::lower(Str::random(5));
    $this->createdSlug = $slug;
    $idempotency = 'test-orvae-prov-idem-'.Str::random(6);

    $payload = [
        'external_order_id' => 'order-idem-'.Str::uuid(),
        'plan_slug' => 'starter',
        'tenant_slug' => $slug,
        'razon_social' => 'Clínica Idempotencia',
        'admin_nombres' => 'Luis',
        'admin_apellidos' => 'Prueba',
        'admin_email' => 'idem+'.$slug.'@test.local',
        'admin_password' => 'ClaveSegura123',
    ];

    $signed = OrvaeProvisionTestHelper::signedJsonRequest($payload, $this->secret, $idempotency);

    $first = $this->call(
        'POST',
        '/api/internal/saas/provision',
        [],
        [],
        [],
        $signed['server'],
        $signed['body'],
    );
    $first->assertCreated();

    $second = $this->call(
        'POST',
        '/api/internal/saas/provision',
        [],
        [],
        [],
        $signed['server'],
        $signed['body'],
    );
    $second->assertCreated();
    expect($second->json('tenant.slug'))->toBe($first->json('tenant.slug'));

    expect(Tenant::query()->where('slug', $slug)->count())->toBe(1);
});

it('renueva suscripción existente sin crear otro tenant', function (): void {
    $slug = 'renew-orvae-'.Str::lower(Str::random(5));
    $this->createdSlug = $slug;

    $provisionPayload = [
        'external_order_id' => 'order-first-'.Str::uuid(),
        'plan_slug' => 'starter',
        'tenant_slug' => $slug,
        'razon_social' => 'Clínica Renew Test',
        'admin_nombres' => 'Ana',
        'admin_apellidos' => 'Renew',
        'admin_email' => 'renew+'.$slug.'@test.local',
        'admin_password' => 'ClaveSegura123',
        'payment' => [
            'monto' => 149,
            'moneda' => 'PEN',
            'pasarela' => 'culqi',
            'transaction_id' => 'chg_first_001',
        ],
    ];

    $signedProvision = OrvaeProvisionTestHelper::signedJsonRequest(
        $provisionPayload,
        $this->secret,
        'test-orvae-prov-renew-'.Str::random(6),
    );

    $this->call(
        'POST',
        '/api/internal/saas/provision',
        [],
        [],
        [],
        $signedProvision['server'],
        $signedProvision['body'],
    )->assertCreated();

    $tenant = Tenant::query()->where('slug', $slug)->firstOrFail();
    $subscription = Subscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $originalEnd = $subscription->current_period_end?->toIso8601String();

    $renewPayload = [
        'external_order_id' => 'order-renew-'.Str::uuid(),
        'order_number' => 'ORV-RENEW-001',
        'tenant_slug' => $slug,
        'plan_slug' => 'starter',
        'ciclo' => 'mensual',
        'payment' => [
            'monto' => 149,
            'moneda' => 'PEN',
            'pasarela' => 'culqi',
            'transaction_id' => 'chg_renew_001',
            'pagado_at' => now()->toIso8601String(),
        ],
    ];

    $signedRenew = OrvaeProvisionTestHelper::signedJsonRequest(
        $renewPayload,
        $this->secret,
        'test-orvae-renew-'.Str::random(6),
    );

    $renewResponse = $this->call(
        'POST',
        '/api/internal/saas/renew',
        [],
        [],
        [],
        $signedRenew['server'],
        $signedRenew['body'],
    );

    $renewResponse->assertOk()
        ->assertJsonPath('renewed', true)
        ->assertJsonPath('tenant.slug', $slug);

    expect(Tenant::query()->where('slug', $slug)->count())->toBe(1);

    $subscription->refresh();
    expect($subscription->estado)->toBe('active')
        ->and($subscription->current_period_end?->toIso8601String())->not->toBe($originalEnd);

    expect(SubscriptionPayment::query()->where('tenant_id', $tenant->id)->count())->toBe(2);

    $tenant->refresh();
    expect($tenant->estado)->toBe('active');
});
