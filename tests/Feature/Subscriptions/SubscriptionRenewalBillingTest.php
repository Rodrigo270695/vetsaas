<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\Subscriptions\SubscriptionRenewalBilling;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class);

it('suma precio pactado y bot ia para renovación', function (): void {
    $plan = Plan::query()->create([
        'codigo' => 'free',
        'nombre' => 'Free',
        'descripcion' => null,
        'precio_mensual' => '0',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => 1,
        'es_publico' => true,
        'activo' => true,
    ]);

    $tenant = Tenant::query()->create([
        'slug' => 'bill-'.Str::lower(Str::random(6)),
        'schema_name' => 'vet_'.Str::lower(Str::random(6)),
        'razon_social' => 'Clínica Billing',
        'email_admin' => Str::lower(Str::random(8)).'@bill.test',
        'estado' => 'active',
    ]);

    $subscription = Subscription::withoutEvents(function () use ($tenant, $plan): Subscription {
        return Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'estado' => 'active',
            'ciclo' => 'mensual',
            'precio_pactado' => '99.90',
            'bot_ia_activo' => true,
            'bot_ia_precio_mensual' => '15.00',
            'bot_ia_activado_at' => now(),
        ]);
    });

    expect(SubscriptionRenewalBilling::planAmount($subscription))->toBe(99.90)
        ->and(SubscriptionRenewalBilling::botIaAmount($subscription))->toBe(15.00)
        ->and(SubscriptionRenewalBilling::totalAmount($subscription))->toBe(114.90)
        ->and(SubscriptionRenewalBilling::isBillable($subscription))->toBeTrue();
});

it('expone renewal billing por api interna', function (): void {
    $secret = 'test-hmac-secret-'.Str::random(8);
    config(['orvae.provision.hmac_secret' => $secret]);

    $plan = Plan::query()->create([
        'codigo' => 'free',
        'nombre' => 'Free',
        'descripcion' => null,
        'precio_mensual' => '0',
        'precio_anual' => null,
        'trial_days' => 0,
        'orden' => 1,
        'es_publico' => true,
        'activo' => true,
    ]);

    $tenant = Tenant::query()->create([
        'slug' => 'api-bill-'.Str::lower(Str::random(6)),
        'schema_name' => 'vet_'.Str::lower(Str::random(6)),
        'razon_social' => 'Clínica API Billing',
        'email_admin' => Str::lower(Str::random(8)).'@api.test',
        'estado' => 'active',
    ]);

    Subscription::withoutEvents(function () use ($tenant, $plan): void {
        Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'estado' => 'active',
            'ciclo' => 'mensual',
            'precio_pactado' => '99.90',
            'bot_ia_activo' => true,
            'bot_ia_precio_mensual' => '15.00',
        ]);
    });

    $timestamp = (string) time();
    $body = '';
    $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);

    $this->getJson(
        '/api/internal/saas/tenants/'.$tenant->slug.'/renewal-billing',
        [
            'X-Orvae-Timestamp' => $timestamp,
            'X-Orvae-Signature' => $signature,
        ],
    )
        ->assertOk()
        ->assertJsonPath('applies', true)
        ->assertJsonPath('plan_amount', 99.90)
        ->assertJsonPath('bot_ia_amount', 15.00)
        ->assertJsonPath('total_amount', 114.90);
});
