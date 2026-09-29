<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resultados externos (PDF o imagen) que el paciente trae de otra clínica.
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('consulta_resultados') || ! Schema::hasTable('consultas')) {
                return;
            }

            Schema::create('consulta_resultados', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('consulta_id')
                    ->constrained('consultas')
                    ->cascadeOnDelete();
                $table->string('archivo_path', 500);
                $table->string('original_name', 255);
                $table->string('mime', 120);
                $table->unsignedInteger('bytes')->nullable();
                $table->unsignedSmallInteger('orden')->default(0);
                $table->uuid('created_by_id')->nullable();
                $table->timestampsTz();

                $table->index(['consulta_id', 'orden']);
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('consulta_resultados');
        });
    }
};
