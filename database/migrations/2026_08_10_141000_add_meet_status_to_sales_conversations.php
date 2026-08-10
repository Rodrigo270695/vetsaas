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
            if (! Schema::hasColumn('sales_conversations', 'meet_status')) {
                $table->string('meet_status', 32)->nullable()->after('google_event_id');
            }
            if (! Schema::hasColumn('sales_conversations', 'meet_proposed_at')) {
                $table->timestamp('meet_proposed_at')->nullable()->after('meet_status');
            }
            if (! Schema::hasColumn('sales_conversations', 'meet_notified_at')) {
                $table->timestamp('meet_notified_at')->nullable()->after('meet_proposed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_conversations', function (Blueprint $table): void {
            $cols = array_values(array_filter(
                ['meet_status', 'meet_proposed_at', 'meet_notified_at'],
                static fn (string $col): bool => Schema::hasColumn('sales_conversations', $col),
            ));

            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
