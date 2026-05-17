<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('proveedores', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->char('ruc', 11);
                $table->string('razon_social', 255);
                $table->text('direccion')->nullable();
                $table->string('ubigeo_sunat', 6)->nullable();
                $table->string('estado_sunat', 32)->nullable();
                $table->string('condicion_sunat', 32)->nullable();
                $table->string('telefono', 40)->nullable();
                $table->string('email', 255)->nullable();
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

                $table->unique('ruc');
                $table->index('razon_social');
                $table->index('activo');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('proveedores');
        });
    }
};
