<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('referral_code', 40)->nullable()->after('referido_por_tenant_id');
            $table->unsignedInteger('referral_days_balance')->default(0)->after('referral_code');
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->unique('referral_code');
        });

        // Backfill: código = slug en mayúsculas (sin caracteres raros).
        $tenants = DB::table('tenants')->select('id', 'slug')->whereNull('referral_code')->get();
        foreach ($tenants as $tenant) {
            $code = strtoupper(preg_replace('/[^A-Z0-9\-]/', '', strtoupper((string) $tenant->slug)) ?: 'REF');
            $base = mb_substr($code, 0, 36);
            $candidate = $base;
            $n = 1;
            while (DB::table('tenants')->where('referral_code', $candidate)->where('id', '!=', $tenant->id)->exists()) {
                $candidate = mb_substr($base, 0, 32).'-'.$n;
                $n++;
            }
            DB::table('tenants')->where('id', $tenant->id)->update(['referral_code' => $candidate]);
        }

        Schema::create('referral_ledger', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('referrer_tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('referred_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignUuid('subscription_payment_id')->nullable()->constrained('subscription_payments')->nullOnDelete();
            $table->integer('days');
            $table->string('type', 30);
            // earned | applied | manual_grant | manual_adjust
            $table->string('notes', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['referrer_tenant_id', 'type']);
            $table->index(['referred_tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_ledger');

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropUnique(['referral_code']);
            $table->dropColumn(['referral_code', 'referral_days_balance']);
        });
    }
};
