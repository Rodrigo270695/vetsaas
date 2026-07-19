<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->string('petpass_status', 32)->nullable()->after('microchip');
            $table->string('petpass_registration_id', 64)->nullable()->after('petpass_status');
            $table->string('petpass_public_code', 16)->nullable()->after('petpass_registration_id');
            $table->string('petpass_certificate_url', 500)->nullable()->after('petpass_public_code');
            $table->timestampTz('petpass_registered_at')->nullable()->after('petpass_certificate_url');
            $table->timestampTz('petpass_lost_at')->nullable()->after('petpass_registered_at');

            $table->index('petpass_status');
            $table->index('petpass_public_code');
        });
    }

    public function down(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->dropIndex(['petpass_status']);
            $table->dropIndex(['petpass_public_code']);
            $table->dropColumn([
                'petpass_status',
                'petpass_registration_id',
                'petpass_public_code',
                'petpass_certificate_url',
                'petpass_registered_at',
                'petpass_lost_at',
            ]);
        });
    }
};
