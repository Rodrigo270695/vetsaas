<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['tenant_whatsapp_sessions', 'platform_whatsapp_sessions'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                if (! Schema::hasColumn($table, 'status_changed_at')) {
                    $blueprint->timestampTz('status_changed_at')->nullable()->after('status');
                }
            });

            DB::table($table)->update([
                'status_changed_at' => DB::raw(
                    "CASE WHEN status IN ('initializing', 'authenticating') AND (phone IS NULL OR phone = '') "
                    .'THEN COALESCE(created_at, updated_at) '
                    .'ELSE COALESCE(last_synced_at, updated_at, created_at) END',
                ),
            ]);
        }
    }

    public function down(): void
    {
        foreach (['tenant_whatsapp_sessions', 'platform_whatsapp_sessions'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                if (Schema::hasColumn($table, 'status_changed_at')) {
                    $blueprint->dropColumn('status_changed_at');
                }
            });
        }
    }
};
