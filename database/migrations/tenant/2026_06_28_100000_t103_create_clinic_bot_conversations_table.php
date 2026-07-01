<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('clinic_bot_conversations')) {
                return;
            }

            Schema::create('clinic_bot_conversations', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('phone', 30)->index();
                $table->string('wa_chat_id', 80)->index();
                $table->string('client_name', 120)->nullable();
                $table->json('messages')->nullable();
                $table->unsignedSmallInteger('turn_count')->default(0);
                $table->boolean('bot_active')->default(true);
                $table->boolean('bot_paused_manually')->default(false);
                $table->timestampTz('last_message_at')->nullable();
                $table->timestampsTz();
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('clinic_bot_conversations');
        });
    }
};
