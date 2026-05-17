<?php

namespace Database\Seeders;

use App\Models\CategoriaProducto;
use App\Models\Producto;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo demo de categorías y productos por tenant (schemas PostgreSQL).
 *
 * Idempotente: usa `updateOrCreate` por `slug` (categorías) y `sku` (productos).
 * Requiere tablas tenant migradas, incluida `unidades_medida` (códigos de unidad válidos).
 *
 * Uso:
 *
 *   php artisan db:seed --class=InventarioCategoriasYProductosSeeder
 *
 * Recorre todos los tenants en `public.tenants` y si existe el schema,
 * siembra en él. Para un solo schema (p. ej. tras `vetsaas:tenant-migrate`):
 *
 *   (new \Database\Seeders\InventarioCategoriasYProductosSeeder)->seedForSchema('vet_mi_clinica');
 */
class InventarioCategoriasYProductosSeeder extends Seeder
{
    /**
     * @return list<array{nombre:string,slug:string,descripcion:string,orden:int}>
     */
    private const CATEGORIAS = [
        [
            'nombre' => 'Medicamentos',
            'slug' => 'medicamentos',
            'descripcion' => 'Fármacos y presentaciones de venta en clínica.',
            'orden' => 10,
        ],
        [
            'nombre' => 'Vacunas',
            'slug' => 'vacunas',
            'descripcion' => 'Vacunas e inmunización.',
            'orden' => 20,
        ],
        [
            'nombre' => 'Alimentos y snacks',
            'slug' => 'alimentos-snacks',
            'descripcion' => 'Concentrados, húmedos y premios.',
            'orden' => 30,
        ],
        [
            'nombre' => 'Higiene y accesorios',
            'slug' => 'higiene-accesorios',
            'descripcion' => 'Shampoos, collares, transporte, etc.',
            'orden' => 40,
        ],
        [
            'nombre' => 'Insumos clínico-quirúrgicos',
            'slug' => 'insumos-clinicos',
            'descripcion' => 'Gasas, jeringas, guantes, material de curación.',
            'orden' => 50,
        ],
    ];

    /**
     * @return list<array{sku:string,nombre:string,slug:string,descripcion:string,categoria_slug:string,unidad:string,precio_venta:string,medicamento:bool,activo:bool}>
     */
    private const PRODUCTOS = [
        [
            'sku' => 'DEMO-MED-AMOX-250',
            'nombre' => 'Amoxicilina 250 mg (comp.)',
            'slug' => 'amoxicilina-250mg-comp',
            'descripcion' => 'Antibiótico uso veterinario — presentación demo.',
            'categoria_slug' => 'medicamentos',
            'unidad' => 'COMP',
            'precio_venta' => '2.50',
            'medicamento' => true,
            'activo' => true,
        ],
        [
            'sku' => 'DEMO-MED-MEL-INJ',
            'nombre' => 'Meloxicam 2 mg/ml inyectable',
            'slug' => 'meloxicam-2mg-ml-inyectable',
            'descripcion' => 'Antiinflamatorio no esteroideo — frasco demo.',
            'categoria_slug' => 'medicamentos',
            'unidad' => 'ML',
            'precio_venta' => '18.90',
            'medicamento' => true,
            'activo' => true,
        ],
        [
            'sku' => 'DEMO-VAC-OCTU',
            'nombre' => 'Vacuna polivalente canina (octuple)',
            'slug' => 'vacuna-polivalente-canina-octuple',
            'descripcion' => 'Refuerzo anual sujeto a calendario clínico.',
            'categoria_slug' => 'vacunas',
            'unidad' => 'DOSIS',
            'precio_venta' => '85.00',
            'medicamento' => true,
            'activo' => true,
        ],
        [
            'sku' => 'DEMO-VAC-RAB',
            'nombre' => 'Vacuna antirrábica inactivada',
            'slug' => 'vacuna-antirrabica-inactivada',
            'descripcion' => 'Dosis única según normativa local.',
            'categoria_slug' => 'vacunas',
            'unidad' => 'DOSIS',
            'precio_venta' => '35.00',
            'medicamento' => true,
            'activo' => true,
        ],
        [
            'sku' => 'DEMO-ALI-CON-ADU',
            'nombre' => 'Concentrado adulto razas medianas 15 kg',
            'slug' => 'concentrado-adulto-razas-medianas-15kg',
            'descripcion' => 'Bolsa demo — referencia genérica.',
            'categoria_slug' => 'alimentos-snacks',
            'unidad' => 'KG',
            'precio_venta' => '189.00',
            'medicamento' => false,
            'activo' => true,
        ],
        [
            'sku' => 'DEMO-ALI-SNACK-DEN',
            'nombre' => 'Snack dental perro (bolsa)',
            'slug' => 'snack-dental-perro-bolsa',
            'descripcion' => 'Premio masticable — presentación demo.',
            'categoria_slug' => 'alimentos-snacks',
            'unidad' => 'BOLSA',
            'precio_venta' => '24.50',
            'medicamento' => false,
            'activo' => true,
        ],
        [
            'sku' => 'DEMO-HIG-SHAM-250',
            'nombre' => 'Shampoo hipoalergénico 250 ml',
            'slug' => 'shampoo-hipoalergenico-250ml',
            'descripcion' => 'Limpieza suave para pieles sensibles.',
            'categoria_slug' => 'higiene-accesorios',
            'unidad' => 'ML',
            'precio_venta' => '32.00',
            'medicamento' => false,
            'activo' => true,
        ],
        [
            'sku' => 'DEMO-HIG-COLLAR-M',
            'nombre' => 'Collar nylon talla M',
            'slug' => 'collar-nylon-talla-m',
            'descripcion' => 'Accesorio demo — color surtido.',
            'categoria_slug' => 'higiene-accesorios',
            'unidad' => 'UN',
            'precio_venta' => '16.00',
            'medicamento' => false,
            'activo' => true,
        ],
        [
            'sku' => 'DEMO-INS-GASA-EST',
            'nombre' => 'Gasas estériles (paquete)',
            'slug' => 'gasas-esteriles-paquete',
            'descripcion' => 'Material de curación — paquete demo.',
            'categoria_slug' => 'insumos-clinicos',
            'unidad' => 'SOBRE',
            'precio_venta' => '8.75',
            'medicamento' => false,
            'activo' => true,
        ],
        [
            'sku' => 'DEMO-INS-JER-3ML',
            'nombre' => 'Jeringa desechable 3 ml',
            'slug' => 'jeringa-desechable-3ml',
            'descripcion' => 'Aguja incluida — caja unidad demo.',
            'categoria_slug' => 'insumos-clinicos',
            'unidad' => 'JERINGA',
            'precio_venta' => '0.80',
            'medicamento' => false,
            'activo' => true,
        ],
        [
            'sku' => 'DEMO-INS-GUANT-NIT',
            'nombre' => 'Guantes nitrilo talla M (caja 100)',
            'slug' => 'guantes-nitrilo-talla-m-caja-100',
            'descripcion' => 'Protección clínica — caja demo.',
            'categoria_slug' => 'insumos-clinicos',
            'unidad' => 'CAJA',
            'precio_venta' => '42.00',
            'medicamento' => false,
            'activo' => true,
        ],
    ];

    public function run(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->command?->warn('InventarioCategoriasYProductosSeeder requiere PostgreSQL. Omitido.');

            return;
        }

        $tenants = Tenant::query()->orderBy('slug')->get(['schema_name', 'slug']);

        if ($tenants->isEmpty()) {
            $this->command?->warn('No hay filas en public.tenants. Nada que sembrar.');

            return;
        }

        foreach ($tenants as $tenant) {
            $schema = (string) $tenant->schema_name;
            if ($schema === '') {
                continue;
            }
            $this->seedForSchema($schema, $tenant->slug);
        }
    }

    public function seedForSchema(string $schemaName, ?string $tenantSlug = null): void
    {
        if (! preg_match('/^[a-z_][a-z0-9_]{0,62}$/i', $schemaName)) {
            $this->command?->error('Nombre de schema inválido: '.$schemaName);

            return;
        }

        $safe = str_replace('"', '', $schemaName);

        $exists = DB::selectOne(
            'select 1 as x from information_schema.schemata where schema_name = ? limit 1',
            [$safe],
        );
        if ($exists === null) {
            $this->command?->warn('Schema no existe, se omite: '.$safe);

            return;
        }

        DB::statement('SET search_path TO "'.$safe.'", public');

        try {
            if (! Schema::hasTable('categorias_productos') || ! Schema::hasTable('productos')) {
                $this->command?->warn('Faltan tablas de inventario en '.$safe.'. Omisión.');

                return;
            }

            if (! Schema::hasTable('unidades_medida')) {
                $this->command?->warn('Falta tabla unidades_medida en '.$safe.'. Ejecuta migraciones tenant antes. Omisión.');

                return;
            }

            $slugToCategoriaId = [];

            foreach (self::CATEGORIAS as $row) {
                $cat = CategoriaProducto::query()->updateOrCreate(
                    ['slug' => $row['slug']],
                    [
                        'nombre' => $row['nombre'],
                        'descripcion' => $row['descripcion'],
                        'orden' => $row['orden'],
                        'activo' => true,
                        'parent_id' => null,
                        'created_by_id' => null,
                        'updated_by_id' => null,
                    ],
                );
                $slugToCategoriaId[$row['slug']] = $cat->id;
            }

            foreach (self::PRODUCTOS as $row) {
                $cid = $slugToCategoriaId[$row['categoria_slug']] ?? null;
                if ($cid === null) {
                    continue;
                }

                Producto::query()->updateOrCreate(
                    ['sku' => $row['sku']],
                    [
                        'categoria_id' => $cid,
                        'nombre' => $row['nombre'],
                        'slug' => $row['slug'],
                        'descripcion' => $row['descripcion'],
                        'codigo_barras' => null,
                        'unidad' => strtoupper($row['unidad']),
                        'precio_venta' => $row['precio_venta'],
                        'medicamento' => $row['medicamento'],
                        'activo' => $row['activo'],
                        'created_by_id' => null,
                        'updated_by_id' => null,
                    ],
                );
            }

            $suffix = $tenantSlug !== null ? " (tenant: {$tenantSlug})" : '';
            $this->command?->info('Inventario demo sembrado en schema `'.$safe.'`'.$suffix.'.');
        } finally {
            DB::statement('SET search_path TO public');
        }
    }
}
