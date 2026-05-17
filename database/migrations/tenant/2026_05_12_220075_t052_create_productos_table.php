<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('productos', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('categoria_id')
                    ->nullable()
                    ->constrained('categorias_productos')
                    ->nullOnDelete();
                $table->string('nombre', 255);
                $table->string('slug', 160)->nullable();
                $table->text('descripcion')->nullable();
                $table->string('sku', 64)->nullable();
                $table->string('codigo_barras', 64)->nullable();
                $table->string('unidad', 20)->default('UN');
                $table->decimal('precio_venta', 10, 2)->nullable();
                $table->boolean('medicamento')->default(false);
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

                $table->index('categoria_id');
                $table->index('activo');
                $table->index('nombre');
                $table->unique('slug');
                $table->unique('sku');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('productos');
        });
    }
};
