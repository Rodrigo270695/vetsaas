<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veterinaria_prospectos', function (Blueprint $table): void {
            if (! Schema::hasColumn('veterinaria_prospectos', 'lat')) {
                $table->decimal('lat', 10, 7)->nullable()->after('distrito');
            }
            if (! Schema::hasColumn('veterinaria_prospectos', 'lng')) {
                $table->decimal('lng', 10, 7)->nullable()->after('lat');
            }
            if (! Schema::hasColumn('veterinaria_prospectos', 'osm_id')) {
                $table->string('osm_id', 40)->nullable()->after('lng');
            }
            if (! Schema::hasColumn('veterinaria_prospectos', 'geo_source')) {
                $table->string('geo_source', 20)->nullable()->after('osm_id');
            }
            if (! Schema::hasColumn('veterinaria_prospectos', 'volante_visitado_at')) {
                $table->timestampTz('volante_visitado_at')->nullable()->after('mensaje_error');
            }
        });

        if (Schema::hasColumn('veterinaria_prospectos', 'osm_id')) {
            Schema::table('veterinaria_prospectos', function (Blueprint $table): void {
                $table->index('osm_id');
                $table->index(['lat', 'lng']);
                $table->index('volante_visitado_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('veterinaria_prospectos', function (Blueprint $table): void {
            $cols = [];
            foreach (['lat', 'lng', 'osm_id', 'geo_source', 'volante_visitado_at'] as $col) {
                if (Schema::hasColumn('veterinaria_prospectos', $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
