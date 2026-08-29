<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contacto opcional capturado tras entrar a la demo (celular y/o correo).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('demo_access_logs')) {
            return;
        }

        Schema::table('demo_access_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('demo_access_logs', 'clinic_name')) {
                $table->string('clinic_name', 150)->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('demo_access_logs', 'phone')) {
                $table->string('phone', 20)->nullable()->after('clinic_name');
            }
            if (! Schema::hasColumn('demo_access_logs', 'email')) {
                $table->string('email', 150)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('demo_access_logs', 'lead_captured_at')) {
                $table->timestampTz('lead_captured_at')->nullable()->after('email');
            }
            if (! Schema::hasColumn('demo_access_logs', 'lead_skipped_at')) {
                $table->timestampTz('lead_skipped_at')->nullable()->after('lead_captured_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('demo_access_logs')) {
            return;
        }

        Schema::table('demo_access_logs', function (Blueprint $table): void {
            foreach (['clinic_name', 'phone', 'email', 'lead_captured_at', 'lead_skipped_at'] as $column) {
                if (Schema::hasColumn('demo_access_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
