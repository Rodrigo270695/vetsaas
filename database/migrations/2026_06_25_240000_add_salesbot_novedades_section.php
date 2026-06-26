<?php

declare(strict_types=1);

use App\Models\SalesBotKnowledge;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Sección "novedad" + primera entrada: facturación en Pro y Clínica.
 * firstOrCreate: no sobrescribe si ya existe en el panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('salesbot_knowledge')) {
            return;
        }

        SalesBotKnowledge::query()->firstOrCreate(
            ['slug' => 'novedad-facturacion-pro-clinica'],
            [
                'product'    => 'vetsaas',
                'section'    => 'novedad',
                'title'      => 'Plan Pro y Clínica ya emiten factura electrónica SUNAT',
                'sort_order' => 1,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
Gancho para reactivación: Desde que hablamos, los planes Pro y Clínica ya incluyen boletas y facturas electrónicas integradas con SUNAT — sin límite en Pro, hasta 200/mes en Clínica según plan.
Ideal para leads que preguntaron por facturación, comprobantes o el Plan Pro/Clínica.
Mensaje corto sugerido: "Oye, una cosa que cambió: ahora el Plan Pro ya emite boletas y facturas electrónicas directo desde el sistema. ¿Te sigue interesando?"
Cuando deje de ser novedad (todos lo sepan), desactivar esta entrada en el panel.
TXT,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        SalesBotKnowledge::flushCache('vetsaas');
    }

    public function down(): void
    {
        if (! Schema::hasTable('salesbot_knowledge')) {
            return;
        }

        SalesBotKnowledge::query()
            ->where('slug', 'novedad-facturacion-pro-clinica')
            ->delete();

        SalesBotKnowledge::flushCache('vetsaas');
    }
};
