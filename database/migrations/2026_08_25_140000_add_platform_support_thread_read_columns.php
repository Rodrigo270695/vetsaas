<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_support_threads')) {
            return;
        }

        Schema::table('platform_support_threads', function (Blueprint $table): void {
            if (! Schema::hasColumn('platform_support_threads', 'from_clinic')) {
                $table->boolean('from_clinic')->default(false)->after('last_preview');
            }
            if (! Schema::hasColumn('platform_support_threads', 'platform_last_read_at')) {
                $table->timestampTz('platform_last_read_at')->nullable()->after('from_clinic');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_support_threads')) {
            return;
        }

        Schema::table('platform_support_threads', function (Blueprint $table): void {
            if (Schema::hasColumn('platform_support_threads', 'platform_last_read_at')) {
                $table->dropColumn('platform_last_read_at');
            }
            if (Schema::hasColumn('platform_support_threads', 'from_clinic')) {
                $table->dropColumn('from_clinic');
            }
        });
    }
};
