<?php

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
            if (! Schema::hasColumn('platform_settings', 'in_app_assistant_announcement_title')) {
                $table->string('in_app_assistant_announcement_title', 160)
                    ->nullable()
                    ->after('in_app_assistant_announcement_version');
            }

            if (! Schema::hasColumn('platform_settings', 'in_app_assistant_announcement_body')) {
                $table->text('in_app_assistant_announcement_body')
                    ->nullable()
                    ->after('in_app_assistant_announcement_title');
            }

            if (! Schema::hasColumn('platform_settings', 'in_app_assistant_announcement_features')) {
                $table->json('in_app_assistant_announcement_features')
                    ->nullable()
                    ->after('in_app_assistant_announcement_body');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        Schema::table('platform_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('platform_settings', 'in_app_assistant_announcement_features')) {
                $table->dropColumn('in_app_assistant_announcement_features');
            }
            if (Schema::hasColumn('platform_settings', 'in_app_assistant_announcement_body')) {
                $table->dropColumn('in_app_assistant_announcement_body');
            }
            if (Schema::hasColumn('platform_settings', 'in_app_assistant_announcement_title')) {
                $table->dropColumn('in_app_assistant_announcement_title');
            }
        });
    }
};
