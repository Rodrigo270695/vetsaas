<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('promotions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name', 120);
                $table->string('code', 30)->nullable();
                $table->text('description')->nullable();
                $table->string('discount_type', 20);
                $table->decimal('value', 10, 2);
                $table->string('scope', 30);
                $table->string('condition_type', 40)->default('none');
                $table->string('grooming_service_slug', 80)->nullable();
                $table->boolean('auto_apply')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestampTz('valid_from')->nullable();
                $table->timestampTz('valid_until')->nullable();
                $table->unsignedInteger('max_uses')->nullable();
                $table->unsignedInteger('uses_count')->default(0);
                $table->unsignedSmallInteger('priority')->default(100);
                $table->foreignUuid('created_by_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->foreignUuid('updated_by_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestampsTz();
                $table->softDeletesTz();

                $table->unique('code');
                $table->index('is_active');
                $table->index(['scope', 'condition_type']);
            });

            Schema::table('ventas', function (Blueprint $table): void {
                $table->foreignUuid('promotion_id')
                    ->nullable()
                    ->after('descuento_monto')
                    ->constrained('promotions')
                    ->nullOnDelete();
                $table->string('promotion_name_snapshot', 120)->nullable()->after('promotion_id');
            });

            Schema::table('venta_lineas', function (Blueprint $table): void {
                $table->foreignUuid('promotion_id')
                    ->nullable()
                    ->after('descuento_pct')
                    ->constrained('promotions')
                    ->nullOnDelete();
            });

            DB::table('promotions')->insert([
                'id' => (string) Str::uuid(),
                'name' => '2ª mascota grooming −50%',
                'code' => null,
                'description' => 'Si el cliente ya pagó un baño grooming hoy para otra mascota, este servicio tiene 50% de descuento.',
                'discount_type' => 'pct_line',
                'value' => '50.00',
                'scope' => 'grooming',
                'condition_type' => 'second_pet_grooming',
                'grooming_service_slug' => null,
                'auto_apply' => true,
                'is_active' => true,
                'valid_from' => null,
                'valid_until' => null,
                'max_uses' => null,
                'uses_count' => 0,
                'priority' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('venta_lineas', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('promotion_id');
            });

            Schema::table('ventas', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('promotion_id');
                $table->dropColumn('promotion_name_snapshot');
            });

            Schema::dropIfExists('promotions');
        });
    }
};
