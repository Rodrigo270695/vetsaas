<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade columnas de soporte interno a `subscription_payments`.
 *
 * La fila base la escribe Orvae (vía webhook de la pasarela) y sus
 * columnas originales son inmutables. Estas columnas extra son para
 * que el equipo de soporte de VetSaaS deje rastro de sus acciones
 * humanas sobre el pago, sin tocar los datos del webhook:
 *
 *   - internal_note      : notas internas del equipo de soporte
 *   - refunded_at        : timestamp del reembolso manual (si lo hubo)
 *   - refunded_by        : usuario del back-office que marcó el reembolso
 *   - refund_reason      : motivo del reembolso (auditoría)
 *   - invoice_resent_at  : última vez que se reenvió la factura al cliente
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->text('internal_note')->nullable()->after('error_mensaje');
            $table->timestampTz('refunded_at')->nullable()->after('pagado_at');
            $table->foreignUuid('refunded_by')
                ->nullable()
                ->after('refunded_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('refund_reason')->nullable()->after('refunded_by');
            $table->timestampTz('invoice_resent_at')->nullable()->after('refund_reason');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refunded_by');
            $table->dropColumn([
                'internal_note',
                'refunded_at',
                'refund_reason',
                'invoice_resent_at',
            ]);
        });
    }
};
