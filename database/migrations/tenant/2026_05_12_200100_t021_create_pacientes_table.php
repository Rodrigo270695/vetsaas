<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('pacientes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('propietario_id')
                    ->constrained('propietarios')
                    ->cascadeOnDelete();
                $table->string('nombre', 120);
                $table->string('especie', 80)->nullable();
                $table->string('raza', 120)->nullable();
                $table->char('sexo', 1)->nullable();
                $table->date('fecha_nacimiento')->nullable();
                $table->decimal('peso_kg', 7, 2)->nullable();
                $table->string('microchip', 64)->nullable();
                $table->string('color', 80)->nullable();
                $table->boolean('esterilizado')->nullable();
                $table->text('notas')->nullable();
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

                $table->index('propietario_id');
                $table->index('nombre');
                $table->index('activo');
                $table->index('microchip');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('pacientes');
        });
    }
};
