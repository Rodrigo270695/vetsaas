<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'last_path')) {
                $table->string('last_path', 512)->nullable()->after('last_seen_at');
            }

            if (! Schema::hasColumn('users', 'last_module')) {
                $table->string('last_module', 64)->nullable()->after('last_path');
            }

            if (! Schema::hasColumn('users', 'last_path_at')) {
                $table->timestampTz('last_path_at')->nullable()->after('last_module');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['last_path_at', 'last_module', 'last_path'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
