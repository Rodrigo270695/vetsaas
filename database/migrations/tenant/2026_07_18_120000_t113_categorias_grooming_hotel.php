<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('categorias_grooming')) {
                Schema::create('categorias_grooming', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->string('nombre', 80);
                    $table->boolean('activo')->default(true);
                    $table->timestampsTz();
                    $table->softDeletesTz();

                    $table->unique('nombre');
                    $table->index(['activo']);
                });
            }

            if (! Schema::hasTable('categorias_hotel')) {
                Schema::create('categorias_hotel', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->string('nombre', 80);
                    $table->boolean('activo')->default(true);
                    $table->timestampsTz();
                    $table->softDeletesTz();

                    $table->unique('nombre');
                    $table->index(['activo']);
                });
            }

            if (Schema::hasTable('grooming_servicios') && ! Schema::hasColumn('grooming_servicios', 'categoria_id')) {
                Schema::table('grooming_servicios', function (Blueprint $table): void {
                    $table->foreignUuid('categoria_id')
                        ->nullable()
                        ->after('categoria')
                        ->constrained('categorias_grooming')
                        ->nullOnDelete();
                });

                $this->backfillCategorias(
                    sourceTable: 'grooming_servicios',
                    catalogTable: 'categorias_grooming',
                );
            }

            if (Schema::hasTable('hotel_tipos_estancia') && ! Schema::hasColumn('hotel_tipos_estancia', 'categoria_id')) {
                Schema::table('hotel_tipos_estancia', function (Blueprint $table): void {
                    $table->foreignUuid('categoria_id')
                        ->nullable()
                        ->after('categoria')
                        ->constrained('categorias_hotel')
                        ->nullOnDelete();
                });

                $this->backfillCategorias(
                    sourceTable: 'hotel_tipos_estancia',
                    catalogTable: 'categorias_hotel',
                );
            }
        });
    }

    /**
     * @param  non-empty-string  $sourceTable
     * @param  non-empty-string  $catalogTable
     */
    private function backfillCategorias(string $sourceTable, string $catalogTable): void
    {
        $now = now();
        $nombres = DB::table($sourceTable)
            ->whereNotNull('categoria')
            ->where('categoria', '!=', '')
            ->distinct()
            ->pluck('categoria');

        $map = [];

        foreach ($nombres as $nombreRaw) {
            $nombre = trim((string) $nombreRaw);
            if ($nombre === '') {
                continue;
            }

            $nombre = mb_substr($nombre, 0, 80);
            $existing = DB::table($catalogTable)
                ->where('nombre', $nombre)
                ->whereNull('deleted_at')
                ->value('id');

            if ($existing) {
                $map[$nombre] = (string) $existing;

                continue;
            }

            $id = (string) Str::uuid();
            DB::table($catalogTable)->insert([
                'id' => $id,
                'nombre' => $nombre,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $map[$nombre] = $id;
        }

        foreach ($map as $nombre => $id) {
            DB::table($sourceTable)
                ->where('categoria', $nombre)
                ->whereNull('categoria_id')
                ->update(['categoria_id' => $id]);
        }
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasColumn('grooming_servicios', 'categoria_id')) {
                Schema::table('grooming_servicios', function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('categoria_id');
                });
            }

            if (Schema::hasColumn('hotel_tipos_estancia', 'categoria_id')) {
                Schema::table('hotel_tipos_estancia', function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('categoria_id');
                });
            }

            Schema::dropIfExists('categorias_grooming');
            Schema::dropIfExists('categorias_hotel');
        });
    }
};
