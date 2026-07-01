<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base de conocimiento del asistente IA de la clínica (schema tenant).
 *
 * Secciones: faq | horario | politica | servicio | contacto | general
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('clinic_bot_knowledge')) {
                return;
            }

            Schema::create('clinic_bot_knowledge', function (Blueprint $table): void {
                $table->id();
                $table->string('section', 50)->index();
                $table->string('slug', 100)->unique();
                $table->string('title', 200);
                $table->longText('content');
                $table->json('meta')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('clinic_bot_knowledge');
        });
    }
};
