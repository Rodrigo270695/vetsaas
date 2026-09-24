<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('internamiento_evoluciones')) {
                return;
            }

            Schema::table('internamiento_evoluciones', function (Blueprint $table): void {
                if (! Schema::hasColumn('internamiento_evoluciones', 'deshidratacion_pct')) {
                    $table->decimal('deshidratacion_pct', 5, 2)->nullable();
                }

                if (! Schema::hasColumn('internamiento_evoluciones', 'tllc_segundos')) {
                    $table->decimal('tllc_segundos', 4, 1)->nullable();
                }

                if (! Schema::hasColumn('internamiento_evoluciones', 'pas')) {
                    $table->unsignedSmallInteger('pas')->nullable();
                }

                if (! Schema::hasColumn('internamiento_evoluciones', 'pad')) {
                    $table->unsignedSmallInteger('pad')->nullable();
                }

                if (! Schema::hasColumn('internamiento_evoluciones', 'pam')) {
                    $table->unsignedSmallInteger('pam')->nullable();
                }
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('internamiento_evoluciones')) {
                return;
            }

            Schema::table('internamiento_evoluciones', function (Blueprint $table): void {
                foreach (['deshidratacion_pct', 'tllc_segundos', 'pas', 'pad', 'pam'] as $column) {
                    if (Schema::hasColumn('internamiento_evoluciones', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        });
    }
};
