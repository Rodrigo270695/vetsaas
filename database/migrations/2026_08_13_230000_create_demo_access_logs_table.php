<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orígenes geográficos de accesos al tenant demo (mapa de demos).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('demo_access_logs')) {
            return;
        }

        Schema::create('demo_access_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['lat', 'lng']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_access_logs');
    }
};
