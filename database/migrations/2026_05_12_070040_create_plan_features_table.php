<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('feature', 60);
            $table->integer('valor_int')->nullable();
            $table->boolean('valor_bool')->nullable();
            $table->string('valor_str', 50)->nullable();
            $table->unique(['plan_id', 'feature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
