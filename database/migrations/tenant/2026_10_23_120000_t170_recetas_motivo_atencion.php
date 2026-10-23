<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recetas')) {
            return;
        }

        if (! Schema::hasColumn('recetas', 'motivo_atencion')) {
            Schema::table('recetas', function (Blueprint $table): void {
                $table->text('motivo_atencion')->nullable()->after('estado');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('recetas') && Schema::hasColumn('recetas', 'motivo_atencion')) {
            Schema::table('recetas', function (Blueprint $table): void {
                $table->dropColumn('motivo_atencion');
            });
        }
    }
};
