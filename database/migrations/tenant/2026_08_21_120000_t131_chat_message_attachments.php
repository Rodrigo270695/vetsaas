<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('chat_messages')) {
                return;
            }

            Schema::table('chat_messages', function (Blueprint $table): void {
                if (! Schema::hasColumn('chat_messages', 'attachment_path')) {
                    $table->string('attachment_path', 500)->nullable()->after('body');
                }
                if (! Schema::hasColumn('chat_messages', 'attachment_name')) {
                    $table->string('attachment_name', 255)->nullable()->after('attachment_path');
                }
                if (! Schema::hasColumn('chat_messages', 'attachment_mime')) {
                    $table->string('attachment_mime', 120)->nullable()->after('attachment_name');
                }
                if (! Schema::hasColumn('chat_messages', 'attachment_size')) {
                    $table->unsignedInteger('attachment_size')->nullable()->after('attachment_mime');
                }
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('chat_messages') || ! Schema::hasColumn('chat_messages', 'attachment_path')) {
                return;
            }

            Schema::table('chat_messages', function (Blueprint $table): void {
                $table->dropColumn([
                    'attachment_path',
                    'attachment_name',
                    'attachment_mime',
                    'attachment_size',
                ]);
            });
        });
    }
};
