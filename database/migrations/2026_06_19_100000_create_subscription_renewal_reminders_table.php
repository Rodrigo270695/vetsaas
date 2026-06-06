<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_renewal_reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subscription_id')
                ->constrained('subscriptions')
                ->cascadeOnDelete();
            $table->string('reminder_kind', 10);
            $table->timestampTz('anchor_at');
            $table->string('channel', 20)->default('whatsapp');
            $table->string('destinatario', 150);
            $table->timestampTz('sent_at');
            $table->timestampsTz();

            $table->unique(['subscription_id', 'reminder_kind', 'anchor_at'], 'uq_subscription_renewal_reminders');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE subscription_renewal_reminders ADD CONSTRAINT subscription_renewal_reminders_kind_chk CHECK (reminder_kind IN ('7d','1d'))");
            DB::statement("ALTER TABLE subscription_renewal_reminders ADD CONSTRAINT subscription_renewal_reminders_channel_chk CHECK (channel IN ('whatsapp','email'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_renewal_reminders');
    }
};
