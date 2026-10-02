<?php

use App\Database\Migrations\TenantMigration;
use App\Models\DocumentoAutorizacionPlantilla;
use App\Support\Clinica\DocumentoAutorizacionRenderer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Certificado de defunción, estado de la mascota y autorización del titular.
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('defunciones')) {
                Schema::create('defunciones', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('paciente_id')->constrained('pacientes')->cascadeOnDelete();
                    $table->timestampTz('ocurrido_at');
                    $table->string('motivo', 200);
                    $table->string('sitio', 255)->nullable();
                    $table->string('testigo', 255)->nullable();
                    $table->text('comentarios')->nullable();
                    $table->foreignUuid('veterinario_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignUuid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignUuid('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestampsTz();
                    $table->softDeletesTz();

                    $table->index(['paciente_id', 'ocurrido_at']);
                });
            }

            if (Schema::hasTable('pacientes') && ! Schema::hasColumn('pacientes', 'fallecido_at')) {
                Schema::table('pacientes', function (Blueprint $table): void {
                    $table->timestampTz('fallecido_at')->nullable()->after('activo');
                });
            }

            if (Schema::hasTable('documento_autorizacion_envios')) {
                DB::statement('ALTER TABLE documento_autorizacion_envios ALTER COLUMN consulta_id DROP NOT NULL');

                if (Schema::hasTable('defunciones') && ! Schema::hasColumn('documento_autorizacion_envios', 'defuncion_id')) {
                    Schema::table('documento_autorizacion_envios', function (Blueprint $table): void {
                        $table->foreignUuid('defuncion_id')
                            ->nullable()
                            ->constrained('defunciones')
                            ->nullOnDelete();
                    });
                }
            }

            if (! Schema::hasTable('documento_autorizacion_plantillas')) {
                return;
            }

            $nombre = DocumentoAutorizacionPlantilla::NOMBRE_DEFUNCION;
            $existe = DB::table('documento_autorizacion_plantillas')->where('nombre', $nombre)->exists();
            if ($existe) {
                return;
            }

            $now = now();
            DB::table('documento_autorizacion_plantillas')->insert([
                'id' => (string) Str::uuid(),
                'nombre' => $nombre,
                'descripcion' => 'Autorización del titular para registrar el certificado de defunción.',
                'cuerpo' => DocumentoAutorizacionRenderer::cuerpoDefuncion(),
                'activo' => true,
                'created_by_id' => null,
                'updated_by_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('documento_autorizacion_envios') && Schema::hasColumn('documento_autorizacion_envios', 'defuncion_id')) {
                Schema::table('documento_autorizacion_envios', function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('defuncion_id');
                });
            }

            if (Schema::hasTable('pacientes') && Schema::hasColumn('pacientes', 'fallecido_at')) {
                Schema::table('pacientes', function (Blueprint $table): void {
                    $table->dropColumn('fallecido_at');
                });
            }

            Schema::dropIfExists('defunciones');
        });
    }
};
