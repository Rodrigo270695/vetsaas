<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'sub_payments_manual_reference_unique';

    public function up(): void
    {
        if (! Schema::hasTable('subscription_payments')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            return;
        }

        DB::statement(sprintf(
            "CREATE UNIQUE INDEX IF NOT EXISTS %s
             ON subscription_payments (subscription_id, pasarela_transaction_id)
             WHERE pasarela = 'manual' AND pasarela_transaction_id IS NOT NULL",
            self::INDEX_NAME,
        ));
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS '.self::INDEX_NAME);
    }
};
