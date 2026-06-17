<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fel_documents', function (Blueprint $table): void {
            $table->json('apisunat_payload')->nullable()->after('enlace_consulta');
        });
    }

    public function down(): void
    {
        Schema::table('fel_documents', function (Blueprint $table): void {
            $table->dropColumn('apisunat_payload');
        });
    }
};
