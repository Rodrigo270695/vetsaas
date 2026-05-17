<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('hotel_estancia_tarifas', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                /** Slug del catálogo (`HotelCatalogoTipoEstancia`). Precio por noche sugerido en caja. */
                $table->string('tipo_estancia', 80);
                $table->decimal('precio_lista', 12, 2);
                $table->char('moneda', 3)->default('PEN');
                $table->boolean('activo')->default(true);
                $table->timestampsTz();

                $table->unique('tipo_estancia');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('hotel_estancia_tarifas');
        });
    }
};
