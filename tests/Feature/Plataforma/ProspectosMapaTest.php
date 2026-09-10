<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\VeterinariaProspecto;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PermissionsSeeder::class);
    $superRole = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    $superRole->syncPermissions(Permission::all());

    $this->superadmin = User::factory()->create([
        'email' => 'super-mapa@vetsaas.test',
        'tenant_id' => null,
        'password' => Hash::make('clave-super'),
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $this->superadmin->assignRole('superadmin');
});

it('importa veterinarias OSM con lat lng y arma ruta en Lambayeque', function (): void {
    config(['prospectos.norte_bboxes' => [
        'Lambayeque' => [-7.22, -80.12, -5.92, -79.32],
    ]]);
    config(['prospectos.places_api_key' => '']);

    Http::fake([
        '*' => Http::response([
            'elements' => [[
                'type' => 'node',
                'id' => 99,
                'lat' => -6.705,
                'lon' => -79.91,
                'tags' => [
                    'name' => 'Clínica Vet Norte',
                    'phone' => '979111222',
                    'addr:street' => 'Av. 8 de Octubre',
                ],
            ]],
        ], 200),
    ]);

    $this->actingAs($this->superadmin)
        ->from('http://127.0.0.1/plataforma/prospectos-veterinarias/mapa')
        ->post('http://127.0.0.1/plataforma/prospectos-veterinarias/mapa/import')
        ->assertRedirect();

    $row = VeterinariaProspecto::query()->where('nombre', 'Clínica Vet Norte')->first();
    expect($row)->not->toBeNull()
        ->and($row->lat)->toBeFloat()
        ->and($row->departamento)->toBe('Lambayeque');

    $this->get('http://127.0.0.1/plataforma/prospectos-veterinarias/mapa')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('plataforma/prospectos-veterinarias/mapa')
            ->where('stats.en_ruta', 1)
            ->where('ruta.0.nombre', 'Clínica Vet Norte')
        );
});
