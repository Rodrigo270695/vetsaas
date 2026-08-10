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
            if (! Schema::hasColumn('sales_conversations', 'demo_sent_at')) {
                $table->timestamp('demo_sent_at')->nullable()->after('last_reactivation_at');
            }
            if (! Schema::hasColumn('sales_conversations', 'demo_followup_sent_at')) {
                $table->timestamp('demo_followup_sent_at')->nullable()->after('demo_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_conversations', function (Blueprint $table): void {
            $cols = array_values(array_filter(
                ['demo_sent_at', 'demo_followup_sent_at'],
                static fn (string $col): bool => Schema::hasColumn('sales_conversations', $col),
            ));

            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
