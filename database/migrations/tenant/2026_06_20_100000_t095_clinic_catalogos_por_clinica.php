<?php

use App\Database\Migrations\TenantMigration;
use App\Support\Catalog\ClinicCatalogSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('grooming_servicios') && ! Schema::hasColumn('grooming_servicios', 'codigo_legacy')) {
                Schema::table('grooming_servicios', function (Blueprint $table): void {
                    $table->string('codigo_legacy', 80)->nullable()->after('categoria');
                });
            }

            if (Schema::hasTable('cfg_clinic_settings')) {
                if (! Schema::hasColumn('cfg_clinic_settings', 'grooming_catalogo_personalizado')) {
                    Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                        $table->boolean('grooming_catalogo_personalizado')->default(true);
                    });
                } else {
                    DB::table('cfg_clinic_settings')->update(['grooming_catalogo_personalizado' => true]);
                }

                if (! Schema::hasColumn('cfg_clinic_settings', 'hotel_catalogo_personalizado')) {
                    Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                        $table->boolean('hotel_catalogo_personalizado')->default(true);
                    });
                } else {
                    DB::table('cfg_clinic_settings')->update(['hotel_catalogo_personalizado' => true]);
                }
            }

            if (! Schema::hasTable('hotel_tipos_estancia')) {
                Schema::create('hotel_tipos_estancia', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->string('nombre', 200);
                    $table->string('categoria', 80)->nullable();
                    $table->string('codigo_legacy', 80)->nullable();
                    $table->decimal('precio_lista', 12, 2)->default(0);
                    $table->char('moneda', 3)->default('PEN');
                    $table->boolean('activo')->default(true);
                    $table->unsignedSmallInteger('orden')->default(0);
                    $table->timestampsTz();
                });
            }

            if (Schema::hasTable('hotel_estancias') && ! Schema::hasColumn('hotel_estancias', 'hotel_tipo_id')) {
                Schema::table('hotel_estancias', function (Blueprint $table): void {
                    $table->foreignUuid('hotel_tipo_id')
                        ->nullable()
                        ->after('tipo_estancia')
                        ->constrained('hotel_tipos_estancia')
                        ->nullOnDelete();
                });
            }

            ClinicCatalogSeeder::seedGroomingIfEmpty();
            ClinicCatalogSeeder::seedHotelIfEmpty();
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasColumn('hotel_estancias', 'hotel_tipo_id')) {
                Schema::table('hotel_estancias', function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('hotel_tipo_id');
                });
            }

            Schema::dropIfExists('hotel_tipos_estancia');

            if (Schema::hasColumn('cfg_clinic_settings', 'hotel_catalogo_personalizado')) {
                Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                    $table->dropColumn('hotel_catalogo_personalizado');
                });
            }

            if (Schema::hasColumn('grooming_servicios', 'codigo_legacy')) {
                Schema::table('grooming_servicios', function (Blueprint $table): void {
                    $table->dropColumn('codigo_legacy');
                });
            }
        });
    }
};
