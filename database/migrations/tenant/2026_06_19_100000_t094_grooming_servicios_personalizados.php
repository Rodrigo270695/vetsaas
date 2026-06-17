<?php

use App\Database\Migrations\TenantMigration;
use App\Grooming\GroomingCatalogoMode;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $schema = config('tenant.migration_schema');
        $safe = is_string($schema) ? str_replace('"', '', $schema) : null;

        $this->runInTenant(function (): void {
            if (! Schema::hasColumn('cfg_clinic_settings', 'grooming_catalogo_personalizado')) {
                Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                    $table->boolean('grooming_catalogo_personalizado')->default(false);
                });
            }

            if (! Schema::hasTable('grooming_servicios')) {
                Schema::create('grooming_servicios', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->string('nombre', 200);
                    $table->string('categoria', 80)->nullable();
                    $table->decimal('precio_lista', 12, 2)->default(0);
                    $table->char('moneda', 3)->default('PEN');
                    $table->unsignedSmallInteger('duracion_minutos')->default(60);
                    $table->boolean('activo')->default(true);
                    $table->unsignedSmallInteger('orden')->default(0);
                    $table->timestampsTz();
                });
            }

            if (! Schema::hasColumn('grooming_turnos', 'grooming_servicio_id')) {
                Schema::table('grooming_turnos', function (Blueprint $table): void {
                    $table->foreignUuid('grooming_servicio_id')
                        ->nullable()
                        ->after('servicio')
                        ->constrained('grooming_servicios')
                        ->nullOnDelete();
                });
            }
        });

        if ($safe === null) {
            return;
        }

        $slug = DB::table('tenants')->where('schema_name', $safe)->value('slug');

        if ($slug !== GroomingCatalogoMode::PILOT_TENANT_SLUG) {
            return;
        }

        DB::statement('SET search_path TO "'.$safe.'", public');

        if (Schema::hasTable('cfg_clinic_settings') && Schema::hasColumn('cfg_clinic_settings', 'grooming_catalogo_personalizado')) {
            DB::table('cfg_clinic_settings')->update(['grooming_catalogo_personalizado' => true]);
        }
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasColumn('grooming_turnos', 'grooming_servicio_id')) {
                Schema::table('grooming_turnos', function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('grooming_servicio_id');
                });
            }

            Schema::dropIfExists('grooming_servicios');

            if (Schema::hasColumn('cfg_clinic_settings', 'grooming_catalogo_personalizado')) {
                Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                    $table->dropColumn('grooming_catalogo_personalizado');
                });
            }
        });
    }
};
