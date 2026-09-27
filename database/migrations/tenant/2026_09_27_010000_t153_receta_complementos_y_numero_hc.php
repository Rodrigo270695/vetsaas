<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('historias_clinicas') && ! Schema::hasColumn('historias_clinicas', 'numero')) {
                Schema::table('historias_clinicas', function (Blueprint $table): void {
                    $table->unsignedInteger('numero')->nullable()->unique();
                });

                $ids = DB::table('historias_clinicas')->orderBy('created_at')->orderBy('id')->pluck('id');
                $numero = 1;
                foreach ($ids as $id) {
                    DB::table('historias_clinicas')->where('id', $id)->update(['numero' => $numero]);
                    $numero++;
                }
            }

            if (Schema::hasTable('recetas') && ! Schema::hasColumn('recetas', 'examenes_complementarios')) {
                Schema::table('recetas', function (Blueprint $table): void {
                    $table->text('examenes_complementarios')->nullable()->after('observaciones');
                    $table->date('consulta_control_at')->nullable()->after('examenes_complementarios');
                    $table->text('signos_alarma')->nullable()->after('consulta_control_at');
                });
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('recetas') && Schema::hasColumn('recetas', 'signos_alarma')) {
                Schema::table('recetas', function (Blueprint $table): void {
                    $table->dropColumn(['examenes_complementarios', 'consulta_control_at', 'signos_alarma']);
                });
            }

            if (Schema::hasTable('historias_clinicas') && Schema::hasColumn('historias_clinicas', 'numero')) {
                Schema::table('historias_clinicas', function (Blueprint $table): void {
                    $table->dropUnique(['numero']);
                    $table->dropColumn('numero');
                });
            }
        });
    }
};
