<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat v2: reply, menciones, mute, adjuntos múltiples, retención.
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('chat_messages')) {
                return;
            }

            Schema::table('chat_messages', function (Blueprint $table): void {
                if (! Schema::hasColumn('chat_messages', 'reply_to_id')) {
                    $table->uuid('reply_to_id')->nullable()->after('user_id');
                }
                if (! Schema::hasColumn('chat_messages', 'mentioned_user_ids')) {
                    $table->json('mentioned_user_ids')->nullable()->after('body');
                }
            });

            try {
                Schema::table('chat_messages', function (Blueprint $table): void {
                    $table->index('reply_to_id');
                });
            } catch (\Throwable) {
                // índice ya existe
            }

            if (Schema::hasTable('chat_participants') && ! Schema::hasColumn('chat_participants', 'muted_at')) {
                Schema::table('chat_participants', function (Blueprint $table): void {
                    $table->timestampTz('muted_at')->nullable()->after('last_read_at');
                });
            }

            if (! Schema::hasTable('chat_message_attachments')) {
                Schema::create('chat_message_attachments', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('message_id')
                        ->constrained('chat_messages')
                        ->cascadeOnDelete();
                    $table->string('path', 500);
                    $table->string('name', 255);
                    $table->string('mime', 120)->nullable();
                    $table->unsignedInteger('size')->nullable();
                    $table->timestampTz('created_at')->useCurrent();

                    $table->index('message_id');
                });
            }

            if (Schema::hasTable('cfg_clinic_settings')
                && ! Schema::hasColumn('cfg_clinic_settings', 'chat_retention_days')
            ) {
                Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                    $table->unsignedSmallInteger('chat_retention_days')->nullable();
                });
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('cfg_clinic_settings') && Schema::hasColumn('cfg_clinic_settings', 'chat_retention_days')) {
                Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                    $table->dropColumn('chat_retention_days');
                });
            }

            Schema::dropIfExists('chat_message_attachments');

            if (Schema::hasTable('chat_participants') && Schema::hasColumn('chat_participants', 'muted_at')) {
                Schema::table('chat_participants', function (Blueprint $table): void {
                    $table->dropColumn('muted_at');
                });
            }

            if (Schema::hasTable('chat_messages')) {
                Schema::table('chat_messages', function (Blueprint $table): void {
                    if (Schema::hasColumn('chat_messages', 'mentioned_user_ids')) {
                        $table->dropColumn('mentioned_user_ids');
                    }
                    if (Schema::hasColumn('chat_messages', 'reply_to_id')) {
                        $table->dropColumn('reply_to_id');
                    }
                });
            }
        });
    }
};
