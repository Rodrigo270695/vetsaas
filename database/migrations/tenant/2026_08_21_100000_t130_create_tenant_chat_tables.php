<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat interno del tenant: DMs y grupos entre usuarios de la misma clínica.
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('chat_conversations', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type', 16); // direct | group
                $table->string('name', 120)->nullable();
                /** Par ordenado userA:userB para DMs únicos (solo type=direct). */
                $table->string('direct_key', 80)->nullable()->unique();
                $table->foreignUuid('created_by_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestampsTz();

                $table->index('type');
            });

            Schema::create('chat_participants', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('conversation_id')
                    ->constrained('chat_conversations')
                    ->cascadeOnDelete();
                $table->foreignUuid('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();
                $table->timestampTz('last_read_at')->nullable();
                $table->timestampTz('joined_at')->useCurrent();
                $table->timestampsTz();

                $table->unique(['conversation_id', 'user_id']);
                $table->index('user_id');
            });

            Schema::create('chat_messages', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('conversation_id')
                    ->constrained('chat_conversations')
                    ->cascadeOnDelete();
                $table->foreignUuid('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();
                $table->text('body');
                $table->timestampTz('created_at')->useCurrent();

                $table->index(['conversation_id', 'created_at']);
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('chat_messages');
            Schema::dropIfExists('chat_participants');
            Schema::dropIfExists('chat_conversations');
        });
    }
};
