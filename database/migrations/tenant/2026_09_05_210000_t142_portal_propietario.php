<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('portal_propietarios')) {
                Schema::create('portal_propietarios', function (Blueprint $table) {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('propietario_id')
                        ->unique()
                        ->constrained('propietarios')
                        ->cascadeOnDelete();
                    $table->string('pin_hash', 255)->nullable();
                    $table->timestampTz('pin_set_at')->nullable();
                    $table->unsignedSmallInteger('pin_failed_attempts')->default(0);
                    $table->timestampTz('pin_locked_until')->nullable();
                    $table->string('invite_token', 64)->nullable()->unique();
                    $table->timestampTz('invite_expires_at')->nullable();
                    $table->string('telefono_snapshot', 30)->nullable();
                    $table->string('reset_code_hash', 255)->nullable();
                    $table->timestampTz('reset_code_expires_at')->nullable();
                    $table->unsignedSmallInteger('reset_failed_attempts')->default(0);
                    $table->timestampTz('reset_last_sent_at')->nullable();
                    $table->unsignedSmallInteger('reset_sent_hour_count')->default(0);
                    $table->foreignUuid('invited_by_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestampsTz();
                });
            }

            if (! Schema::hasTable('portal_propietario_sesiones')) {
                Schema::create('portal_propietario_sesiones', function (Blueprint $table) {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('portal_propietario_id')
                        ->constrained('portal_propietarios')
                        ->cascadeOnDelete();
                    $table->string('token_hash', 64)->unique();
                    $table->string('ip', 45)->nullable();
                    $table->string('user_agent', 255)->nullable();
                    $table->timestampTz('expires_at');
                    $table->timestampTz('last_seen_at')->nullable();
                    $table->timestampTz('revoked_at')->nullable();
                    $table->timestampsTz();
                    $table->index(['portal_propietario_id', 'revoked_at']);
                });
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('portal_propietario_sesiones');
            Schema::dropIfExists('portal_propietarios');
        });
    }
};
