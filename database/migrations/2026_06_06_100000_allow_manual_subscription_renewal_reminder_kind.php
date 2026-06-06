<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE subscription_renewal_reminders DROP CONSTRAINT IF EXISTS subscription_renewal_reminders_kind_chk');
        DB::statement("ALTER TABLE subscription_renewal_reminders ADD CONSTRAINT subscription_renewal_reminders_kind_chk CHECK (reminder_kind ~ '^(manual|[0-9]+d)$')");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE subscription_renewal_reminders DROP CONSTRAINT IF EXISTS subscription_renewal_reminders_kind_chk');
        DB::statement("ALTER TABLE subscription_renewal_reminders ADD CONSTRAINT subscription_renewal_reminders_kind_chk CHECK (reminder_kind ~ '^[0-9]+d$')");
    }
};
