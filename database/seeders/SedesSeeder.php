<?php

namespace Database\Seeders;

use App\Models\Sede;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class SedesSeeder extends Seeder
{
    /**
     * @return list<array<string, mixed>>
     */
    private const SEDES_PLANTILLA = [
        [
            'codigo' => 'LIM-01',
            'nombre' => 'Sede Lima Centro',
            'direccion' => 'Av. Arequipa 1234',
            'distrito' => 'Lince',
            'provincia' => 'Lima',
            'departamento' => 'Lima',
            'telefono' => '+51 1 555-0101',
            'email' => 'lima@vetsaas.pe',
            'serie_factura' => 'F001',
            'serie_boleta' => 'B001',
            'activa' => true,
        ],
        [
            'codigo' => 'AQP-01',
            'nombre' => 'Sede Arequipa',
            'direccion' => 'Calle Mercaderes 250',
            'distrito' => 'Cercado',
            'provincia' => 'Arequipa',
            'departamento' => 'Arequipa',
            'telefono' => '+51 54 555-0202',
            'email' => 'arequipa@vetsaas.pe',
            'serie_factura' => 'F002',
            'serie_boleta' => 'B002',
            'activa' => true,
        ],
        [
            'codigo' => 'CUS-01',
            'nombre' => 'Sede Cusco',
            'direccion' => 'Av. El Sol 480',
            'distrito' => 'Cusco',
            'provincia' => 'Cusco',
            'departamento' => 'Cusco',
            'telefono' => '+51 84 555-0303',
            'email' => 'cusco@vetsaas.pe',
            'serie_factura' => 'F003',
            'serie_boleta' => 'B003',
            'activa' => false,
        ],
    ];

    public function run(): void
    {
        $tenants = Tenant::query()->orderBy('slug')->get(['id']);

        if ($tenants->isEmpty()) {
            $this->command?->warn('SedesSeeder: no hay tenants en public.tenants; no se crean sedes.');

            return;
        }

        foreach ($tenants as $tenant) {
            foreach (self::SEDES_PLANTILLA as $fila) {
                Sede::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'codigo' => $fila['codigo'],
                    ],
                    [
                        ...$fila,
                        'tenant_id' => $tenant->id,
                    ],
                );
            }
        }

        $this->command?->info('SedesSeeder: sedes demo por tenant ('.$tenants->count().' clínica(s)).');
    }
}
