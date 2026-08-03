<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token one-shot post-provisión Orvae → subdominio → cambiar contraseña.
 * Más fiable que URL firmada Laravel entre hosts centrales y de tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('bootstrap_login_token', 64)->nullable()->after('must_change_password');
            $table->timestamp('bootstrap_login_expires_at')->nullable()->after('bootstrap_login_token');
            $table->index('bootstrap_login_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['bootstrap_login_token']);
            $table->dropColumn(['bootstrap_login_token', 'bootstrap_login_expires_at']);
        });
    }
};
