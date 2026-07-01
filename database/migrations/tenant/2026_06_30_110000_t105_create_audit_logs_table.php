<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('audit_logs')) {
                return;
            }

            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('usuario_id')->nullable()->index();
                $table->string('usuario_nombre', 150)->nullable();
                $table->string('usuario_email', 150)->nullable();
                $table->string('accion', 30);
                $table->string('modulo', 60);
                $table->string('tabla', 80)->nullable();
                $table->string('registro_id', 100)->nullable();
                $table->string('registro_label', 255)->nullable();
                $table->json('cambios')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 300)->nullable();
                $table->timestampTz('created_at')->useCurrent();

                $table->index(['accion', 'created_at']);
                $table->index(['modulo', 'created_at']);
                $table->index(['created_at']);
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('audit_logs');
        });
    }
};
