<?php

declare(strict_types=1);

use App\Models\ClinicSetting;
use App\Models\PortalPropietario;
use App\Models\Propietario;
use App\Services\Clinica\ClinicalHistoryWhatsAppSender;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Portal landing requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('oculta acceso landing cuando no está activo', function (): void {
    $this->get('http://'.$this->testTenantHost.'/portal/acceso')->assertNotFound();
});

it('flujo documento otp y redirección al pin', function (): void {
    app(TenantManager::class)->runForSlug($this->testTenant->slug, function (): void {
        ClinicSetting::current()->forceFill(['portal_landing_activo' => true])->save();
        $owner = Propietario::query()->create([
            'nombres' => 'María',
            'apellidos' => 'López',
            'numero_documento' => '12345678',
            'telefono' => '999555444',
            'activo' => true,
        ]);
        PortalPropietario::query()->create([
            'propietario_id' => $owner->id,
            'telefono_snapshot' => '999555444',
        ]);
    });

    $this->mock(ClinicalHistoryWhatsAppSender::class, function ($mock): void {
        $mock->shouldReceive('send')->once()->andReturn([]);
    });

    $this->get('http://'.$this->testTenantHost.'/portal/acceso')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('portal/acceso'));

    $this->post('http://'.$this->testTenantHost.'/portal/acceso', [
        'numero_documento' => '12345678',
    ])->assertRedirect();

    $code = app(TenantManager::class)->runForSlug($this->testTenant->slug, function (): ?string {
        $portal = PortalPropietario::query()->first();
        if ($portal === null || $portal->landing_otp_hash === null) {
            return null;
        }
        foreach (range(0, 999999) as $n) {
            $candidate = str_pad((string) $n, 6, '0', STR_PAD_LEFT);
            if (Hash::check($candidate, (string) $portal->landing_otp_hash)) {
                return $candidate;
            }
        }

        return null;
    });

    expect($code)->not->toBeNull();

    $this->post('http://'.$this->testTenantHost.'/portal/acceso/codigo', [
        'code' => $code,
    ])
        ->assertRedirect()
        ->assertCookie(config('portal.identity_cookie', 'vetsaas_portal_id'));
});