<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('portal_avisos')) {
                Schema::create('portal_avisos', function (Blueprint $table) {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('portal_propietario_id')
                        ->constrained('portal_propietarios')
                        ->cascadeOnDelete();
                    $table->foreignUuid('paciente_id')->nullable()->constrained('pacientes')->nullOnDelete();
                    $table->string('tipo', 40);
                    $table->string('titulo', 180);
                    $table->string('cuerpo', 500);
                    $table->string('url', 500)->nullable();
                    $table->timestampTz('leido_at')->nullable();
                    $table->timestampsTz();
                    $table->index(['portal_propietario_id', 'created_at']);
                });
            }

            if (! Schema::hasTable('portal_push_subscriptions')) {
                Schema::create('portal_push_subscriptions', function (Blueprint $table) {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('portal_propietario_id')
                        ->constrained('portal_propietarios')
                        ->cascadeOnDelete();
                    $table->string('endpoint', 500)->unique();
                    $table->string('public_key', 255)->nullable();
                    $table->string('auth_token', 255);
                    $table->string('content_encoding', 32)->default('aes128gcm');
                    $table->timestampsTz();
                    $table->index('portal_propietario_id');
                });
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('portal_push_subscriptions');
            Schema::dropIfExists('portal_avisos');
        });
    }
};
