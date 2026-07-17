<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('grooming_turno_fotos')) {
                return;
            }

            Schema::create('grooming_turno_fotos', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('grooming_turno_id')
                    ->constrained('grooming_turnos')
                    ->cascadeOnDelete();
                $table->string('tipo', 32)->default('proceso');
                $table->string('path', 500);
                $table->string('caption', 255)->nullable();
                $table->timestampTz('enviado_whatsapp_at')->nullable();
                $table->foreignUuid('created_by_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestampsTz();

                $table->index(['grooming_turno_id', 'tipo']);
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('grooming_turno_fotos');
        });
    }
};
