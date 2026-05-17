<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subscription_id')->constrained('subscriptions');
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('plan_id')->constrained('plans');
            $table->decimal('monto', 10, 2);
            $table->char('moneda', 3)->default('PEN');
            $table->decimal('igv_monto', 10, 2)->default(0);
            $table->decimal('descuento_monto', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('estado', 20);
            $table->string('pasarela', 30)->nullable();
            $table->string('pasarela_transaction_id', 200)->nullable();
            $table->json('pasarela_response')->nullable();
            $table->timestampTz('periodo_inicio');
            $table->timestampTz('periodo_fin');
            $table->boolean('fel_emitido')->default(false);
            $table->string('fel_numero', 15)->nullable();
            $table->text('error_mensaje')->nullable();
            $table->timestampTz('pagado_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE subscription_payments ADD CONSTRAINT chk_sub_payments_estado CHECK (estado IN ('pendiente','procesado','fallido','reembolsado'))");
            DB::statement('CREATE INDEX idx_sub_payments_tenant ON subscription_payments (tenant_id, created_at DESC)');
            DB::statement("CREATE INDEX idx_sub_payments_estado ON subscription_payments (estado) WHERE estado = 'pendiente'");
        } else {
            Schema::table('subscription_payments', function (Blueprint $table) {
                $table->index(['tenant_id', 'created_at'], 'idx_sub_payments_tenant_sqlite');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
