<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenants', 'portal_acceso_hostname')) {
                $table->string('portal_acceso_hostname', 253)->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasColumn('tenants', 'portal_acceso_hostname')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('portal_acceso_hostname');
        });
    }
};