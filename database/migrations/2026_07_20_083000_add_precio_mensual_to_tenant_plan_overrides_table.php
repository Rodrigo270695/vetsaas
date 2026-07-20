<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_plan_overrides', function (Blueprint $table): void {
            $table->decimal('precio_mensual', 10, 2)->nullable()->after('extra');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_plan_overrides', function (Blueprint $table): void {
            $table->dropColumn('precio_mensual');
        });
    }
};
