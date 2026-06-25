<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega `lost_at` para distinguir entre lead convertido (cerró el trato)
 * y lead perdido (no respondió tras 2 intentos de reactivación).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_conversations', function (Blueprint $table): void {
            // Fecha en que el sistema marcó este lead como perdido.
            // null = sigue activo | fecha = cerrado automáticamente.
            $table->timestamp('lost_at')->nullable()->after('converted');
        });
    }

    public function down(): void
    {
        Schema::table('sales_conversations', function (Blueprint $table): void {
            $table->dropColumn('lost_at');
        });
    }
};
