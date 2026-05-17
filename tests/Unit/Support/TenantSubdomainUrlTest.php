<?php

use App\Models\Tenant;
use App\Support\Tenancy\TenantSubdomainUrl;
use Tests\TestCase;

uses(TestCase::class);

it('construye la url de login con tenant.root_domain y orvae.tenant.scheme', function (): void {
    config([
        'tenant.root_domain' => 'vetsaas.orvae.pe',
        'orvae.tenant.scheme' => 'https',
        'orvae.tenant.login_path' => '/login',
    ]);

    $tenant = new Tenant(['slug' => 'clinica-demo']);

    expect(TenantSubdomainUrl::login($tenant))
        ->toBe('https://clinica-demo.vetsaas.orvae.pe/login');
});
