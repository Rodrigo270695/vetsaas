<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuenta cada «Ahora no». A la tercera, el aviso es obligatorio.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_product_reviews')) {
            return;
        }

        Schema::table('tenant_product_reviews', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenant_product_reviews', 'prompt_dismiss_count')) {
                $table->unsignedTinyInteger('prompt_dismiss_count')->default(0)->after('prompt_dismissed_on');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_product_reviews')) {
            return;
        }

        Schema::table('tenant_product_reviews', function (Blueprint $table): void {
            if (Schema::hasColumn('tenant_product_reviews', 'prompt_dismiss_count')) {
                $table->dropColumn('prompt_dismiss_count');
            }
        });
    }
};
