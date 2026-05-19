<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\TenantRolesSeeder;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fase 4 · Módulo 1 — Configuración general de la clínica.
 *
 * Cubre:
 *
 *   - Auto-provisión: la primera vez que un usuario abre la pantalla
 *     se crea la única fila de `cfg_clinic_settings` con valores por
 *     defecto, sin que tenga que hacer nada explícito.
 *   - Aislamiento: cada tenant edita SU propia fila dentro de su
 *     schema. Mutar el tenant A no afecta al tenant B.
 *   - Autorización: el permiso `config-general.view` controla el
 *     acceso. Sin ese permiso, el middleware responde 403.
 *   - Cifrado: el token de Nubefact (única credencial del cliente)
 *     se guarda cifrado con `Crypt::encryptString`, nunca en claro.
 *   - Flujo "limpiar credencial": el flag `clear_nubefact=true` borra
 *     la credencial existente y baja el flag `*_configurado`.
 *   - Subida de logo: el endpoint acepta `multipart/form-data` con un
 *     archivo de imagen, lo guarda en el disco `public` y registra el
 *     `logo_path`. El flag `clear_logo` elimina el archivo previo.
 *   - Defensa en profundidad: si el módulo se llama sin tenant
 *     resuelto desde el host central, los roles operativos reciben 404;
 *     el superadmin recibe `shared/tenant-required` (200 OK).
 *
 * Requiere PostgreSQL porque la tabla vive en un schema del tenant
 * (multi-schema). En SQLite se omite la suite entera.
 */
uses(RefreshDatabaseWithPgsqlSafety::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Configuración General usa schemas tenant; requiere PostgreSQL.');
    }

    config([
        'tenant.central_domains' => ['localhost', '127.0.0.1', 'vetsaas.test'],
        'tenant.root_domain' => 'vetsaas.test',
        'tenant.schema_prefix' => 'vet_',
        'tenant.allowed_states' => ['active', 'trial', 'grace'],
        'tenant.cache_ttl' => 0,
    ]);

    $this->seed(PermissionsSeeder::class);
    $this->seed(TenantRolesSeeder::class);

    $this->slug = 'cfg-test-'.Str::lower(Str::random(4));
    $this->schema = 'vet_test_'.Str::lower(Str::random(6));

    Artisan::call('vetsaas:tenant-migrate', [
        'schema' => $this->schema,
    ]);

    $this->tenant = Tenant::create([
        'slug' => $this->slug,
        'schema_name' => $this->schema,
        'razon_social' => 'Clínica de Prueba',
        'nombre_comercial' => 'Test Vet',
        'email_admin' => 'admin@test.local',
        'timezone' => 'America/Lima',
        'locale' => 'es',
        'estado' => 'active',
    ]);

    $this->admin = User::factory()->create([
        'email' => 'admin@test.local',
        'tenant_id' => $this->tenant->id,
        'password' => Hash::make('clave-admin'),
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $this->admin->assignRole('admin_clinica');

    $this->groomer = User::factory()->create([
        'email' => 'groomer@test.local',
        'tenant_id' => $this->tenant->id,
        'password' => Hash::make('clave-groomer'),
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $this->groomer->assignRole('groomer');

    $this->host = $this->slug.'.vetsaas.test';
});

afterEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        return;
    }

    if (isset($this->tenant)) {
        $this->tenant->forceDelete();
    }

    if (isset($this->schema)) {
        DB::statement('DROP SCHEMA IF EXISTS "'.$this->schema.'" CASCADE');
    }

    DB::statement('SET search_path TO public');
});

/* -------------------------------------------------------------------------- */
/*                                Show / acceso                                */
/* -------------------------------------------------------------------------- */

it('autoprovisiona la fila de configuración al primer acceso del admin', function (): void {
    $this->actingAs($this->admin);

    DB::statement('SET search_path TO "'.$this->schema.'", public');
    expect(DB::table('cfg_clinic_settings')->count())->toBe(0);
    DB::statement('SET search_path TO public');

    $response = $this->get('http://'.$this->host.'/configuracion/general');

    $response->assertOk();

    DB::statement('SET search_path TO "'.$this->schema.'", public');
    $row = DB::table('cfg_clinic_settings')->first();
    DB::statement('SET search_path TO public');

    expect($row)->not->toBeNull();
    expect($row->moneda)->toBe('PEN');
    expect((float) $row->igv_porcentaje)->toBe(18.00);
    expect($row->duracion_cita_default_min)->toBe(30);
    expect((bool) $row->emite_comprobantes_sunat)->toBeFalse();
});

it('un empleado sin permiso config-general.view recibe 403', function (): void {
    $this->actingAs($this->groomer);

    $response = $this->get('http://'.$this->host.'/configuracion/general');

    $response->assertForbidden();
});

it('un invitado (sin login) es redirigido al login', function (): void {
    $response = $this->get('http://'.$this->host.'/configuracion/general');

    $response->assertRedirect();
});

it('superadmin sin tenant resuelto recibe la pantalla "shared/tenant-required" (200 OK)', function (): void {
    /*
     * Cuando un superadmin del panel central (tenant_id=null) entra a
     * una ruta tenant-only desde el host central, el middleware
     * `tenant.required` (EnsureTenant) le muestra una pantalla Inertia
     * informativa en lugar del 404 seco, para evitar que el sidebar
     * (que para superadmin muestra TODO) lleve a una experiencia rota.
     */
    $superRole = \Spatie\Permission\Models\Role::firstOrCreate([
        'name' => 'superadmin',
        'guard_name' => 'web',
    ]);
    $superRole->syncPermissions(\Spatie\Permission\Models\Permission::all());

    $superadmin = User::factory()->create([
        'email' => 'super@vetsaas.test',
        'tenant_id' => null,
        'password' => Hash::make('clave-super'),
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $superadmin->assignRole('superadmin');

    $this->actingAs($superadmin);

    $response = $this->get('http://localhost/configuracion/general');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('shared/tenant-required')
        ->where('attempted_path', '/configuracion/general')
    );
});

/* -------------------------------------------------------------------------- */
/*                                  Update                                     */
/* -------------------------------------------------------------------------- */

it('admin_clinica puede actualizar la configuración con datos válidos', function (): void {
    $this->actingAs($this->admin);

    $payload = validPayload();

    $response = $this->put('http://'.$this->host.'/configuracion/general', $payload);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    DB::statement('SET search_path TO "'.$this->schema.'", public');
    $row = DB::table('cfg_clinic_settings')->first();
    DB::statement('SET search_path TO public');

    expect($row->ruc)->toBe('20123456789');
    expect($row->razon_social)->toBe('Clínica San Patricio SAC');
    expect($row->duracion_cita_default_min)->toBe(45);
    expect((bool) $row->recordatorio_48h_activo)->toBeTrue();
    expect((bool) $row->precio_incluye_igv)->toBeFalse();
    expect($row->moneda)->toBe('USD');
});

it('persiste el remitente comercial visible (correo + WhatsApp display)', function (): void {
    /*
     * Las credenciales reales de Twilio/Brevo ya no se piden al cliente
     * (viven en `public.platform_settings`). Lo único que el cliente
     * personaliza es la "firma comercial" de los mensajes que envía la
     * plataforma en su nombre: nombre de remitente, correo de respuesta
     * y número WhatsApp visible.
     */
    $this->actingAs($this->admin);

    $payload = array_merge(validPayload(), [
        'email_from' => 'contacto@miclinica.pe',
        'email_from_nombre' => 'Clínica San Patricio',
        'whatsapp_display_number' => '+51 999 000 111',
    ]);

    $this->put('http://'.$this->host.'/configuracion/general', $payload)
        ->assertSessionHasNoErrors();

    DB::statement('SET search_path TO "'.$this->schema.'", public');
    $row = DB::table('cfg_clinic_settings')->first();
    DB::statement('SET search_path TO public');

    expect($row->email_from)->toBe('contacto@miclinica.pe');
    expect($row->email_from_nombre)->toBe('Clínica San Patricio');
    expect($row->whatsapp_display_number)->toBe('+51 999 000 111');
});

it('rechaza RUC con menos de 11 dígitos', function (): void {
    $this->actingAs($this->admin);

    $payload = array_merge(validPayload(), ['ruc' => '1234']);

    $response = $this->from('http://'.$this->host.'/configuracion/general')
        ->put('http://'.$this->host.'/configuracion/general', $payload);

    $response->assertSessionHasErrors('ruc');
});

it('rechaza moneda fuera del catálogo permitido', function (): void {
    $this->actingAs($this->admin);

    $payload = array_merge(validPayload(), ['moneda' => 'EUR']);

    $response = $this->from('http://'.$this->host.'/configuracion/general')
        ->put('http://'.$this->host.'/configuracion/general', $payload);

    $response->assertSessionHasErrors('moneda');
});

it('rechaza activar emisión SUNAT si el plan no incluye factura electrónica', function (): void {
    $this->actingAs($this->admin);

    $payload = array_merge(validPayload(), [
        'emite_comprobantes_sunat' => true,
    ]);

    $response = $this->from('http://'.$this->host.'/configuracion/general')
        ->put('http://'.$this->host.'/configuracion/general', $payload);

    $response->assertSessionHasErrors('emite_comprobantes_sunat');
});

it('un empleado sin permiso config-general.update recibe 403 al intentar guardar', function (): void {
    $this->actingAs($this->groomer);

    $response = $this->put('http://'.$this->host.'/configuracion/general', validPayload());

    $response->assertForbidden();
});

/* -------------------------------------------------------------------------- */
/*                       Cifrado de Nubefact (única integración del cliente)  */
/* -------------------------------------------------------------------------- */

it('cifra el token de Nubefact al guardarlo y marca la integración como configurada', function (): void {
    $this->actingAs($this->admin);

    $payload = array_merge(validPayload(), [
        'nubefact_ruc' => '20123456789',
        'nubefact_api_ruta' => 'https://api.nubefact.com/api/v1/local-principal-test',
        'nubefact_token' => 'super-secret-nubefact-token',
    ]);

    $this->put('http://'.$this->host.'/configuracion/general', $payload)
        ->assertSessionHasNoErrors();

    DB::statement('SET search_path TO "'.$this->schema.'", public');
    $row = DB::table('cfg_clinic_settings')->first();
    DB::statement('SET search_path TO public');

    expect($row->nubefact_token_enc)->not->toBeNull();
    expect($row->nubefact_token_enc)->not->toBe('super-secret-nubefact-token');

    expect(Crypt::decryptString($row->nubefact_token_enc))
        ->toBe('super-secret-nubefact-token');

    expect($row->nubefact_api_ruta)
        ->toBe('https://api.nubefact.com/api/v1/local-principal-test');

    expect((bool) $row->nubefact_configurado)->toBeTrue();
});

it('no marca Nubefact configurado si solo hay token sin ruta', function (): void {
    $this->actingAs($this->admin);

    $this->put('http://'.$this->host.'/configuracion/general', array_merge(validPayload(), [
        'nubefact_token' => 'solo-token-sin-ruta',
    ]))->assertSessionHasNoErrors();

    DB::statement('SET search_path TO "'.$this->schema.'", public');
    $row = DB::table('cfg_clinic_settings')->first();
    DB::statement('SET search_path TO public');

    expect($row->nubefact_token_enc)->not->toBeNull();
    expect($row->nubefact_api_ruta)->toBeNull();
    expect((bool) $row->nubefact_configurado)->toBeFalse();
});

it('el flag clear_nubefact borra la credencial guardada y baja el flag configurado', function (): void {
    $this->actingAs($this->admin);

    // Paso 1: guardar credencial.
    $this->put('http://'.$this->host.'/configuracion/general', array_merge(validPayload(), [
        'nubefact_api_ruta' => 'https://api.nubefact.com/api/v1/local-principal-test',
        'nubefact_token' => 'token-original',
    ]));

    DB::statement('SET search_path TO "'.$this->schema.'", public');
    $row = DB::table('cfg_clinic_settings')->first();
    DB::statement('SET search_path TO public');
    expect((bool) $row->nubefact_configurado)->toBeTrue();
    expect($row->nubefact_token_enc)->not->toBeNull();

    // Paso 2: pedir borrado explícito.
    $this->put('http://'.$this->host.'/configuracion/general', array_merge(validPayload(), [
        'clear_nubefact' => true,
    ]));

    DB::statement('SET search_path TO "'.$this->schema.'", public');
    $row = DB::table('cfg_clinic_settings')->first();
    DB::statement('SET search_path TO public');
    expect($row->nubefact_token_enc)->toBeNull();
    expect($row->nubefact_api_ruta)->toBeNull();
    expect((bool) $row->nubefact_configurado)->toBeFalse();
});

it('no toca el token de Nubefact si el cliente no lo manda (preserva on-the-fly)', function (): void {
    $this->actingAs($this->admin);

    // Guarda una vez con credencial.
    $this->put('http://'.$this->host.'/configuracion/general', array_merge(validPayload(), [
        'nubefact_api_ruta' => 'https://api.nubefact.com/api/v1/local-principal-test',
        'nubefact_token' => 'token-original',
    ]));

    DB::statement('SET search_path TO "'.$this->schema.'", public');
    $firstRow = DB::table('cfg_clinic_settings')->first();
    DB::statement('SET search_path TO public');
    $originalEnc = $firstRow->nubefact_token_enc;
    expect($originalEnc)->not->toBeNull();

    // Guarda otra vez SIN mandar nubefact_token. El valor cifrado
    // debe permanecer intacto.
    $this->put('http://'.$this->host.'/configuracion/general', array_merge(validPayload(), [
        'nubefact_token' => '',
    ]));

    DB::statement('SET search_path TO "'.$this->schema.'", public');
    $secondRow = DB::table('cfg_clinic_settings')->first();
    DB::statement('SET search_path TO public');
    expect($secondRow->nubefact_token_enc)->toBe($originalEnc);
});

/* -------------------------------------------------------------------------- */
/*                                Subida de logo                               */
/* -------------------------------------------------------------------------- */

it('acepta un archivo de imagen y persiste el logo_path en el disco public', function (): void {
    Storage::fake('public');

    $this->actingAs($this->admin);

    $file = UploadedFile::fake()->image('logo.png', 256, 256);

    $payload = array_merge(validPayload(), ['logo' => $file]);

    $response = $this->post(
        'http://'.$this->host.'/configuracion/general',
        array_merge($payload, ['_method' => 'PUT']),
    );

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    DB::statement('SET search_path TO "'.$this->schema.'", public');
    $row = DB::table('cfg_clinic_settings')->first();
    DB::statement('SET search_path TO public');

    expect($row->logo_path)->not->toBeNull();
    expect($row->logo_path)->toStartWith('tenants/'.$this->slug.'/logos/');
    Storage::disk('public')->assertExists($row->logo_path);
});

it('rechaza archivos que no son imágenes válidas', function (): void {
    Storage::fake('public');

    $this->actingAs($this->admin);

    $file = UploadedFile::fake()->create('not-an-image.pdf', 100, 'application/pdf');

    $payload = array_merge(validPayload(), ['logo' => $file]);

    $response = $this->from('http://'.$this->host.'/configuracion/general')
        ->post('http://'.$this->host.'/configuracion/general', array_merge($payload, ['_method' => 'PUT']));

    $response->assertSessionHasErrors('logo');
});

it('clear_logo borra el archivo físico y limpia el logo_path', function (): void {
    Storage::fake('public');

    $this->actingAs($this->admin);

    // Paso 1: subir logo.
    $file = UploadedFile::fake()->image('logo.png', 256, 256);
    $this->post(
        'http://'.$this->host.'/configuracion/general',
        array_merge(validPayload(), ['logo' => $file, '_method' => 'PUT']),
    );

    DB::statement('SET search_path TO "'.$this->schema.'", public');
    $row = DB::table('cfg_clinic_settings')->first();
    DB::statement('SET search_path TO public');
    $originalPath = $row->logo_path;
    expect($originalPath)->not->toBeNull();
    Storage::disk('public')->assertExists($originalPath);

    // Paso 2: pedir borrado.
    $this->put(
        'http://'.$this->host.'/configuracion/general',
        array_merge(validPayload(), ['clear_logo' => true]),
    );

    DB::statement('SET search_path TO "'.$this->schema.'", public');
    $row = DB::table('cfg_clinic_settings')->first();
    DB::statement('SET search_path TO public');
    expect($row->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($originalPath);
});

it('al reemplazar el logo elimina el archivo previo (no deja huérfanos)', function (): void {
    Storage::fake('public');

    $this->actingAs($this->admin);

    // Paso 1: primer logo.
    $first = UploadedFile::fake()->image('first.png', 256, 256);
    $this->post(
        'http://'.$this->host.'/configuracion/general',
        array_merge(validPayload(), ['logo' => $first, '_method' => 'PUT']),
    );

    DB::statement('SET search_path TO "'.$this->schema.'", public');
    $row = DB::table('cfg_clinic_settings')->first();
    DB::statement('SET search_path TO public');
    $firstPath = $row->logo_path;
    expect($firstPath)->not->toBeNull();

    // Paso 2: reemplazo.
    $second = UploadedFile::fake()->image('second.png', 256, 256);
    $this->post(
        'http://'.$this->host.'/configuracion/general',
        array_merge(validPayload(), ['logo' => $second, '_method' => 'PUT']),
    );

    DB::statement('SET search_path TO "'.$this->schema.'", public');
    $row = DB::table('cfg_clinic_settings')->first();
    DB::statement('SET search_path TO public');
    $secondPath = $row->logo_path;

    expect($secondPath)->not->toBeNull();
    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertExists($secondPath);
    Storage::disk('public')->assertMissing($firstPath);
});

/* -------------------------------------------------------------------------- */
/*                                  Helpers                                    */
/* -------------------------------------------------------------------------- */

function validPayload(): array
{
    return [
        'ruc' => '20123456789',
        'razon_social' => 'Clínica San Patricio SAC',
        'nombre_comercial' => 'San Patricio Vet',
        'direccion_fiscal' => 'Av. Javier Prado 1234',
        'distrito_id' => null,
        'color_primario' => '#1F6F43',
        'color_secundario' => '#94C7A8',
        'email_institucional' => 'contacto@sp.pe',
        'telefono_principal' => '+51 1 555-0101',
        'web_url' => 'https://sp.pe',
        'duracion_cita_default_min' => 45,
        'intervalo_agenda_min' => 15,
        'dias_anticipacion_cita' => 60,
        'horas_min_cancelacion' => 12,
        'recordatorio_48h_activo' => true,
        'recordatorio_2h_activo' => true,
        'recordatorio_vacuna_activo' => true,
        'recordatorio_vacuna_dias_antes' => 7,
        'recordatorio_cumple_activo' => false,
        'moneda' => 'USD',
        'igv_porcentaje' => 18.00,
        'precio_incluye_igv' => false,
        'ticket_ancho_mm' => '58',
        'emite_comprobantes_sunat' => false,
    ];
}
