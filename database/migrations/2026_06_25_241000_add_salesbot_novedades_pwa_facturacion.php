<?php

declare(strict_types=1);

use App\Models\SalesBotKnowledge;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Novedades: PWA instalable + facturación electrónica desde caja.
 * firstOrCreate: no sobrescribe entradas editadas en el panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('salesbot_knowledge')) {
            return;
        }

        $entries = [
            [
                'slug'       => 'novedad-pwa-app-celular',
                'title'      => 'VetSaaS ahora se instala como app en el celular (PWA)',
                'sort_order' => 2,
                'content'    => <<<'TXT'
Gancho para reactivación: Ahora puedes instalar VetSaaS en tu celular o tablet como si fuera una app — sin Play Store ni App Store.
Ideal para leads que dijeron "no tengo la PC a mano", "trabajo desde el celular" o "quiero algo rápido en consulta".

Qué es: PWA (Progressive Web App). Entras a tu clínica desde el navegador y la instalas en la pantalla de inicio. Abre a pantalla completa, como app nativa.

Cómo se instala:
- Android / Chrome: botón "Instalar" o menú ⋮ → Instalar aplicación / Añadir a inicio.
- iPhone (Safari): Compartir → Añadir a pantalla de inicio.
- PC (Chrome/Edge): icono de instalar en la barra de direcciones.

Atajos directos al instalar: Nueva venta, Ventas y Sesión de caja.

Mensaje corto sugerido: "Oye, ahora VetSaaS se puede instalar en el celular como app — agenda, historial y caja desde la pantalla de inicio. ¿Quieres ver cómo queda en el tuyo?"

Cuando deje de ser novedad, desactivar esta entrada.
TXT,
            ],
            [
                'slug'       => 'novedad-facturacion-desde-caja',
                'title'      => 'Emite boleta o factura electrónica al cobrar en caja',
                'sort_order' => 3,
                'content'    => <<<'TXT'
Gancho para reactivación: Ya no hace falta ir a otro sistema para la SUNAT — en Plan Pro y Clínica emites boleta o factura electrónica en el mismo momento que registras la venta en caja.
Ideal para leads que preguntaron por comprobantes, boletas, facturas, SUNAT, Nubefact o "¿puedo facturar desde ahí?".

Cómo funciona:
1. Cobras la consulta o producto en caja (como siempre).
2. El sistema genera la boleta o factura electrónica integrada con SUNAT (vía Nubefact).
3. Recibes PDF/XML listos para enviar al cliente — sin duplicar trabajo.

Beneficio de venta: "Cierras la venta y el comprobante sale solo. Menos media hora al día en portales aparte."

Planes: incluido en Pro y Clínica (no en Free ni Starter). Los límites exactos los lee el bot desde la BD de planes.

Mensaje corto sugerido: "Una cosa buena: ahora al cobrar en caja puedes emitir boleta o factura electrónica ahí mismo, sin salir de VetSaaS. ¿Tu clínica factura con boleta o factura?"

Cuando deje de ser novedad, desactivar esta entrada.
TXT,
            ],
        ];

        foreach ($entries as $entry) {
            SalesBotKnowledge::query()->firstOrCreate(
                ['slug' => $entry['slug']],
                [
                    'product'    => 'vetsaas',
                    'section'    => 'novedad',
                    'title'      => $entry['title'],
                    'sort_order' => $entry['sort_order'],
                    'is_active'  => true,
                    'meta'       => null,
                    'content'    => $entry['content'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        SalesBotKnowledge::flushCache('vetsaas');
    }

    public function down(): void
    {
        if (! Schema::hasTable('salesbot_knowledge')) {
            return;
        }

        SalesBotKnowledge::query()
            ->whereIn('slug', [
                'novedad-pwa-app-celular',
                'novedad-facturacion-desde-caja',
            ])
            ->delete();

        SalesBotKnowledge::flushCache('vetsaas');
    }
};
