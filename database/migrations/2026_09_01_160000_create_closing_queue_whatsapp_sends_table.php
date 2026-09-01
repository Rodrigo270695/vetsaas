<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closing_queue_whatsapp_sends', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('row_key', 80)->unique();
            $table->string('kind', 20);
            $table->string('phone', 80)->nullable();
            $table->string('from_phone', 80)->nullable();
            $table->timestampTz('sent_at');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closing_queue_whatsapp_sends');
    }
};
