<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('categorias_productos', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('parent_id')->nullable();
                $table->string('nombre', 120);
                $table->string('slug', 140)->nullable();
                $table->text('descripcion')->nullable();
                $table->unsignedSmallInteger('orden')->default(0);
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

                $table->index('parent_id');
                $table->index('activo');
                $table->index('orden');
                $table->unique('slug');
            });

            Schema::table('categorias_productos', function (Blueprint $table) {
                $table->foreign('parent_id')
                    ->references('id')
                    ->on('categorias_productos')
                    ->nullOnDelete();
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('categorias_productos');
        });
    }
};
