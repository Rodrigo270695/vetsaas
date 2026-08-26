<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestampTz('win_back_pending_at')->nullable()->after('bot_ia_activado_at');
            $table->timestampTz('win_back_accepted_at')->nullable()->after('win_back_pending_at');
            $table->string('win_back_phone', 32)->nullable()->after('win_back_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['win_back_pending_at', 'win_back_accepted_at', 'win_back_phone']);
        });
    }
};
