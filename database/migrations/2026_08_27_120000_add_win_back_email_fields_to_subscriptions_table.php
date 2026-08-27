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
            $table->string('win_back_email', 255)->nullable()->after('win_back_phone');
            $table->string('win_back_token', 64)->nullable()->unique()->after('win_back_email');
            $table->string('win_back_channel', 20)->nullable()->after('win_back_token');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['win_back_email', 'win_back_token', 'win_back_channel']);
        });
    }
};
