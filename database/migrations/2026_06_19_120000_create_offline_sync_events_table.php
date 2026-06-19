<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_sync_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_uuid')->unique();
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            $table->string('type', 64);
            $table->json('payload');
            $table->string('status', 20)->default('synced');
            $table->string('resource_type', 64)->nullable();
            $table->uuid('resource_id')->nullable();
            $table->string('resource_label', 120)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('synced_at')->useCurrent();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'synced_at']);
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_sync_events');
    }
};
