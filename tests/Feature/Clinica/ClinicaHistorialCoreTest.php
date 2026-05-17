<?php

use App\Models\Cirugia;
use App\Models\Consulta;
use App\Models\HistoriaClinica;
use App\Models\Paciente;
use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioLinea;
use App\Models\Propietario;
use App\Models\Receta;
use App\Models\RecetaLinea;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VacunaAplicada;
use App\Tenancy\Facades\Tenant as TenantContext;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\TenantMigrateTestGuards;

/**
 * Núcleo clínico: cierre/reapertura de consulta, validación vacuna–consulta cerrada,
 * e historial del paciente con detalle.vinculos (recetas / laboratorio / cirugías).
 *
 * Requiere PostgreSQL (multi-schema), igual que {@see ClinicSettingTest}.
 * Con `DB_CONNECTION=sqlite` en phpunit (por defecto en `.env.testing`) los casos se omiten.
 *
 * Para ejecutarlos con Postgres use una base solo de tests (p. ej. `vetsaas_test`), no la de desarrollo:
 * `DB_CONNECTION=pgsql DB_DATABASE=vetsaas_test ... php artisan test tests/Feature/Clinica/ClinicaHistorialCoreTest.php`
 */
beforeEach(function (): void {
    TenantMigrateTestGuards::guardIfUnsafePgsql($this);

    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Clínica tenant usa schemas PostgreSQL.');
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

    $this->slug = 'clin-hist-'.Str::lower(Str::random(4));
    $this->schema = 'vet_test_'.Str::lower(Str::random(6));

    Artisan::call('vetsaas:tenant-migrate', [
        'schema' => $this->schema,
    ]);

    $this->tenant = Tenant::create([
        'slug' => $this->slug,
        'schema_name' => $this->schema,
        'razon_social' => 'Clínica Historial Test',
        'nombre_comercial' => 'Hist Test',
        'email_admin' => 'admin-hist@test.local',
        'timezone' => 'America/Lima',
        'locale' => 'es',
        'estado' => 'active',
    ]);

    $this->admin = User::factory()->create([
        'email' => 'admin-hist@test.local',
        'tenant_id' => $this->tenant->id,
        'password' => Hash::make('clave-admin'),
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $this->admin->assignRole('admin_clinica');

    $this->host = $this->slug.'.vetsaas.test';
});

afterEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        return;
    }

    if (isset($this->admin)) {
        $this->admin->forceDelete();
    }

    if (isset($this->tenant)) {
        $this->tenant->forceDelete();
    }

    if (isset($this->schema)) {
        DB::statement('DROP SCHEMA IF EXISTS "'.$this->schema.'" CASCADE');
    }

    DB::statement('SET search_path TO public');
});

/**
 * @return array{paciente: Paciente, consulta: Consulta}
 */
function clinicaHist_seedPacienteConsultaAbierta(string $slug, string $adminId): array
{
    return TenantContext::runForSlug($slug, function () use ($adminId) {
        $prop = Propietario::query()->create([
            'nombres' => 'María',
            'apellidos' => 'Histórica',
            'activo' => true,
            'created_by_id' => $adminId,
            'updated_by_id' => $adminId,
        ]);

        $paciente = Paciente::query()->create([
            'propietario_id' => $prop->id,
            'nombre' => 'Mascota Test',
            'activo' => true,
            'created_by_id' => $adminId,
            'updated_by_id' => $adminId,
        ]);

        $historia = HistoriaClinica::query()->create([
            'paciente_id' => $paciente->id,
            'created_by_id' => $adminId,
            'updated_by_id' => $adminId,
        ]);

        $consulta = $historia->consultas()->create([
            'atendido_at' => now()->subHours(2),
            'motivo' => 'Control general',
            'cerrada_at' => null,
            'cerrada_por_id' => null,
            'veterinario_id' => $adminId,
            'created_by_id' => $adminId,
            'updated_by_id' => $adminId,
        ]);

        return ['paciente' => $paciente, 'consulta' => $consulta];
    });
}

it('cierra y reabre una consulta vía POST', function (): void {
    $adminId = (string) $this->admin->id;
    ['consulta' => $consulta] = clinicaHist_seedPacienteConsultaAbierta($this->slug, $adminId);

    $this->actingAs($this->admin)
        ->post('http://'.$this->host.'/clinica/historias-clinicas/consultas/'.$consulta->id.'/cerrar')
        ->assertRedirect();

    TenantContext::runForSlug($this->slug, function () use ($consulta, $adminId): void {
        $c = Consulta::query()->findOrFail($consulta->id);
        expect($c->cerrada_at)->not->toBeNull();
        expect((string) $c->cerrada_por_id)->toBe($adminId);
    });

    $this->actingAs($this->admin)
        ->post('http://'.$this->host.'/clinica/historias-clinicas/consultas/'.$consulta->id.'/reabrir')
        ->assertRedirect();

    TenantContext::runForSlug($this->slug, function () use ($consulta): void {
        $c = Consulta::query()->findOrFail($consulta->id);
        expect($c->cerrada_at)->toBeNull();
        expect($c->cerrada_por_id)->toBeNull();
    });
});

it('rechaza crear vacuna vinculada a una consulta cerrada', function (): void {
    $adminId = (string) $this->admin->id;
    ['paciente' => $paciente, 'consulta' => $consulta] = clinicaHist_seedPacienteConsultaAbierta($this->slug, $adminId);

    $this->actingAs($this->admin)
        ->post('http://'.$this->host.'/clinica/historias-clinicas/consultas/'.$consulta->id.'/cerrar')
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->from('http://'.$this->host.'/clinica/vacunaciones')
        ->post('http://'.$this->host.'/clinica/vacunaciones', [
            'paciente_id' => $paciente->id,
            'consulta_id' => $consulta->id,
            'nombre_vacuna' => 'Antirrábica',
            'aplicada_at' => now()->toDateTimeString(),
            'categoria_registro' => VacunaAplicada::CATEGORIA_VACUNA,
        ])
        ->assertSessionHasErrors('consulta_id');
});

it('permite crear vacuna vinculada a consulta abierta', function (): void {
    $adminId = (string) $this->admin->id;
    ['paciente' => $paciente, 'consulta' => $consulta] = clinicaHist_seedPacienteConsultaAbierta($this->slug, $adminId);

    $this->actingAs($this->admin)
        ->from('http://'.$this->host.'/clinica/vacunaciones')
        ->post('http://'.$this->host.'/clinica/vacunaciones', [
            'paciente_id' => $paciente->id,
            'consulta_id' => $consulta->id,
            'nombre_vacuna' => 'Antirrábica',
            'aplicada_at' => now()->toDateTimeString(),
            'categoria_registro' => VacunaAplicada::CATEGORIA_VACUNA,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    TenantContext::runForSlug($this->slug, function () use ($paciente, $consulta): void {
        expect(
            VacunaAplicada::query()
                ->where('paciente_id', $paciente->id)
                ->where('consulta_id', $consulta->id)
                ->count(),
        )->toBe(1);
    });
});

it('expone recetas, laboratorio y cirugías en detalle.vinculos del timeline del paciente', function (): void {
    $adminId = (string) $this->admin->id;
    ['paciente' => $paciente, 'consulta' => $consulta] = clinicaHist_seedPacienteConsultaAbierta($this->slug, $adminId);

    TenantContext::runForSlug($this->slug, function () use ($paciente, $consulta, $adminId): void {
        $receta = Receta::query()->create([
            'paciente_id' => $paciente->id,
            'consulta_id' => $consulta->id,
            'veterinario_id' => $adminId,
            'sede_id' => null,
            'emitida_at' => now(),
            'estado' => Receta::ESTADO_EMITIDA,
            'observaciones' => null,
            'created_by_id' => $adminId,
            'updated_by_id' => $adminId,
        ]);
        RecetaLinea::query()->create([
            'receta_id' => $receta->id,
            'producto_id' => null,
            'nombre_medicamento' => 'Amoxicilina',
            'posologia' => '12 h',
            'orden' => 0,
        ]);

        $pedido = PedidoLaboratorio::query()->create([
            'paciente_id' => $paciente->id,
            'consulta_id' => $consulta->id,
            'veterinario_id' => $adminId,
            'sede_id' => null,
            'solicitado_at' => now(),
            'estado' => PedidoLaboratorio::ESTADO_SOLICITADO,
            'laboratorio_destino' => null,
            'observaciones' => null,
            'created_by_id' => $adminId,
            'updated_by_id' => $adminId,
        ]);
        PedidoLaboratorioLinea::query()->create([
            'pedido_laboratorio_id' => $pedido->id,
            'nombre_examen' => 'Hemograma',
            'orden' => 0,
        ]);

        Cirugia::query()->create([
            'paciente_id' => $paciente->id,
            'consulta_id' => $consulta->id,
            'veterinario_id' => $adminId,
            'sede_id' => null,
            'programada_at' => now()->addDay(),
            'estado' => Cirugia::ESTADO_PROGRAMADA,
            'nombre_procedimiento' => 'Esterilización',
            'tipo_anestesia' => null,
            'observaciones' => null,
            'created_by_id' => $adminId,
            'updated_by_id' => $adminId,
        ]);
    });

    $this->actingAs($this->admin)
        ->get('http://'.$this->host.'/clinica/pacientes/'.$paciente->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clinica/pacientes/show')
            ->has('timeline', 1)
            ->has('timeline.0', fn (Assert $row) => $row
                ->where('kind', 'consulta')
                ->has('detalle.vinculos.recetas', 1)
                ->has('detalle.vinculos.laboratorio', 1)
                ->has('detalle.vinculos.cirugias', 1)
                ->where('detalle.vinculos.recetas.0.estado', Receta::ESTADO_EMITIDA)
                ->where('detalle.vinculos.recetas.0.lineas_count', 1)
                ->where('detalle.vinculos.laboratorio.0.estado', PedidoLaboratorio::ESTADO_SOLICITADO)
                ->where('detalle.vinculos.laboratorio.0.lineas_count', 1)
                ->where('detalle.vinculos.cirugias.0.estado', Cirugia::ESTADO_PROGRAMADA)
                ->where('detalle.vinculos.cirugias.0.titulo', 'Esterilización')
            )
        );
});
