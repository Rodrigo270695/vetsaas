<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends TenantMigration
{
    /**
     * Catálogo de unidades (sistema + personalizadas por clínica).
     * `productos.unidad` almacena el código (columna `codigo`).
     */
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('unidades_medida', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('codigo', 20);
                $table->string('nombre', 80);
                $table->boolean('es_sistema')->default(false);
                $table->boolean('activo')->default(true);
                $table->timestampsTz();
                $table->softDeletesTz();

                $table->unique('codigo');
                $table->index(['activo', 'es_sistema']);
            });

            $now = now();
            $rows = [
                ['codigo' => 'UN', 'nombre' => 'Unidad'],
                ['codigo' => 'KG', 'nombre' => 'Kilogramo'],
                ['codigo' => 'G', 'nombre' => 'Gramo'],
                ['codigo' => 'MG', 'nombre' => 'Miligramo'],
                ['codigo' => 'L', 'nombre' => 'Litro'],
                ['codigo' => 'ML', 'nombre' => 'Mililitro'],
                ['codigo' => 'M', 'nombre' => 'Metro'],
                ['codigo' => 'CM', 'nombre' => 'Centímetro'],
                ['codigo' => 'UI', 'nombre' => 'Unidad internacional (UI)'],
                ['codigo' => 'DOSIS', 'nombre' => 'Dosis'],
                ['codigo' => 'CAJA', 'nombre' => 'Caja'],
                ['codigo' => 'BLISTER', 'nombre' => 'Blíster'],
                ['codigo' => 'TIRA', 'nombre' => 'Tira'],
                ['codigo' => 'BOLSA', 'nombre' => 'Bolsa'],
                ['codigo' => 'FRASCO', 'nombre' => 'Frasco'],
                ['codigo' => 'VIAL', 'nombre' => 'Vial'],
                ['codigo' => 'AMP', 'nombre' => 'Ampolla'],
                ['codigo' => 'TUBO', 'nombre' => 'Tubo'],
                ['codigo' => 'SOBRE', 'nombre' => 'Sobre'],
                ['codigo' => 'BOTE', 'nombre' => 'Bote'],
                ['codigo' => 'ROLLO', 'nombre' => 'Rollo'],
                ['codigo' => 'PAR', 'nombre' => 'Par'],
                ['codigo' => 'COMP', 'nombre' => 'Comprimido'],
                ['codigo' => 'GOTAS', 'nombre' => 'Gotas'],
                ['codigo' => 'JERINGA', 'nombre' => 'Jeringa'],
                ['codigo' => 'TEST', 'nombre' => 'Test / kit'],
                ['codigo' => 'LATA', 'nombre' => 'Lata'],
                ['codigo' => 'PACK', 'nombre' => 'Pack'],
                ['codigo' => 'DPA', 'nombre' => 'Dosis por aplicación'],
                ['codigo' => 'ENV', 'nombre' => 'Envase'],
            ];

            foreach ($rows as $row) {
                DB::table('unidades_medida')->insert([
                    'id' => (string) Str::uuid(),
                    'codigo' => $row['codigo'],
                    'nombre' => $row['nombre'],
                    'es_sistema' => true,
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if (Schema::hasTable('productos')) {
                $existentes = DB::table('unidades_medida')
                    ->pluck('codigo')
                    ->map(fn (mixed $c): string => strtoupper((string) $c))
                    ->all();

                $usados = DB::table('productos')
                    ->select('unidad')
                    ->whereNotNull('unidad')
                    ->where('unidad', '!=', '')
                    ->distinct()
                    ->pluck('unidad');

                foreach ($usados as $codigo) {
                    $c = strtoupper(trim((string) $codigo));
                    if ($c === '' || in_array($c, $existentes, true)) {
                        continue;
                    }
                    DB::table('unidades_medida')->insert([
                        'id' => (string) Str::uuid(),
                        'codigo' => substr($c, 0, 20),
                        'nombre' => $c,
                        'es_sistema' => false,
                        'activo' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $existentes[] = substr($c, 0, 20);
                }
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('unidades_medida');
        });
    }
};
