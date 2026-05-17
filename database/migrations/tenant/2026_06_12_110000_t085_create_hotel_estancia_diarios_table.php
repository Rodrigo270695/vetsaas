<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('hotel_estancia_diarios', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('hotel_estancia_id')
                    ->constrained('hotel_estancias')
                    ->cascadeOnDelete();
                $table->date('fecha');
                $table->text('notas')->nullable();
                $table->foreignUuid('created_by_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestampsTz();

                $table->unique(['hotel_estancia_id', 'fecha']);
                $table->index(['hotel_estancia_id', 'fecha']);
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('hotel_estancia_diarios');
        });
    }
};
