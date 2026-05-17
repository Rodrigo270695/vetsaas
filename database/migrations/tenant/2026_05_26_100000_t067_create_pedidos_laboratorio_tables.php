<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('pedidos_laboratorio', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('paciente_id')
                    ->constrained('pacientes')
                    ->cascadeOnDelete();
                $table->foreignUuid('consulta_id')
                    ->nullable()
                    ->constrained('consultas')
                    ->nullOnDelete();
                $table->foreignUuid('veterinario_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->foreignUuid('sede_id')
                    ->nullable()
                    ->constrained('sedes')
                    ->nullOnDelete();
                $table->timestampTz('solicitado_at');
                $table->string('estado', 24)->default('borrador');
                $table->string('laboratorio_destino', 200)->nullable();
                $table->text('observaciones')->nullable();
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

                $table->index(['solicitado_at', 'estado']);
                $table->index('paciente_id');
                $table->index('consulta_id');
            });

            Schema::create('pedido_laboratorio_lineas', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('pedido_laboratorio_id')
                    ->constrained('pedidos_laboratorio')
                    ->cascadeOnDelete();
                $table->string('nombre_examen', 500);
                $table->text('indicaciones')->nullable();
                $table->text('resultado')->nullable();
                $table->timestampTz('resultado_at')->nullable();
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestampsTz();

                $table->index(['pedido_laboratorio_id', 'orden']);
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('pedido_laboratorio_lineas');
            Schema::dropIfExists('pedidos_laboratorio');
        });
    }
};
