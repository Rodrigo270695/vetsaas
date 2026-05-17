<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained('plans');
            $table->string('estado', 20)->default('trial');
            $table->string('ciclo', 10)->default('mensual');
            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampTz('current_period_start')->nullable();
            $table->timestampTz('current_period_end')->nullable();
            $table->timestampTz('grace_ends_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->text('cancel_feedback')->nullable();
            $table->decimal('precio_pactado', 10, 2);
            $table->decimal('descuento_pct', 5, 2)->default(0);
            $table->foreignUuid('promo_code_id')->nullable()->constrained('promo_codes');
            $table->timestampTz('proximo_cobro_at')->nullable();
            $table->string('metodo_pago_token', 200)->nullable();
            $table->timestampsTz();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT chk_subscriptions_estado CHECK (estado IN ('trial','active','grace','suspended','cancelled'))");
            DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT chk_subscriptions_ciclo CHECK (ciclo IN ('mensual','anual'))");
            DB::statement("CREATE UNIQUE INDEX idx_subscriptions_tenant_active ON subscriptions (tenant_id) WHERE estado <> 'cancelled'");
            DB::statement("CREATE INDEX idx_subscriptions_cobro ON subscriptions (proximo_cobro_at) WHERE estado = 'active'");
            DB::statement("CREATE INDEX idx_subscriptions_grace ON subscriptions (grace_ends_at) WHERE estado = 'grace'");
        } else {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->index('tenant_id');
                $table->index('proximo_cobro_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
