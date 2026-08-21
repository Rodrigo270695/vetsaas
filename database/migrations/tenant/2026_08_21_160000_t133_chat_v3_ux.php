<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat v3: editar/borrar mensajes, reacciones, pin y presencia.
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
                if (! Schema::hasColumn('chat_messages', 'edited_at')) {
                    $table->timestampTz('edited_at')->nullable()->after('created_at');
                }
                if (! Schema::hasColumn('chat_messages', 'deleted_at')) {
                    $table->timestampTz('deleted_at')->nullable()->after('edited_at');
                }
            });

            // Permitir body null en mensajes soft-deleted o solo-adjunto.
            try {
                Schema::table('chat_messages', function (Blueprint $table): void {
                    $table->text('body')->nullable()->change();
                });
            } catch (\Throwable) {
                // change() puede no estar disponible sin doctrine/dbal; no bloquear.
            }

            if (! Schema::hasTable('chat_message_reactions')) {
                Schema::create('chat_message_reactions', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('message_id')
                        ->constrained('chat_messages')
                        ->cascadeOnDelete();
                    $table->uuid('user_id');
                    $table->string('emoji', 16);
                    $table->timestampTz('created_at')->useCurrent();

                    $table->unique(['message_id', 'user_id']);
                    $table->index('message_id');
                    $table->index('user_id');
                });
            }

            if (Schema::hasTable('chat_participants')
                && ! Schema::hasColumn('chat_participants', 'pinned_at')
            ) {
                Schema::table('chat_participants', function (Blueprint $table): void {
                    $table->timestampTz('pinned_at')->nullable()->after('muted_at');
                });
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('chat_participants') && Schema::hasColumn('chat_participants', 'pinned_at')) {
                Schema::table('chat_participants', function (Blueprint $table): void {
                    $table->dropColumn('pinned_at');
                });
            }

            Schema::dropIfExists('chat_message_reactions');

            if (Schema::hasTable('chat_messages')) {
                Schema::table('chat_messages', function (Blueprint $table): void {
                    if (Schema::hasColumn('chat_messages', 'deleted_at')) {
                        $table->dropColumn('deleted_at');
                    }
                    if (Schema::hasColumn('chat_messages', 'edited_at')) {
                        $table->dropColumn('edited_at');
                    }
                });
            }
        });
    }
};
