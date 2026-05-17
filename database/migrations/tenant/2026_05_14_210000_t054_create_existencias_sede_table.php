<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('existencias_sede', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('producto_id')
                    ->constrained('productos')
                    ->cascadeOnDelete();
                /** UUID en `public.sedes` (sin FK cruzada entre schemas). */
                $table->uuid('sede_id');
                $table->decimal('cantidad', 14, 3)->default(0);
                $table->timestampsTz();

                $table->unique(['producto_id', 'sede_id']);
                $table->index('sede_id');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('existencias_sede');
        });
    }
};
