<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('pacientes')) {
                return;
            }

            if (! Schema::hasColumn('pacientes', 'petpass_status')) {
                Schema::table('pacientes', function (Blueprint $table): void {
                    $table->string('petpass_status', 32)->nullable()->after('microchip');
                });
            }

            if (! Schema::hasColumn('pacientes', 'petpass_registration_id')) {
                Schema::table('pacientes', function (Blueprint $table): void {
                    $table->string('petpass_registration_id', 64)->nullable()->after('petpass_status');
                });
            }

            if (! Schema::hasColumn('pacientes', 'petpass_public_code')) {
                Schema::table('pacientes', function (Blueprint $table): void {
                    $table->string('petpass_public_code', 16)->nullable()->after('petpass_registration_id');
                });
            }

            if (! Schema::hasColumn('pacientes', 'petpass_certificate_url')) {
                Schema::table('pacientes', function (Blueprint $table): void {
                    $table->string('petpass_certificate_url', 500)->nullable()->after('petpass_public_code');
                });
            }

            if (! Schema::hasColumn('pacientes', 'petpass_registered_at')) {
                Schema::table('pacientes', function (Blueprint $table): void {
                    $table->timestampTz('petpass_registered_at')->nullable()->after('petpass_certificate_url');
                });
            }

            if (! Schema::hasColumn('pacientes', 'petpass_lost_at')) {
                Schema::table('pacientes', function (Blueprint $table): void {
                    $table->timestampTz('petpass_lost_at')->nullable()->after('petpass_registered_at');
                });
            }

            $this->ensureIndex('pacientes', 'petpass_status', 'pacientes_petpass_status_index');
            $this->ensureIndex('pacientes', 'petpass_public_code', 'pacientes_petpass_public_code_index');
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('pacientes')) {
                return;
            }

            $this->dropIndexIfExists('pacientes_petpass_status_index');
            $this->dropIndexIfExists('pacientes_petpass_public_code_index');

            $cols = array_values(array_filter([
                Schema::hasColumn('pacientes', 'petpass_status') ? 'petpass_status' : null,
                Schema::hasColumn('pacientes', 'petpass_registration_id') ? 'petpass_registration_id' : null,
                Schema::hasColumn('pacientes', 'petpass_public_code') ? 'petpass_public_code' : null,
                Schema::hasColumn('pacientes', 'petpass_certificate_url') ? 'petpass_certificate_url' : null,
                Schema::hasColumn('pacientes', 'petpass_registered_at') ? 'petpass_registered_at' : null,
                Schema::hasColumn('pacientes', 'petpass_lost_at') ? 'petpass_lost_at' : null,
            ]));

            if ($cols !== []) {
                Schema::table('pacientes', function (Blueprint $table) use ($cols): void {
                    $table->dropColumn($cols);
                });
            }
        });
    }

    private function ensureIndex(string $table, string $column, string $indexName): void
    {
        if (! Schema::hasColumn($table, $column) || $this->indexExists($indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->index($column);
        });
    }

    private function dropIndexIfExists(string $indexName): void
    {
        if (! $this->indexExists($indexName)) {
            return;
        }

        Schema::table('pacientes', function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }

    private function indexExists(string $indexName): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        $schema = config('tenant.migration_schema');
        if (! is_string($schema) || $schema === '') {
            return false;
        }

        return DB::table('pg_indexes')
            ->where('schemaname', $schema)
            ->where('indexname', $indexName)
            ->exists();
    }
};
