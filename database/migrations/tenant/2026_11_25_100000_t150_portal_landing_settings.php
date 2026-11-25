<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('cfg_clinic_settings')) {
                return;
            }

            Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                if (! Schema::hasColumn('cfg_clinic_settings', 'portal_landing_activo')) {
                    $table->boolean('portal_landing_activo')->default(false);
                }
            });

            if (! Schema::hasTable('portal_propietarios')) {
                return;
            }

            Schema::table('portal_propietarios', function (Blueprint $table): void {
                if (! Schema::hasColumn('portal_propietarios', 'landing_otp_hash')) {
                    $table->string('landing_otp_hash', 255)->nullable();
                }
                if (! Schema::hasColumn('portal_propietarios', 'landing_otp_expires_at')) {
                    $table->timestampTz('landing_otp_expires_at')->nullable();
                }
                if (! Schema::hasColumn('portal_propietarios', 'landing_otp_failed_attempts')) {
                    $table->unsignedSmallInteger('landing_otp_failed_attempts')->default(0);
                }
                if (! Schema::hasColumn('portal_propietarios', 'landing_otp_last_sent_at')) {
                    $table->timestampTz('landing_otp_last_sent_at')->nullable();
                }
                if (! Schema::hasColumn('portal_propietarios', 'landing_otp_sent_hour_count')) {
                    $table->unsignedSmallInteger('landing_otp_sent_hour_count')->default(0);
                }
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('cfg_clinic_settings') && Schema::hasColumn('cfg_clinic_settings', 'portal_landing_activo')) {
                Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                    $table->dropColumn('portal_landing_activo');
                });
            }

            if (! Schema::hasTable('portal_propietarios')) {
                return;
            }

            foreach ([
                'landing_otp_hash',
                'landing_otp_expires_at',
                'landing_otp_failed_attempts',
                'landing_otp_last_sent_at',
                'landing_otp_sent_hour_count',
            ] as $col) {
                if (Schema::hasColumn('portal_propietarios', $col)) {
                    Schema::table('portal_propietarios', function (Blueprint $table) use ($col): void {
                        $table->dropColumn($col);
                    });
                }
            }
        });
    }
};