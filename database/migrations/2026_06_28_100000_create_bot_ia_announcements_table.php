<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_ia_announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 200);
            $table->text('bullet_1');
            $table->text('bullet_2')->nullable();
            $table->text('bullet_3')->nullable();
            $table->string('guide_title', 200)->nullable();
            $table->text('guide_body')->nullable();
            $table->text('guide_tip_1')->nullable();
            $table->text('guide_tip_2')->nullable();
            $table->text('guide_tip_3')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->foreignUuid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_ia_announcements');
    }
};
