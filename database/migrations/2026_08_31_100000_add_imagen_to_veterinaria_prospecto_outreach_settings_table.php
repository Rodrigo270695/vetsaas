<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imagen adjunta al primer mensaje de contacto (IA + WhatsApp).
 * Si no hay archivo subido, se usa la imagen por defecto de VetSaaS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veterinaria_prospecto_outreach_settings', function (Blueprint $table): void {
            $table->boolean('enviar_con_imagen')->default(true)->after('hora_envio');
            $table->string('imagen_path', 500)->nullable()->after('enviar_con_imagen');
        });
    }

    public function down(): void
    {
        Schema::table('veterinaria_prospecto_outreach_settings', function (Blueprint $table): void {
            $table->dropColumn(['enviar_con_imagen', 'imagen_path']);
        });
    }
};
