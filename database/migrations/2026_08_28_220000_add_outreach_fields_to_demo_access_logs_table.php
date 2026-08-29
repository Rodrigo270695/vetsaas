<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking de outreach comercial a leads capturados en la demo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('demo_access_logs')) {
            return;
        }

        Schema::table('demo_access_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('demo_access_logs', 'outreach_sent_at')) {
                $table->timestampTz('outreach_sent_at')->nullable()->after('lead_skipped_at');
            }
            if (! Schema::hasColumn('demo_access_logs', 'outreach_channel')) {
                $table->string('outreach_channel', 20)->nullable()->after('outreach_sent_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('demo_access_logs')) {
            return;
        }

        Schema::table('demo_access_logs', function (Blueprint $table): void {
            foreach (['outreach_channel', 'outreach_sent_at'] as $column) {
                if (Schema::hasColumn('demo_access_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
