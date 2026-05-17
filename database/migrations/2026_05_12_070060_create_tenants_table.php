<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 60)->unique();
            $table->string('schema_name', 60)->unique();
            $table->string('razon_social', 200);
            $table->string('nombre_comercial', 150)->nullable();
            $table->string('ruc', 11)->nullable()->unique();
            $table->string('email_admin', 150)->unique();
            $table->string('telefono', 20)->nullable();
            $table->foreignId('distrito_id')
                ->nullable()
                ->constrained('distritos')
                ->nullOnDelete();
            $table->string('direccion', 255)->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->text('nubefact_token_enc')->nullable();
            $table->string('nubefact_ruc', 11)->nullable();
            $table->boolean('sunat_configurado')->default(false);
            $table->string('estado', 20)->default('trial');
            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampTz('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->boolean('onboarding_completado')->default(false);
            $table->unsignedSmallInteger('onboarding_paso')->default(0);
            $table->string('timezone', 50)->default('America/Lima');
            $table->string('locale', 10)->default('es_PE');
            $table->string('canal_adquisicion', 50)->nullable();
            $table->uuid('referido_por_tenant_id')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->foreign('referido_por_tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tenants ADD CONSTRAINT chk_tenants_slug_format CHECK (slug ~ '^[a-z0-9\\-]+$')");
            DB::statement("ALTER TABLE tenants ADD CONSTRAINT chk_tenants_ruc_format CHECK (ruc IS NULL OR ruc ~ '^\\d{11}$')");
            DB::statement('ALTER TABLE tenants ADD CONSTRAINT chk_tenants_onboarding_paso CHECK (onboarding_paso BETWEEN 0 AND 5)');
            DB::statement("ALTER TABLE tenants ADD CONSTRAINT chk_tenants_estado CHECK (estado IN ('trial','active','suspended','cancelled'))");
            DB::statement("CREATE INDEX idx_tenants_slug ON tenants (slug) WHERE deleted_at IS NULL");
            DB::statement("CREATE INDEX idx_tenants_estado ON tenants (estado) WHERE deleted_at IS NULL");
            DB::statement("CREATE INDEX idx_tenants_trial ON tenants (trial_ends_at) WHERE estado = 'trial'");
        } else {
            Schema::table('tenants', function (Blueprint $table) {
                $table->index('slug');
                $table->index('estado');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
