<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS chk_subscriptions_ciclo');
        DB::statement(
            "ALTER TABLE subscriptions ADD CONSTRAINT chk_subscriptions_ciclo CHECK (ciclo IN ('mensual','trimestral','semestral','anual'))"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS chk_subscriptions_ciclo');
        DB::statement(
            "ALTER TABLE subscriptions ADD CONSTRAINT chk_subscriptions_ciclo CHECK (ciclo IN ('mensual','anual'))"
        );
    }
};
