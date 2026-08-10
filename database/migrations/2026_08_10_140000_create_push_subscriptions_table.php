<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('push_subscriptions')) {
            return;
        }

        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('endpoint', 500)->unique();
            $table->string('public_key')->nullable();
            $table->string('auth_token');
            $table->string('content_encoding', 32)->default('aesgcm');
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
