<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('titulo', 200);
            $table->text('mensaje');
            $table->string('tipo', 20)->default('info');
            $table->foreignUuid('plan_id_target')->nullable()->constrained('plans');
            $table->foreignUuid('tenant_id_target')->nullable()->constrained('tenants');
            $table->boolean('activo')->default(true);
            $table->timestampTz('publicado_at')->nullable();
            $table->timestampTz('expira_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE global_notifications ADD CONSTRAINT chk_global_notif_tipo CHECK (tipo IN ('info','warning','error','success','mantenimiento'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('global_notifications');
    }
};
