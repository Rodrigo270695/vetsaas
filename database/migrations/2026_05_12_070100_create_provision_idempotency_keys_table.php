<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provision_idempotency_keys', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('key', 120)->unique();
            $table->string('source', 30)->default('orvae');
            $table->uuid('tenant_id')->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->json('response_body');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('expires_at')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE provision_idempotency_keys ADD CONSTRAINT chk_provision_idem_source CHECK (source IN ('orvae','aulavirtual','manual'))");
            DB::statement('CREATE INDEX idx_provision_idem_expires ON provision_idempotency_keys (expires_at) WHERE expires_at IS NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('provision_idempotency_keys');
    }
};
