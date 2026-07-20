<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('consultas')) {
                return;
            }

            if (! Schema::hasColumn('consultas', 'cita_id')) {
                Schema::table('consultas', function (Blueprint $table): void {
                    $table->foreignUuid('cita_id')
                        ->nullable()
                        ->after('historia_clinica_id')
                        ->constrained('citas')
                        ->nullOnDelete();
                    $table->unique('cita_id');
                });
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('consultas') && Schema::hasColumn('consultas', 'cita_id')) {
                Schema::table('consultas', function (Blueprint $table): void {
                    $table->dropUnique(['cita_id']);
                    $table->dropConstrainedForeignId('cita_id');
                });
            }
        });
    }
};
