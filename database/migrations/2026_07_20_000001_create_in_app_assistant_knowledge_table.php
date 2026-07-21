<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('in_app_assistant_knowledge', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 160)->unique();
            $table->string('scope', 16);
            $table->string('section', 24);
            $table->string('title', 200);
            $table->longText('content');
            $table->json('keywords')->nullable();
            $table->json('url_patterns')->nullable();
            $table->json('component_patterns')->nullable();
            $table->json('required_permissions')->nullable();
            $table->string('permission_mode', 8)->default('any');
            $table->json('allowed_roles')->nullable();
            $table->json('actions')->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'scope', 'priority']);
            $table->index(['section', 'is_active']);
            $table->index(['scope', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('in_app_assistant_knowledge');
    }
};
