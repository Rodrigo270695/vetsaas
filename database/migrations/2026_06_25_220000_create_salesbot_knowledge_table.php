<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base de conocimiento del bot de ventas.
 *
 * Aquí viven los planes, módulos, features y FAQs.
 * Rodrigo los actualiza en la BD → el bot los sabe en el siguiente mensaje
 * (caché de 5 minutos para evitar golpear la BD en cada mensaje).
 *
 * Estructura de secciones por producto "vetsaas":
 *   - planes      → descripción de cada plan con precio y límites
 *   - modulos     → qué hace cada módulo y cómo funciona
 *   - faqs        → preguntas frecuentes con respuestas
 *   - objeciones  → cómo manejar cada objeción típica
 *
 * Para actualizar desde tinker:
 *   $k = \App\Models\SalesBotKnowledge::where('product','vetsaas')
 *          ->where('slug','plan-pro')->first();
 *   $k->content = "...nuevo contenido...";
 *   $k->save();
 *   \Illuminate\Support\Facades\Cache::forget('salesbot_knowledge_vetsaas');
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salesbot_knowledge', function (Blueprint $table): void {
            $table->id();

            // Producto al que pertenece. "vetsaas" | "aula-virtual" | "inventario"
            // TODO — Cuando se agregue otro producto, insertar sus filas con ese slug.
            $table->string('product', 50)->default('vetsaas')->index();

            // Categoría: "plan" | "modulo" | "faq" | "objecion" | "general"
            $table->string('section', 50)->index();

            // Identificador único por entrada. Ej: "plan-pro", "modulo-grooming"
            $table->string('slug', 100)->unique();

            // Título visible (para admin/tinker). Ej: "Plan Pro"
            $table->string('title', 200);

            // Contenido en texto libre (markdown o prosa).
            // Este texto se inyecta literalmente en el system prompt del bot.
            $table->longText('content');

            // Metadatos opcionales en JSON (precio, límites, etc.)
            // Ej: {"price": 59.90, "users": 3, "patients": 300}
            $table->json('meta')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salesbot_knowledge');
    }
};
