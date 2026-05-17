<?php

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Fase 4 · Módulo 1.5 — Configuración global del SaaS.
 *
 * Cubre las credenciales de proveedores externos compartidos por todas
 * las clínicas (Twilio + Brevo). Estas claves NO viven por tenant: están
 * en `public.platform_settings` y solo el superadmin puede tocarlas.
 *
 * Tests:
 *   - Auto-provisión: la primera vez que se abre la pantalla la fila
 *     singleton se crea automáticamente.
 *   - Autorización: el permiso `platform-settings.view/update` controla
 *     el acceso. Sin él, 403.
 *   - El admin_clinica de una clínica concreta NO ve esta sección (no
 *     tiene el permiso) y recibe 403.
 *   - Cifrado: SID, token y API key se guardan con `Crypt::encryptString`.
 *   - `clear_twilio` / `clear_brevo` borran las credenciales y bajan
 *     los flags `*_configurado`.
 *   - Preservación on-the-fly: re-guardar sin enviar las credenciales
 *     no las pisa.
 *
 * Requiere PostgreSQL para mantener consistencia con el resto de la
 * suite (el constraint `((TRUE))` solo funciona en pgsql).
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('platform_settings usa un UNIQUE INDEX sobre TRUE; requiere PostgreSQL.');
    }

    $this->seed(PermissionsSeeder::class);
    $this->seed(TenantRolesSeeder::class);

    // Rol superadmin con TODOS los permisos (replica el SuperadminSeeder
    // sin necesidad de variables de entorno).
    $superRole = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    $superRole->syncPermissions(Permission::all());

    $this->superadmin = User::factory()->create([
        'email' => 'super@vetsaas.test',
        'tenant_id' => null,
        'password' => Hash::make('clave-super'),
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $this->superadmin->assignRole('superadmin');

    // Admin de clínica (debería rebotar con 403: no tiene permiso global).
    $this->admin = User::factory()->create([
        'email' => 'admin@test.local',
        'tenant_id' => null,
        'password' => Hash::make('clave-admin'),
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $this->admin->assignRole('admin_clinica');
});

afterEach(function (): void {
    DB::statement('SET search_path TO public');
});

/* -------------------------------------------------------------------------- */
/*                              Show / acceso                                  */
/* -------------------------------------------------------------------------- */

it('autoprovisiona la fila singleton al primer acceso del superadmin', function (): void {
    $this->actingAs($this->superadmin);

    expect(DB::table('platform_settings')->count())->toBe(0);

    $response = $this->get('http://localhost/plataforma/configuracion');

    $response->assertOk();
    expect(DB::table('platform_settings')->count())->toBe(1);

    $row = DB::table('platform_settings')->first();
    expect((bool) $row->twilio_configurado)->toBeFalse();
    expect((bool) $row->brevo_configurado)->toBeFalse();
});

it('un admin_clinica NO puede ver la configuración global (403)', function (): void {
    $this->actingAs($this->admin);

    $response = $this->get('http://localhost/plataforma/configuracion');

    $response->assertForbidden();
});

it('un invitado (sin login) es redirigido al login', function (): void {
    $response = $this->get('http://localhost/plataforma/configuracion');

    $response->assertRedirect();
});

/* -------------------------------------------------------------------------- */
/*                                  Update                                     */
/* -------------------------------------------------------------------------- */

it('superadmin puede guardar credenciales Twilio cifradas y marca configurado', function (): void {
    $this->actingAs($this->superadmin);

    $payload = [
        'twilio_sid' => 'ACxxxxxxxxxxxxxxxxxx',
        'twilio_token' => 'super-secret-twilio-token',
        'twilio_default_from' => '+14155238886',
    ];

    $response = $this->put('http://localhost/plataforma/configuracion', $payload);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $row = DB::table('platform_settings')->first();

    expect($row->twilio_sid_enc)->not->toBeNull();
    expect($row->twilio_sid_enc)->not->toBe('ACxxxxxxxxxxxxxxxxxx');
    expect(Crypt::decryptString($row->twilio_sid_enc))->toBe('ACxxxxxxxxxxxxxxxxxx');
    expect(Crypt::decryptString($row->twilio_token_enc))->toBe('super-secret-twilio-token');
    expect($row->twilio_default_from)->toBe('+14155238886');
    expect((bool) $row->twilio_configurado)->toBeTrue();
});

it('superadmin puede guardar credenciales Brevo cifradas y marca configurado', function (): void {
    $this->actingAs($this->superadmin);

    $payload = [
        'brevo_api_key' => 'xkeysib-supersecret',
        'brevo_default_from_email' => 'no-reply@vetsaas.com',
        'brevo_default_from_name' => 'VetSaaS',
    ];

    $this->put('http://localhost/plataforma/configuracion', $payload)
        ->assertSessionHasNoErrors();

    $row = DB::table('platform_settings')->first();

    expect(Crypt::decryptString($row->brevo_api_key_enc))->toBe('xkeysib-supersecret');
    expect($row->brevo_default_from_email)->toBe('no-reply@vetsaas.com');
    expect($row->brevo_default_from_name)->toBe('VetSaaS');
    expect((bool) $row->brevo_configurado)->toBeTrue();
});

it('rechaza un número WhatsApp con formato inválido', function (): void {
    $this->actingAs($this->superadmin);

    $response = $this->from('http://localhost/plataforma/configuracion')
        ->put('http://localhost/plataforma/configuracion', [
            'twilio_default_from' => 'no-es-numero',
        ]);

    $response->assertSessionHasErrors('twilio_default_from');
});

it('clear_twilio borra las credenciales guardadas y baja el flag', function (): void {
    $this->actingAs($this->superadmin);

    // Paso 1: guardar credenciales.
    $this->put('http://localhost/plataforma/configuracion', [
        'twilio_sid' => 'ACfoo',
        'twilio_token' => 'bar',
        'twilio_default_from' => '+14155238886',
    ]);

    $row = DB::table('platform_settings')->first();
    expect((bool) $row->twilio_configurado)->toBeTrue();

    // Paso 2: borrado explícito.
    $this->put('http://localhost/plataforma/configuracion', [
        'clear_twilio' => true,
    ]);

    $row = DB::table('platform_settings')->first();
    expect($row->twilio_sid_enc)->toBeNull();
    expect($row->twilio_token_enc)->toBeNull();
    expect((bool) $row->twilio_configurado)->toBeFalse();
});

it('preserva credenciales existentes si el cliente no las manda en el update', function (): void {
    $this->actingAs($this->superadmin);

    // Guarda una vez.
    $this->put('http://localhost/plataforma/configuracion', [
        'brevo_api_key' => 'xkeysib-original',
    ]);

    $firstRow = DB::table('platform_settings')->first();
    $originalEnc = $firstRow->brevo_api_key_enc;
    expect($originalEnc)->not->toBeNull();

    // Guarda otra vez SIN mandar la api key (solo el nombre): el enc
    // debe seguir intacto.
    $this->put('http://localhost/plataforma/configuracion', [
        'brevo_default_from_name' => 'VetSaaS Nuevo',
    ]);

    $secondRow = DB::table('platform_settings')->first();
    expect($secondRow->brevo_api_key_enc)->toBe($originalEnc);
    expect($secondRow->brevo_default_from_name)->toBe('VetSaaS Nuevo');
});
