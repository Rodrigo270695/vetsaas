<?php

declare(strict_types=1);

use App\Actions\SyncConsultaPlanTratamiento;
use App\Models\Consulta;
use App\Models\HistoriaClinica;
use App\Models\Paciente;
use App\Models\Producto;
use App\Models\ProductoLote;
use App\Models\Propietario;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Inventario\InventarioLoteService;
use App\Tenancy\Facades\Tenant as TenantContext;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\TenantRolesSeeder;
use Tests\Support\TenantRbac;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\InventarioScenario;
use Tests\Support\TenantMigrateTestGuards;

beforeEach(function (): void {
    TenantMigrateTestGuards::guardIfUnsafePgsql($this);

    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Inventario requiere PostgreSQL.');
    }

    config([
        'tenant.central_domains' => ['localhost', '127.0.0.1', 'vetsaas.test'],
        'tenant.root_domain' => 'vetsaas.test',
        'tenant.schema_prefix' => 'vet_',
        'tenant.allowed_states' => ['active', 'trial', 'grace'],
        'tenant.cache_ttl' => 0,
    ]);

    $this->seed(PermissionsSeeder::class);

    $this->slug = 'fefo-'.Str::lower(Str::random(4));
    $this->schema = 'vet_test_'.Str::lower(Str::random(6));

    Artisan::call('vetsaas:tenant-migrate', ['schema' => $this->schema]);

    $this->tenant = Tenant::query()->create([
        'slug' => $this->slug,
        'schema_name' => $this->schema,
        'razon_social' => 'Clínica FEFO Test',
        'nombre_comercial' => 'FEFO Test',
        'email_admin' => 'fefo-'.Str::lower(Str::random(6)).'@test.local',
        'timezone' => 'America/Lima',
        'locale' => 'es',
        'estado' => 'active',
    ]);

    $this->admin = User::factory()->create([
        'email' => $this->tenant->email_admin,
        'tenant_id' => $this->tenant->id,
        'password' => Hash::make('password'),
        'is_active' => true,
        'must_change_password' => false,
    ]);
    TenantRbac::seedAndAssign($this->admin);

    $this->scenario = InventarioScenario::seed(
        $this->tenant,
        $this->slug,
        (string) $this->admin->id,
        0.0,
    );
});

afterEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        return;
    }

    if (isset($this->schema)) {
        DB::statement('DROP SCHEMA IF EXISTS "'.$this->schema.'" CASCADE');
    }

    DB::statement('SET search_path TO public');
});

it('descuenta stock FEFO al guardar plan de tratamiento con producto vinculado', function (): void {
    $inv = $this->scenario;
    $lotes = app(InventarioLoteService::class);

    $lotes->registrarEntrada(
        (string) $inv['producto']->id,
        (string) $inv['sede']->id,
        '5',
        'LOTE-A',
        now()->addDays(10)->toDateString(),
        'Entrada test',
        (string) $this->admin->id,
    );
    $lotes->registrarEntrada(
        (string) $inv['producto']->id,
        (string) $inv['sede']->id,
        '5',
        'LOTE-B',
        now()->addDays(30)->toDateString(),
        'Entrada test',
        (string) $this->admin->id,
    );

    TenantContext::runForSlug($this->slug, function () use ($inv): void {
        $prop = Propietario::query()->create([
            'tipo_documento' => 'DNI',
            'numero_documento' => '12345678',
            'nombres' => 'Test',
            'apellidos' => 'Owner',
            'created_by_id' => $this->admin->id,
            'updated_by_id' => $this->admin->id,
        ]);

        $paciente = Paciente::query()->create([
            'propietario_id' => $prop->id,
            'nombre' => 'Firulais',
            'especie' => 'canino',
            'created_by_id' => $this->admin->id,
            'updated_by_id' => $this->admin->id,
        ]);

        $hc = HistoriaClinica::query()->create([
            'paciente_id' => $paciente->id,
            'created_by_id' => $this->admin->id,
            'updated_by_id' => $this->admin->id,
        ]);

        $consulta = Consulta::query()->create([
            'historia_clinica_id' => $hc->id,
            'atendido_at' => now(),
            'created_by_id' => $this->admin->id,
            'updated_by_id' => $this->admin->id,
        ]);

        $producto = Producto::query()->findOrFail($inv['producto']->id);
        $producto->update(['medicamento' => true]);

        app(SyncConsultaPlanTratamiento::class)->handle(
            $consulta,
            [
                'estado' => 'activo',
                'lineas' => [[
                    'medicamento' => $producto->nombre,
                    'producto_id' => $producto->id,
                    'cantidad' => '3',
                ]],
            ],
            (string) $this->admin->id,
            (string) $inv['sede']->id,
        );

        $loteA = ProductoLote::query()
            ->where('producto_id', $producto->id)
            ->where('numero_lote', 'LOTE-A')
            ->first();

        $loteB = ProductoLote::query()
            ->where('producto_id', $producto->id)
            ->where('numero_lote', 'LOTE-B')
            ->first();

        expect((float) (string) $loteA->cantidad)->toBe(2.0)
            ->and((float) (string) $loteB->cantidad)->toBe(5.0)
            ->and(InventarioScenario::stockEnSede((string) $producto->id, (string) $inv['sede']->id))->toBe(7.0);
    });
});

it('revierte y vuelve a descontar al editar plan de tratamiento', function (): void {
    $inv = InventarioScenario::seed(
        $this->tenant,
        $this->slug,
        (string) $this->admin->id,
        10.0,
    );

    TenantContext::runForSlug($this->slug, function () use ($inv): void {
        $prop = Propietario::query()->create([
            'tipo_documento' => 'DNI',
            'numero_documento' => '87654321',
            'nombres' => 'Test',
            'apellidos' => 'Owner',
            'created_by_id' => $this->admin->id,
            'updated_by_id' => $this->admin->id,
        ]);

        $paciente = Paciente::query()->create([
            'propietario_id' => $prop->id,
            'nombre' => 'Michi',
            'especie' => 'felino',
            'created_by_id' => $this->admin->id,
            'updated_by_id' => $this->admin->id,
        ]);

        $hc = HistoriaClinica::query()->create([
            'paciente_id' => $paciente->id,
            'created_by_id' => $this->admin->id,
            'updated_by_id' => $this->admin->id,
        ]);

        $consulta = Consulta::query()->create([
            'historia_clinica_id' => $hc->id,
            'atendido_at' => now(),
            'created_by_id' => $this->admin->id,
            'updated_by_id' => $this->admin->id,
        ]);

        $producto = Producto::query()->findOrFail($inv['producto']->id);
        $producto->update(['medicamento' => true]);

        $sync = app(SyncConsultaPlanTratamiento::class);
        $payload = [
            'estado' => 'activo',
            'lineas' => [[
                'medicamento' => $producto->nombre,
                'producto_id' => $producto->id,
                'cantidad' => '2',
            ]],
        ];

        $sync->handle($consulta, $payload, (string) $this->admin->id, (string) $inv['sede']->id);
        expect(InventarioScenario::stockEnSede((string) $producto->id, (string) $inv['sede']->id))->toBe(8.0);

        $payload['lineas'][0]['cantidad'] = '4';
        $sync->handle($consulta->fresh(), $payload, (string) $this->admin->id, (string) $inv['sede']->id);
        expect(InventarioScenario::stockEnSede((string) $producto->id, (string) $inv['sede']->id))->toBe(6.0);
    });
});

it('ajuste a cantidad baja descuenta FEFO y sube con SIN-LOTE', function (): void {
    $inv = $this->scenario;
    $lotes = app(InventarioLoteService::class);
    $productoId = (string) $inv['producto']->id;
    $sedeId = (string) $inv['sede']->id;
    $uid = (string) $this->admin->id;

    TenantContext::runForSlug($this->slug, function () use ($lotes, $productoId, $sedeId, $uid): void {
        $lotes->registrarEntrada($productoId, $sedeId, '5', 'LOTE-A', now()->addDays(5)->toDateString(), 't', $uid);
        $lotes->registrarEntrada($productoId, $sedeId, '5', 'LOTE-B', now()->addDays(40)->toDateString(), 't', $uid);

        $lotes->ajustarACantidad($productoId, $sedeId, '7', 'Ajuste test baja', $uid);
        expect(InventarioScenario::stockEnSede($productoId, $sedeId))->toBe(7.0);

        $loteA = ProductoLote::query()
            ->where('producto_id', $productoId)
            ->where('sede_id', $sedeId)
            ->where('numero_lote', 'LOTE-A')
            ->value('cantidad');
        expect(round((float) (string) $loteA, 3))->toBe(2.0);

        $lotes->ajustarACantidad($productoId, $sedeId, '10', 'Ajuste test sube', $uid);
        expect(InventarioScenario::stockEnSede($productoId, $sedeId))->toBe(10.0);

        $sinLote = ProductoLote::query()
            ->where('producto_id', $productoId)
            ->where('sede_id', $sedeId)
            ->where('numero_lote', InventarioLoteService::LOTE_SIN_ESPECIFICAR)
            ->value('cantidad');
        expect(round((float) (string) $sinLote, 3))->toBe(3.0);
    });
});

it('revierte todos los lotes FEFO al editar plan que cruzó varios lotes', function (): void {
    $inv = $this->scenario;
    $lotes = app(InventarioLoteService::class);
    $productoId = (string) $inv['producto']->id;
    $sedeId = (string) $inv['sede']->id;
    $uid = (string) $this->admin->id;

    $lotes->registrarEntrada($productoId, $sedeId, '3', 'LOTE-A', now()->addDays(8)->toDateString(), 't', $uid);
    $lotes->registrarEntrada($productoId, $sedeId, '5', 'LOTE-B', now()->addDays(40)->toDateString(), 't', $uid);

    TenantContext::runForSlug($this->slug, function () use ($inv, $productoId, $sedeId, $uid): void {
        $prop = Propietario::query()->create([
            'tipo_documento' => 'DNI',
            'numero_documento' => '11223344',
            'nombres' => 'Multi',
            'apellidos' => 'Lote',
            'created_by_id' => $uid,
            'updated_by_id' => $uid,
        ]);

        $paciente = Paciente::query()->create([
            'propietario_id' => $prop->id,
            'nombre' => 'Rocky',
            'especie' => 'canino',
            'created_by_id' => $uid,
            'updated_by_id' => $uid,
        ]);

        $hc = HistoriaClinica::query()->create([
            'paciente_id' => $paciente->id,
            'created_by_id' => $uid,
            'updated_by_id' => $uid,
        ]);

        $consulta = Consulta::query()->create([
            'historia_clinica_id' => $hc->id,
            'atendido_at' => now(),
            'created_by_id' => $uid,
            'updated_by_id' => $uid,
        ]);

        $producto = Producto::query()->findOrFail($productoId);
        $producto->update(['medicamento' => true]);

        $sync = app(SyncConsultaPlanTratamiento::class);
        $sync->handle(
            $consulta,
            [
                'estado' => 'activo',
                'lineas' => [[
                    'medicamento' => $producto->nombre,
                    'producto_id' => $producto->id,
                    'cantidad' => '5',
                ]],
            ],
            $uid,
            $sedeId,
        );

        $loteA = ProductoLote::query()->where('producto_id', $productoId)->where('numero_lote', 'LOTE-A')->first();
        $loteB = ProductoLote::query()->where('producto_id', $productoId)->where('numero_lote', 'LOTE-B')->first();
        expect(round((float) (string) $loteA->cantidad, 3))->toBe(0.0)
            ->and(round((float) (string) $loteB->cantidad, 3))->toBe(3.0)
            ->and(InventarioScenario::stockEnSede($productoId, $sedeId))->toBe(3.0);

        $sync->handle(
            $consulta->fresh(),
            [
                'estado' => 'activo',
                'lineas' => [[
                    'medicamento' => $producto->nombre,
                    'producto_id' => $producto->id,
                    'cantidad' => '1',
                ]],
            ],
            $uid,
            $sedeId,
        );

        $loteA = ProductoLote::query()->where('producto_id', $productoId)->where('numero_lote', 'LOTE-A')->first();
        $loteB = ProductoLote::query()->where('producto_id', $productoId)->where('numero_lote', 'LOTE-B')->first();
        expect(round((float) (string) $loteA->cantidad, 3))->toBe(2.0)
            ->and(round((float) (string) $loteB->cantidad, 3))->toBe(5.0)
            ->and(InventarioScenario::stockEnSede($productoId, $sedeId))->toBe(7.0);
    });
});
