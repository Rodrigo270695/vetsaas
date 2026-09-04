<?php

declare(strict_types=1);

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            $this->addRsvpColumns('citas');
            $this->addRsvpColumns('grooming_turnos');
            $this->addRsvpColumns('hotel_estancias');
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            $this->dropRsvpColumns('citas');
            $this->dropRsvpColumns('grooming_turnos');
            $this->dropRsvpColumns('hotel_estancias');
        });
    }

    private function addRsvpColumns(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (! Schema::hasColumn($table, 'confirmed_at')) {
                $blueprint->timestampTz('confirmed_at')->nullable();
            }
            if (! Schema::hasColumn($table, 'confirmed_via')) {
                $blueprint->string('confirmed_via', 20)->nullable();
            }
            if (! Schema::hasColumn($table, 'owner_responded_at')) {
                $blueprint->timestampTz('owner_responded_at')->nullable();
            }
        });
    }

    private function dropRsvpColumns(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (Schema::hasColumn($table, 'owner_responded_at')) {
                $blueprint->dropColumn('owner_responded_at');
            }
            if (Schema::hasColumn($table, 'confirmed_via')) {
                $blueprint->dropColumn('confirmed_via');
            }
            if (Schema::hasColumn($table, 'confirmed_at')) {
                $blueprint->dropColumn('confirmed_at');
            }
        });
    }
};
