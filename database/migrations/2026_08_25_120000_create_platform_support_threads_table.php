<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_support_threads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->uuid('conversation_id');
            $table->foreignUuid('support_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestampTz('last_message_at')->nullable();
            $table->string('last_preview', 280)->nullable();
            $table->boolean('from_clinic')->default(false);
            $table->timestampTz('platform_last_read_at')->nullable();
            $table->timestampsTz();

            $table->unique('tenant_id');
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_support_threads');
    }
};
