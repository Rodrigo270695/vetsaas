<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('propietarios', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tipo_documento', 20)->nullable();
                $table->string('numero_documento', 20)->nullable();
                $table->string('nombres', 150);
                $table->string('apellidos', 150)->nullable();
                $table->string('razon_social', 200)->nullable();
                $table->string('email', 150)->nullable();
                $table->string('telefono', 20)->nullable();
                $table->string('telefono_alt', 20)->nullable();
                $table->string('direccion', 255)->nullable();
                $table->unsignedBigInteger('distrito_id')->nullable();
                $table->string('distrito', 120)->nullable();
                $table->string('provincia', 120)->nullable();
                $table->string('departamento', 120)->nullable();
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

                $table->index('activo');
                $table->index('nombres');
                $table->index('numero_documento');
                $table->index('email');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('propietarios');
        });
    }
};
