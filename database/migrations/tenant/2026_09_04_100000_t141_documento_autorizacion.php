<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('documento_autorizacion_plantillas')) {
                Schema::create('documento_autorizacion_plantillas', function (Blueprint $table) {
                    $table->uuid('id')->primary();
                    $table->string('nombre', 160);
                    $table->string('descripcion', 500)->nullable();
                    $table->text('cuerpo');
                    $table->boolean('activo')->default(true);
                    $table->foreignUuid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignUuid('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestampsTz();
                    $table->index('activo');
                });
            }

            if (! Schema::hasTable('documento_autorizacion_envios')) {
                Schema::create('documento_autorizacion_envios', function (Blueprint $table) {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('plantilla_id')
                        ->nullable()
                        ->constrained('documento_autorizacion_plantillas')
                        ->nullOnDelete();
                    $table->foreignUuid('consulta_id')->constrained('consultas')->cascadeOnDelete();
                    $table->foreignUuid('paciente_id')->constrained('pacientes')->cascadeOnDelete();
                    $table->foreignUuid('propietario_id')->nullable()->constrained('propietarios')->nullOnDelete();
                    $table->string('titulo', 180);
                    $table->text('cuerpo_snapshot');
                    $table->string('token', 64)->unique();
                    $table->string('estado', 20)->default('pendiente');
                    $table->timestampTz('expires_at');
                    $table->timestampTz('firmado_at')->nullable();
                    $table->string('firmante_nombre', 180)->nullable();
                    $table->string('firmante_documento', 40)->nullable();
                    $table->string('firma_path')->nullable();
                    $table->string('pdf_path')->nullable();
                    $table->string('ip', 45)->nullable();
                    $table->boolean('enviado_whatsapp')->default(false);
                    $table->boolean('enviado_email')->default(false);
                    $table->foreignUuid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestampsTz();
                    $table->index(['consulta_id', 'estado']);
                    $table->index('paciente_id');
                });
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('documento_autorizacion_envios');
            Schema::dropIfExists('documento_autorizacion_plantillas');
        });
    }
};
