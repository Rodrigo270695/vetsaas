<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('internamiento_signos_clinicos') || ! Schema::hasTable('internamientos')) {
                return;
            }

            Schema::create('internamiento_signos_clinicos', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('internamiento_id')
                    ->constrained('internamientos')
                    ->cascadeOnDelete();
                $table->timestampTz('registrado_at');
                $table->string('mucosas', 80)->nullable();
                $table->decimal('glucemia_mg_dl', 6, 1)->nullable();
                $table->decimal('orina_ml', 7, 1)->nullable();
                $table->string('vomito', 32)->nullable();
                $table->string('diarrea', 32)->nullable();
                $table->string('heces', 32)->nullable();
                $table->unsignedTinyInteger('bristol')->nullable();
                $table->string('alimento', 32)->nullable();
                $table->string('agua', 32)->nullable();
                $table->text('notas')->nullable();
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

                $table->index(['internamiento_id', 'registrado_at']);
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('internamiento_signos_clinicos');
        });
    }
};
