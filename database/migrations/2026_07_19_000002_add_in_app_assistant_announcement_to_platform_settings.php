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

        Schema::table('platform_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('platform_settings', 'in_app_assistant_announcement_active')) {
                $table->boolean('in_app_assistant_announcement_active')
                    ->default(false)
                    ->after('in_app_assistant_daily_limit');
            }

            if (! Schema::hasColumn('platform_settings', 'in_app_assistant_announcement_version')) {
                $table->unsignedInteger('in_app_assistant_announcement_version')
                    ->default(0)
                    ->after('in_app_assistant_announcement_active');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        Schema::table('platform_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('platform_settings', 'in_app_assistant_announcement_version')) {
                $table->dropColumn('in_app_assistant_announcement_version');
            }
            if (Schema::hasColumn('platform_settings', 'in_app_assistant_announcement_active')) {
                $table->dropColumn('in_app_assistant_announcement_active');
            }
        });
    }
};
