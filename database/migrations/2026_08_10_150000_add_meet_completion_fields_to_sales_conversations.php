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
            if (! Schema::hasColumn('sales_conversations', 'meet_completed_at')) {
                $table->timestamp('meet_completed_at')->nullable()->after('meet_notified_at');
            }
            if (! Schema::hasColumn('sales_conversations', 'meet_outcome_note')) {
                $table->string('meet_outcome_note', 500)->nullable()->after('meet_completed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_conversations', function (Blueprint $table): void {
            $cols = array_values(array_filter(
                ['meet_completed_at', 'meet_outcome_note'],
                static fn (string $col): bool => Schema::hasColumn('sales_conversations', $col),
            ));

            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
