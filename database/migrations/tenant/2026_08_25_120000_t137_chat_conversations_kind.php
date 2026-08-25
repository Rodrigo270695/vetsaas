<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distingue hilos de equipo vs soporte plataforma (Soporte VetSaaS).
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('chat_conversations')) {
                return;
            }

            if (! Schema::hasColumn('chat_conversations', 'kind')) {
                Schema::table('chat_conversations', function (Blueprint $table): void {
                    $table->string('kind', 16)->default('team')->after('type');
                    $table->index('kind');
                });
            }
        });
    }
};
