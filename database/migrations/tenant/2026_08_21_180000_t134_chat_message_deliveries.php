<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat v4: acuse de entrega (sent → delivered → read).
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('chat_messages')) {
                return;
            }

            if (Schema::hasTable('chat_message_deliveries')) {
                return;
            }

            Schema::create('chat_message_deliveries', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('message_id')
                    ->constrained('chat_messages')
                    ->cascadeOnDelete();
                $table->uuid('user_id');
                $table->timestampTz('delivered_at')->useCurrent();

                $table->unique(['message_id', 'user_id']);
                $table->index('message_id');
                $table->index('user_id');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('chat_message_deliveries');
        });
    }
};
