<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Valoraciones de VetSaaS por usuario de clínica (una por persona).
 * Se publican en la landing de Orvae. Cerrar el modal sin enviar
 * guarda `prompt_dismissed_on` para volver a mostrar al día siguiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_product_reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('comment')->nullable();
            $table->string('author_name', 160)->nullable();
            $table->string('role_label', 80)->nullable();
            $table->string('clinic_name', 180)->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->date('prompt_dismissed_on')->nullable();
            $table->boolean('published')->default(true);
            $table->timestampsTz();

            $table->index(['submitted_at', 'published']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_product_reviews');
    }
};
