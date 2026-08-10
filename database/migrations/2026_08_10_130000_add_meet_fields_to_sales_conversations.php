<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_conversations', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales_conversations', 'meet_at')) {
                $table->timestamp('meet_at')->nullable()->after('demo_followup_sent_at');
            }
            if (! Schema::hasColumn('sales_conversations', 'meet_link')) {
                $table->string('meet_link', 500)->nullable()->after('meet_at');
            }
            if (! Schema::hasColumn('sales_conversations', 'google_event_id')) {
                $table->string('google_event_id', 255)->nullable()->after('meet_link');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_conversations', function (Blueprint $table): void {
            $cols = array_values(array_filter(
                ['meet_at', 'meet_link', 'google_event_id'],
                static fn (string $col): bool => Schema::hasColumn('sales_conversations', $col),
            ));

            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
