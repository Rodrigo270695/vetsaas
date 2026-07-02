<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_ia_announcements', function (Blueprint $table) {
            $table->string('badge', 20)->default('nuevo')->after('title');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE bot_ia_announcements ADD CONSTRAINT chk_bot_ia_announcement_badge CHECK (badge IN ('nuevo','mejora','importante'))");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE bot_ia_announcements DROP CONSTRAINT IF EXISTS chk_bot_ia_announcement_badge');
        }

        Schema::table('bot_ia_announcements', function (Blueprint $table) {
            $table->dropColumn('badge');
        });
    }
};
