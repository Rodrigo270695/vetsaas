<?php

declare(strict_types=1);

use App\Models\Paciente;
use App\Models\PortalPropietario;
use App\Models\PortalPropietarioSesion;
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
        $this->markTestSkipped('El portal usa schemas tenant; requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('no invita sin celular y envía el enlace cuando hay WhatsApp', function (): void {
    $ids = app(TenantManager::class)->runForSlug(
        $this->testTenant->slug,
        function (): array {
            $sinTel = Propietario::query()->create([
                'nombres' => 'Ana',
                'apellidos' => 'SinTel',
                'activo' => true,
            ]);
            $conTel = Propietario::query()->create([
                'nombres' => 'Luis',
                'apellidos' => 'ConTel',
                'telefono' => '999888777',
                'activo' => true,
            ]);

            return ['sin' => $sinTel->id, 'con' => $conTel->id];
        },
    );

    $this->actingAs($this->testTenantAdmin)
        ->post('http://'.$this->testTenantHost.'/clinica/propietarios/'.$ids['sin'].'/portal/invitar')
        ->assertRedirect();

    $this->mock(ClinicalHistoryWhatsAppSender::class, function ($mock): void {
        $mock->shouldReceive('send')->once()->andReturn([]);
    });

    $this->actingAs($this->testTenantAdmin)
        ->post('http://'.$this->testTenantHost.'/clinica/propietarios/'.$ids['con'].'/portal/invitar')
        ->assertRedirect()
        ->assertSessionHas('success');

    app(TenantManager::class)->runForSlug($this->testTenant->slug, function () use ($ids): void {
        $portal = PortalPropietario::query()->where('propietario_id', $ids['con'])->first();
        expect($portal)->not->toBeNull();
        expect($portal?->invite_token)->toHaveLength(64);
    });
});

it('crea el PIN y deja la sesión en el celular', function (): void {
    $token = bin2hex(random_bytes(32));

    app(TenantManager::class)->runForSlug($this->testTenant->slug, function () use ($token): void {
        $owner = Propietario::query()->create([
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
            'telefono' => '999111222',
            'activo' => true,
        ]);
        Paciente::query()->create([
            'propietario_id' => $owner->id,
            'nombre' => 'Luna',
            'activo' => true,
        ]);
        PortalPropietario::query()->create([
            'propietario_id' => $owner->id,
            'invite_token' => $token,
            'invite_expires_at' => now()->addDays(7),
            'telefono_snapshot' => '999111222',
        ]);
    });

    $this->get('http://'.$this->testTenantHost.'/portal/entrar/'.$token)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/entrar')
            ->where('step', 'setup')
            ->where('mascota.nombre', 'Luna'));

    $response = $this->post('http://'.$this->testTenantHost.'/portal/entrar/'.$token.'/pin', [
        'pin' => '2580',
        'pin_confirmation' => '2580',
    ]);

    $response->assertRedirect();

    app(TenantManager::class)->runForSlug($this->testTenant->slug, function (): void {
        $portal = PortalPropietario::query()->sole();
        expect($portal->hasPin())->toBeTrue();
        expect(Hash::check('2580', (string) $portal->pin_hash))->toBeTrue();
        expect(PortalPropietarioSesion::query()->whereNull('revoked_at')->count())->toBe(1);
    });

    $cookie = $response->getCookie((string) config('portal.cookie'));
    expect($cookie)->not->toBeNull();

    $this->disableCookieEncryption();
    $this->withUnencryptedCookie((string) config('portal.cookie'), (string) $cookie?->getValue())
        ->get('http://'.$this->testTenantHost.'/portal')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/home')
            ->where('overview.mascotas.0.nombre', 'Luna')
            ->where('pet', null));
});

it('rechaza el PIN incorrecto y pide el enlace si no hay sesión', function (): void {
    $token = bin2hex(random_bytes(32));

    app(TenantManager::class)->runForSlug($this->testTenant->slug, function () use ($token): void {
        $owner = Propietario::query()->create([
            'nombres' => 'Ana',
            'activo' => true,
        ]);
        PortalPropietario::query()->create([
            'propietario_id' => $owner->id,
            'invite_token' => $token,
            'invite_expires_at' => now()->addDays(7),
            'pin_hash' => Hash::make('1111'),
            'pin_set_at' => now(),
        ]);
    });

    $this->from('http://'.$this->testTenantHost.'/portal/entrar/'.$token)
        ->post('http://'.$this->testTenantHost.'/portal/entrar/'.$token.'/desbloquear', [
            'pin' => '0000',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('pin');

    $home = $this->get('http://'.$this->testTenantHost.'/portal');
    $home->assertRedirect();
    expect((string) $home->headers->get('Location'))->toContain('/portal/sin-acceso');
});
