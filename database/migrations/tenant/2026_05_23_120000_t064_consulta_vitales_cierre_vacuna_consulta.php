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
            Schema::table('consultas', function (Blueprint $table): void {
                $table->decimal('temperatura_c', 4, 1)->nullable()->after('peso_kg');
                $table->unsignedSmallInteger('fc_lpm')->nullable()->after('temperatura_c');
                $table->unsignedSmallInteger('fr_rpm')->nullable()->after('fc_lpm');
                $table->timestampTz('cerrada_at')->nullable()->after('fr_rpm');
                $table->foreignUuid('cerrada_por_id')
                    ->nullable()
                    ->after('cerrada_at')
                    ->constrained('users')
                    ->nullOnDelete();
            });

            DB::table('consultas')->update([
                'cerrada_at' => DB::raw('COALESCE(updated_at, created_at)'),
            ]);

            Schema::table('vacunas_aplicadas', function (Blueprint $table): void {
                $table->foreignUuid('consulta_id')
                    ->nullable()
                    ->after('paciente_id')
                    ->constrained('consultas')
                    ->nullOnDelete();
                $table->index(['consulta_id', 'aplicada_at']);
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('vacunas_aplicadas', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('consulta_id');
            });

            Schema::table('consultas', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('cerrada_por_id');
                $table->dropColumn(['cerrada_at', 'fr_rpm', 'fc_lpm', 'temperatura_c']);
            });
        });
    }
};
