<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos por venta (soporta cobro mixto: efectivo + yape, etc.).
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('venta_pagos')) {
                Schema::create('venta_pagos', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('venta_id')
                        ->constrained('ventas')
                        ->cascadeOnDelete();
                    $table->string('metodo', 24);
                    $table->decimal('monto', 14, 2);
                    $table->decimal('monto_recibido', 14, 2)->nullable();
                    $table->decimal('vuelto', 14, 2)->nullable();
                    $table->unsignedSmallInteger('orden')->default(0);
                    $table->timestampsTz();

                    $table->index(['venta_id', 'orden']);
                    $table->index(['venta_id', 'metodo']);
                });
            }

            if (! Schema::hasTable('ventas') || ! Schema::hasTable('venta_pagos')) {
                return;
            }

            // Backfill: 1 línea por venta histórica con método único.
            $existsPagos = DB::table('venta_pagos')->exists();
            if ($existsPagos) {
                return;
            }

            $ventas = DB::table('ventas')
                ->whereNull('deleted_at')
                ->whereNotNull('metodo_pago')
                ->where('metodo_pago', '!=', '')
                ->select(['id', 'metodo_pago', 'total', 'monto_recibido', 'vuelto', 'created_at', 'updated_at'])
                ->orderBy('created_at')
                ->get();

            $now = now();
            $rows = [];
            foreach ($ventas as $venta) {
                $rows[] = [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'venta_id' => $venta->id,
                    'metodo' => (string) $venta->metodo_pago,
                    'monto' => $venta->total,
                    'monto_recibido' => $venta->metodo_pago === 'efectivo' ? $venta->monto_recibido : null,
                    'vuelto' => $venta->metodo_pago === 'efectivo' ? $venta->vuelto : null,
                    'orden' => 0,
                    'created_at' => $venta->created_at ?? $now,
                    'updated_at' => $venta->updated_at ?? $now,
                ];
                if (count($rows) >= 200) {
                    DB::table('venta_pagos')->insert($rows);
                    $rows = [];
                }
            }
            if ($rows !== []) {
                DB::table('venta_pagos')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('venta_pagos');
        });
    }
};
