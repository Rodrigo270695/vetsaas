<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        if (! Schema::hasColumn('platform_settings', 'in_app_assistant_daily_limit')) {
            Schema::table('platform_settings', function (Blueprint $table): void {
                $table->unsignedSmallInteger('in_app_assistant_daily_limit')
                    ->default(40)
                    ->after('brevo_configurado');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        if (Schema::hasColumn('platform_settings', 'in_app_assistant_daily_limit')) {
            Schema::table('platform_settings', function (Blueprint $table): void {
                $table->dropColumn('in_app_assistant_daily_limit');
            });
        }
    }
};
