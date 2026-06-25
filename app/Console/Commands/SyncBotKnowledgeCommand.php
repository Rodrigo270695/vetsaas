<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\SalesBotKnowledge;
use Illuminate\Console\Command;

/**
 * Auto-sincroniza la base de conocimiento del bot desde la BD real.
 *
 * Corre automáticamente cada noche (ver bootstrap/app.php).
 * También se puede correr a mano:
 *
 *   php artisan vetsaas:sync-bot-knowledge
 *
 * Qué hace:
 *   1. Lee los planes activos con sus features reales desde `plan_features`.
 *   2. Genera/actualiza entradas en `salesbot_knowledge` para:
 *      - Módulos del sistema (sección: modulo) — si no existen ya, los crea.
 *      - FAQs de precio (sección: faq) — genera desde datos reales de planes.
 *   3. NO sobreescribe entradas que Rodrigo haya editado manualmente
 *      (detectado por `meta->auto_generated = false`).
 *   4. Limpia el caché del bot después de sincronizar.
 *
 * Qué NO hace:
 *   - No toca entradas con `meta->auto_generated = false` (manuales).
 *   - No elimina entradas existentes.
 */
final class SyncBotKnowledgeCommand extends Command
{
    protected $signature   = 'vetsaas:sync-bot-knowledge {--force : Sobreescribir incluso entradas manuales}';
    protected $description = 'Auto-sincroniza la base de conocimiento del bot desde la BD (planes, módulos, FAQs)';

    private const PRODUCT = 'vetsaas';

    /**
     * Módulos de VetSaaS con descripción para ventas.
     * Cuando se agregue un módulo nuevo al sistema, agregar aquí.
     */
    private const MODULES = [
        [
            'slug'  => 'historial-clinico',
            'title' => 'Historial clínico digital',
            'content' => <<<'TXT'
Cada paciente tiene su propio historial completo: consultas, diagnósticos, tratamientos, vacunas y recetas. Todo en una sola pantalla, accesible desde cualquier dispositivo.
Dolor que resuelve: "pierdo tiempo buscando el historial cuando llega un paciente de emergencia".
TXT,
            'order' => 1,
        ],
        [
            'slug'  => 'citas',
            'title' => 'Agenda de citas inteligente',
            'content' => <<<'TXT'
Agendamiento de citas con recordatorios automáticos por WhatsApp. El propietario recibe confirmación y recordatorio sin que el personal haga nada.
Dolor que resuelve: "los clientes se olvidan de sus citas y perdemos tiempo con no-shows".
TXT,
            'order' => 2,
        ],
        [
            'slug'  => 'caja-ventas',
            'title' => 'Caja y control de ventas',
            'content' => <<<'TXT'
Control de caja diaria, ventas de productos y servicios, múltiples métodos de pago (efectivo, tarjeta, Yape, Plin). Cierre de caja automático con reporte del día.
Dolor que resuelve: "no sé exactamente cuánto gané hoy ni qué productos se vendieron".
TXT,
            'order' => 3,
        ],
        [
            'slug'  => 'facturacion-electronica',
            'title' => 'Facturación electrónica SUNAT',
            'content' => <<<'TXT'
Emisión de boletas y facturas electrónicas integradas con SUNAT. Disponible en planes Starter y superiores. En el plan Clínica, guías de remisión, notas de crédito y débito también incluidas.
Dolor que resuelve: "me toma media hora al día generar comprobantes y enviárselos a la SUNAT".
TXT,
            'order' => 4,
        ],
        [
            'slug'  => 'inventario-stock',
            'title' => 'Inventario y control de stock',
            'content' => <<<'TXT'
Control de productos veterinarios, medicamentos y accesorios. Alertas automáticas cuando un producto llega al stock mínimo. Movimientos de entrada y salida registrados.
Dolor que resuelve: "me quedo sin medicamentos porque no controlo el stock y el proveedor demora".
TXT,
            'order' => 5,
        ],
        [
            'slug'  => 'grooming',
            'title' => 'Módulo de Grooming / Peluquería canina',
            'content' => <<<'TXT'
Gestión completa del servicio de peluquería: agendamiento, registro de servicios por mascota, historial de cortes y tratamientos estéticos. Se integra con la agenda general.
Dolor que resuelve: "anoto los grooming en un papel aparte y a veces se pierde".
TXT,
            'order' => 6,
        ],
        [
            'slug'  => 'hospitalizacion',
            'title' => 'Hospitalización',
            'content' => <<<'TXT'
Control de pacientes hospitalizados: asignación de jaulas, seguimiento de evolución, medicación y alta médica. El propietario puede recibir actualizaciones.
Dolor que resuelve: "cuando tengo pacientes hospitalizados no tengo un control ordenado de su evolución".
TXT,
            'order' => 7,
        ],
        [
            'slug'  => 'laboratorio',
            'title' => 'Laboratorio clínico',
            'content' => <<<'TXT'
Registro de resultados de laboratorio directamente en el historial clínico del paciente. Vinculado a la consulta. Sin papeles ni resultados perdidos.
Dolor que resuelve: "los resultados de laboratorio los anoto en papel y se pierden o no sé a qué consulta corresponden".
TXT,
            'order' => 8,
        ],
        [
            'slug'  => 'cirugia',
            'title' => 'Registro de cirugías',
            'content' => <<<'TXT'
Registro completo del procedimiento quirúrgico: protocolo anestésico, técnica, hallazgos, materiales usados y evolución post-operatoria. Todo vinculado al historial.
Dolor que resuelve: "no tengo un registro ordenado de las cirugías que hago".
TXT,
            'order' => 9,
        ],
        [
            'slug'  => 'whatsapp-automatico',
            'title' => 'WhatsApp automático',
            'content' => <<<'TXT'
Recordatorios automáticos de citas, vacunas vencidas y cumpleaños de mascotas enviados directamente por WhatsApp al propietario. Sin intervención manual.
Dolor que resuelve: "paso horas llamando para recordar citas o avisar que la vacuna está vencida".
TXT,
            'order' => 10,
        ],
        [
            'slug'  => 'multi-sede',
            'title' => 'Multi-sede (plan Clínica)',
            'content' => <<<'TXT'
Con el plan Clínica puedes gestionar hasta 3 sedes desde una sola cuenta. Cada sede tiene su propia caja, agenda y stock pero comparten el historial de pacientes.
Dolor que resuelve: "tengo 2 locales y es un caos coordinar la información entre ellos".
TXT,
            'order' => 11,
        ],
    ];

    /**
     * Objeciones estándar y cómo manejarlas.
     */
    private const OBJECTIONS = [
        [
            'slug'  => 'objecion-precio',
            'title' => 'Objeción: "Es caro" o "No tengo presupuesto"',
            'content' => <<<'TXT'
Respuesta recomendada: "Entiendo. ¿Cuánto te cuesta al mes el tiempo que pierdes buscando historiales, llamando para recordar citas o calculando la caja a mano? El plan Starter son S/39.90 — menos que una consulta. Y puedes empezar con el plan Free hoy mismo sin pagar nada."
Cierre: siempre ofrecer el plan Free como entrada sin riesgo.
TXT,
            'order' => 1,
        ],
        [
            'slug'  => 'objecion-tiempo',
            'title' => 'Objeción: "No tengo tiempo para aprender un sistema nuevo"',
            'content' => <<<'TXT'
Respuesta recomendada: "Lo configuramos en 15 minutos juntos por videollamada. Y si en la primera semana sientes que no te sirve, no pagaste nada con el plan Free. ¿Cuándo tienes 15 minutos esta semana?"
TXT,
            'order' => 2,
        ],
        [
            'slug'  => 'objecion-sistema-actual',
            'title' => 'Objeción: "Ya tengo un sistema / uso Excel"',
            'content' => <<<'TXT'
Respuesta recomendada: "Perfecto, eso me ayuda a entenderte mejor. ¿Qué es lo que más te frustra de tu sistema actual o de Excel? Porque VetSaaS tiene algo que la mayoría de sistemas no tiene: [mencionar la feature que resuelve exactamente esa frustración]."
TXT,
            'order' => 3,
        ],
        [
            'slug'  => 'objecion-lo-pienso',
            'title' => 'Objeción: "Lo voy a pensar" o "Déjame consultarlo"',
            'content' => <<<'TXT'
Respuesta recomendada: "Claro, con toda la razón. Para que tengas algo concreto en qué pensar, ¿te muestro el sistema funcionando en 10 minutos ahora mismo? Así decides con información real, no con suposiciones."
TXT,
            'order' => 4,
        ],
    ];

    public function handle(): int
    {
        $this->info('── Sincronizando base de conocimiento del bot ─────────────────');
        $force = (bool) $this->option('force');

        $created  = 0;
        $updated  = 0;
        $skipped  = 0;

        // ── 1. Módulos ────────────────────────────────────────────────────
        $this->line('  → Sincronizando módulos…');
        foreach (self::MODULES as $module) {
            [$c, $u, $s] = $this->upsertEntry('modulo', $module, $force);
            $created += $c; $updated += $u; $skipped += $s;
        }

        // ── 2. Objeciones ─────────────────────────────────────────────────
        $this->line('  → Sincronizando objeciones…');
        foreach (self::OBJECTIONS as $obj) {
            [$c, $u, $s] = $this->upsertEntry('objecion', $obj, $force);
            $created += $c; $updated += $u; $skipped += $s;
        }

        // ── 3. FAQs de precios auto-generadas desde planes ────────────────
        $this->line('  → Generando FAQs de precios desde planes activos…');
        [$c, $u, $s] = $this->syncPlanFaqs($force);
        $created += $c; $updated += $u; $skipped += $s;

        // ── 4. Limpiar caché ──────────────────────────────────────────────
        SalesBotKnowledge::flushCache(self::PRODUCT);
        $this->line('  → Caché del bot limpiada.');

        $this->info("✓ Sincronización completa — creados: {$created} | actualizados: {$updated} | sin cambios: {$skipped}");

        return self::SUCCESS;
    }

    /**
     * Crea o actualiza una entrada de conocimiento.
     * Si ya existe y fue editada manualmente (meta.auto_generated = false),
     * la deja intacta a menos que se use --force.
     *
     * @return array{int,int,int} [creados, actualizados, omitidos]
     */
    private function upsertEntry(string $section, array $data, bool $force): array
    {
        $existing = SalesBotKnowledge::query()
            ->where('product', self::PRODUCT)
            ->where('section', $section)
            ->where('slug', $data['slug'])
            ->first();

        if ($existing !== null) {
            $meta = $existing->meta ?? [];
            $isManual = isset($meta['auto_generated']) && $meta['auto_generated'] === false;

            if ($isManual && ! $force) {
                return [0, 0, 1];
            }

            $existing->update([
                'title'      => $data['title'],
                'content'    => trim($data['content']),
                'sort_order' => $data['order'],
                'is_active'  => true,
                'meta'       => array_merge($meta, ['auto_generated' => true, 'synced_at' => now()->toIso8601String()]),
            ]);

            return [0, 1, 0];
        }

        // Calcular sort_order si no existe ninguno para este producto/sección.
        $maxOrder = SalesBotKnowledge::query()
            ->where('product', self::PRODUCT)
            ->where('section', $section)
            ->max('sort_order') ?? 0;

        SalesBotKnowledge::create([
            'product'    => self::PRODUCT,
            'section'    => $section,
            'slug'       => $data['slug'],
            'title'      => $data['title'],
            'content'    => trim($data['content']),
            'sort_order' => max((int) $maxOrder + 1, $data['order']),
            'is_active'  => true,
            'meta'       => ['auto_generated' => true, 'synced_at' => now()->toIso8601String()],
        ]);

        return [1, 0, 0];
    }

    /**
     * Genera una FAQ por cada plan activo con sus datos reales.
     *
     * @return array{int,int,int}
     */
    private function syncPlanFaqs(bool $force): array
    {
        $created = $updated = $skipped = 0;

        $plans = Plan::query()
            ->with('features')
            ->where('activo', true)
            ->where('es_publico', true)
            ->orderBy('orden')
            ->get();

        foreach ($plans as $plan) {
            $mensual = 'S/'.number_format((float) $plan->precio_mensual, 2).'/mes';

            // Construir resumen de features legible.
            $limites = [];
            foreach (['max_sedes' => 'sede(s)', 'max_usuarios' => 'usuario(s)', 'max_pacientes' => 'pacientes'] as $feat => $label) {
                $val = $plan->resolveFeature($feat);
                if ($val !== null) {
                    $limites[] = $val === -1 ? "{$label} ilimitados" : "{$val} {$label}";
                }
            }

            $felItems = [];
            if ($plan->resolveFeature('boletas_electronicas'))  { $felItems[] = 'boletas'; }
            if ($plan->resolveFeature('facturas_electronicas'))  { $felItems[] = 'facturas'; }
            if ($plan->resolveFeature('guias_remision'))         { $felItems[] = 'guías de remisión'; }
            $maxCpe = $plan->resolveFeature('max_comprobantes_mes');
            $felStr = empty($felItems)
                ? 'Sin facturación electrónica'
                : 'Facturación: '.implode(', ', $felItems).($maxCpe > 0 ? " (hasta {$maxCpe}/mes)" : ' ilimitada');

            $content = "Precio: {$mensual}\n"
                .(!empty($limites) ? 'Incluye: '.implode(', ', $limites)."\n" : '')
                ."{$felStr}\n";

            if ($plan->descripcion) {
                $content .= $plan->descripcion."\n";
            }

            [$c, $u, $s] = $this->upsertEntry('faq', [
                'slug'    => 'precio-plan-'.$plan->codigo,
                'title'   => "¿Cuánto cuesta el plan {$plan->nombre}?",
                'content' => trim($content),
                'order'   => $plan->orden ?? 10,
            ], $force);

            $created += $c; $updated += $u; $skipped += $s;
        }

        return [$created, $updated, $skipped];
    }
}
