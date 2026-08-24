<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modo asesora: flag en settings + catálogo de clínicas de origen + FK en pacientes.
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('cfg_clinic_settings')
                && ! Schema::hasColumn('cfg_clinic_settings', 'modo_asesora_activo')
            ) {
                Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                    $table->boolean('modo_asesora_activo')->default(false)->after('bot_ia_respuestas_activo');
                });
            }

            if (! Schema::hasTable('clinicas_asesoradas')) {
                Schema::create('clinicas_asesoradas', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->string('nombre', 200);
                    $table->string('ruc', 11)->nullable();
                    $table->string('direccion', 255)->nullable();
                    $table->unsignedBigInteger('distrito_id')->nullable();
                    $table->string('distrito', 120)->nullable();
                    $table->string('provincia', 120)->nullable();
                    $table->string('departamento', 120)->nullable();
                    $table->boolean('activo')->default(true);
                    $table->foreignUuid('created_by_id')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                    $table->foreignUuid('updated_by_id')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                    $table->timestampsTz();
                    $table->softDeletesTz();

                    $table->index('activo');
                    $table->index('nombre');
                });
            }

            if (Schema::hasTable('pacientes')
                && ! Schema::hasColumn('pacientes', 'clinica_asesorada_id')
            ) {
                Schema::table('pacientes', function (Blueprint $table): void {
                    $table->foreignUuid('clinica_asesorada_id')
                        ->nullable()
                        ->after('propietario_id')
                        ->constrained('clinicas_asesoradas')
                        ->nullOnDelete();
                    $table->index('clinica_asesorada_id');
                });
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('pacientes')
                && Schema::hasColumn('pacientes', 'clinica_asesorada_id')
            ) {
                Schema::table('pacientes', function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('clinica_asesorada_id');
                });
            }

            Schema::dropIfExists('clinicas_asesoradas');

            if (Schema::hasTable('cfg_clinic_settings')
                && Schema::hasColumn('cfg_clinic_settings', 'modo_asesora_activo')
            ) {
                Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                    $table->dropColumn('modo_asesora_activo');
                });
            }
        });
    }
};
