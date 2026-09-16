<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('whatsapp_campanas')) {
                Schema::create('whatsapp_campanas', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->string('nombre');
                    $table->string('imagen_path')->nullable();
                    $table->json('variantes');
                    $table->unsignedTinyInteger('tope_diario')->default(50);
                    $table->unsignedTinyInteger('intervalo_minutos')->default(12);
                    $table->time('hora_inicio')->default('09:00');
                    $table->time('hora_fin')->default('18:00');
                    $table->string('estado', 20)->default('borrador');
                    $table->timestampTz('last_sent_at')->nullable();
                    $table->timestampTz('started_at')->nullable();
                    $table->timestampTz('paused_at')->nullable();
                    $table->foreignUuid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestampsTz();

                    $table->index('estado');
                });
            }

            if (! Schema::hasTable('whatsapp_campana_destinatarios')) {
                Schema::create('whatsapp_campana_destinatarios', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('campana_id')
                        ->constrained('whatsapp_campanas')
                        ->cascadeOnDelete();
                    $table->foreignUuid('propietario_id')
                        ->constrained('propietarios')
                        ->cascadeOnDelete();
                    $table->string('telefono_normalizado', 16);
                    $table->string('nombre_snapshot');
                    $table->string('mascota_nombres')->nullable();
                    $table->unsignedTinyInteger('variante_index')->nullable();
                    $table->text('cuerpo_enviado')->nullable();
                    $table->string('estado', 20)->default('pendiente');
                    $table->text('error')->nullable();
                    $table->timestampTz('enviado_at')->nullable();
                    $table->timestampsTz();

                    $table->unique(['campana_id', 'telefono_normalizado']);
                    $table->index(['campana_id', 'estado']);
                    $table->index('propietario_id');
                });
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('whatsapp_campana_destinatarios');
            Schema::dropIfExists('whatsapp_campanas');
        });
    }
};
