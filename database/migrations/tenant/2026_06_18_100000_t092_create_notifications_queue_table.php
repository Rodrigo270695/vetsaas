<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('notifications_queue', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tipo', 40);
                $table->string('canal', 20)->default('whatsapp');
                $table->string('destinatario', 150);
                $table->string('destinatario_nombre', 100)->nullable();
                $table->string('asunto', 200)->nullable();
                $table->text('cuerpo');
                $table->string('referencia_tipo', 30)->nullable();
                $table->uuid('referencia_id')->nullable();
                $table->string('dedupe_key', 120)->nullable();
                $table->timestampTz('enviar_at');
                $table->unsignedSmallInteger('prioridad')->default(5);
                $table->string('estado', 20)->default('pendiente');
                $table->unsignedSmallInteger('intentos')->default(0);
                $table->unsignedSmallInteger('max_intentos')->default(3);
                $table->timestampTz('ultimo_intento_at')->nullable();
                $table->text('error_mensaje')->nullable();
                $table->string('proveedor_msg_id', 200)->nullable();
                $table->timestampsTz();

                $table->index(['enviar_at', 'prioridad']);
                $table->index(['referencia_tipo', 'referencia_id']);
            });

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE notifications_queue ADD CONSTRAINT notifications_queue_canal_chk CHECK (canal IN ('whatsapp','email','sms'))");
                DB::statement("ALTER TABLE notifications_queue ADD CONSTRAINT notifications_queue_estado_chk CHECK (estado IN ('pendiente','procesando','enviado','fallido','cancelado'))");
                DB::statement('CREATE UNIQUE INDEX uq_notifications_queue_dedupe ON notifications_queue (dedupe_key) WHERE dedupe_key IS NOT NULL');
                DB::statement('CREATE INDEX idx_notifications_queue_pendientes ON notifications_queue (enviar_at, prioridad) WHERE estado = \'pendiente\'');
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('notifications_queue');
        });
    }
};
