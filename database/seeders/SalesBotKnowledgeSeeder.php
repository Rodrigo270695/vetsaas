<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SalesBotKnowledge;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Carga la base de conocimiento completa de VetSaaS en la tabla salesbot_knowledge.
 *
 * Ejecutar:
 *   php artisan db:seed --class=SalesBotKnowledgeSeeder
 *
 * Es seguro ejecutarlo múltiples veces (usa upsert por slug).
 * Después de ejecutarlo, el caché se invalida automáticamente.
 *
 * Para actualizar solo un dato puntual sin correr el seeder completo:
 *   php artisan tinker
 *   >>> $k = \App\Models\SalesBotKnowledge::where('slug','plan-pro')->first();
 *   >>> $k->content = "...nuevo texto...";
 *   >>> $k->save();
 *   >>> \App\Models\SalesBotKnowledge::flushCache('vetsaas');
 */
final class SalesBotKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $entries = array_merge(
            $this->planes(),
            $this->modulos(),
            $this->objeciones(),
            $this->faqs(),
        );

        foreach ($entries as $entry) {
            SalesBotKnowledge::updateOrInsert(
                ['slug' => $entry['slug']],
                array_merge($entry, ['updated_at' => now(), 'created_at' => now()]),
            );
        }

        // Invalidar caché para que el bot use los datos frescos en el próximo mensaje.
        Cache::forget('salesbot_knowledge_vetsaas');

        $this->command->info('✓ SalesBotKnowledge: '.count($entries).' entradas cargadas para VetSaaS.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PLANES
    // ─────────────────────────────────────────────────────────────────────────

    private function planes(): array
    {
        return [
            [
                'product'    => 'vetsaas',
                'section'    => 'plan',
                'slug'       => 'plan-free',
                'title'      => 'Plan Free — S/0 (gratis para siempre)',
                'sort_order' => 1,
                'is_active'  => true,
                'meta'       => json_encode(['price' => 0, 'sedes' => 1, 'users' => 1, 'patients' => 50]),
                'content'    => <<<'TXT'
Precio: S/0 — sin costo, sin tarjeta, sin límite de tiempo.
Ideal para: veterinarios que quieren probar el sistema antes de pagar.
Incluye:
- 1 sede, 1 usuario
- Hasta 50 pacientes activos
- Historial clínico básico
- Registro de citas
- Acceso al demo en vivo: demo.orvae.pe (usuario: demo@vetsaas.pe / clave: demo1234)
No incluye: facturación electrónica, reportes avanzados ni módulos de hospitalización/cirugía.
Mensaje de venta: "Empieza gratis hoy mismo, sin riesgo. Si en un mes ves que te sirve, recién te decides por el plan pago."
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'plan',
                'slug'       => 'plan-starter',
                'title'      => 'Plan Starter — S/39.90/mes',
                'sort_order' => 2,
                'is_active'  => true,
                'meta'       => json_encode(['price' => 39.90, 'sedes' => 1, 'users' => 2, 'patients' => 150]),
                'content'    => <<<'TXT'
Precio: S/39.90 al mes.
Ideal para: clínicas pequeñas con un veterinario y una recepcionista.
Incluye todo lo del plan Free más:
- 1 sede, 2 usuarios
- Hasta 150 pacientes activos
- Historial clínico completo con subida de fotos/archivos
- Módulo de citas con recordatorios por WhatsApp
- Caja y ventas: registro de ingresos, productos y servicios
- Stock básico: control de medicamentos e insumos
- Reportes de atenciones y ventas del mes
No incluye: facturación electrónica (SUNAT).
Mensaje de venta: "Por menos de S/1.50 al día tienes todo el control de tu clínica desde el celular."
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'plan',
                'slug'       => 'plan-pro',
                'title'      => 'Plan Pro — S/59.90/mes (el más popular)',
                'sort_order' => 3,
                'is_active'  => true,
                'meta'       => json_encode(['price' => 59.90, 'sedes' => 1, 'users' => 3, 'patients' => 300]),
                'content'    => <<<'TXT'
Precio: S/59.90 al mes.
Ideal para: clínicas que emiten facturas/boletas y tienen 2-3 veterinarios.
Incluye todo lo del plan Starter más:
- 1 sede, 3 usuarios
- Hasta 300 pacientes activos
- Facturación electrónica (boletas y facturas SUNAT)
- Módulo de grooming completo
- Módulo de laboratorio: registro de análisis y resultados
- Módulo de hospitalización: fichas de internamiento y control diario
- Módulo de cirugías: protocolo quirúrgico completo
- WhatsApp automático: recordatorios de citas y resultados
- Reportes completos con gráficos
Mensaje de venta: "Es el plan que usan la mayoría de nuestros clientes. Con la facturación electrónica ya te paga solo."
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'plan',
                'slug'       => 'plan-clinica',
                'title'      => 'Plan Clínica — S/99.90/mes',
                'sort_order' => 4,
                'is_active'  => true,
                'meta'       => json_encode(['price' => 99.90, 'sedes' => 3, 'users' => 10, 'patients' => -1]),
                'content'    => <<<'TXT'
Precio: S/99.90 al mes.
Ideal para: cadenas veterinarias o clínicas grandes con múltiples sedes.
Incluye todo lo del plan Pro más:
- Hasta 3 sedes independientes desde un solo panel
- Hasta 10 usuarios con roles diferenciados (admin, veterinario, recepcionista)
- Pacientes ilimitados
- Panel centralizado: ves todas las sedes en un solo dashboard
- Soporte prioritario por WhatsApp con tiempo de respuesta de 2 horas
Mensaje de venta: "Si tienes más de una sede o planeas expandirte, este plan te sale más barato que contratar un sistema por sede."
TXT,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MÓDULOS
    // ─────────────────────────────────────────────────────────────────────────

    private function modulos(): array
    {
        return [
            [
                'product'    => 'vetsaas',
                'section'    => 'modulo',
                'slug'       => 'modulo-historial-clinico',
                'title'      => 'Historial Clínico',
                'sort_order' => 1,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
Qué hace: registra toda la historia médica del paciente en un solo lugar.
Cómo funciona:
- Ficha del paciente: nombre, especie, raza, fecha de nacimiento, foto, propietario.
- Cada consulta queda registrada con: fecha, motivo, diagnóstico, tratamiento, medicamentos y dosis.
- Puedes subir fotos, radiografías, documentos adjuntos.
- El historial queda disponible desde cualquier dispositivo con internet.
- Búsqueda rápida por nombre del paciente o del dueño.
Dolor que resuelve: "Ya no dependo de un cuaderno ni de buscar entre hojas sueltas. Todo está en la pantalla."
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'modulo',
                'slug'       => 'modulo-citas',
                'title'      => 'Gestión de Citas',
                'sort_order' => 2,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
Qué hace: organiza la agenda de la clínica y reduce las ausencias.
Cómo funciona:
- Calendario visual por día, semana o mes.
- Registro de citas con: paciente, dueño, veterinario, tipo de servicio, hora.
- Recordatorio automático al dueño por WhatsApp 24h antes de la cita.
- Confirmación de asistencia: el dueño responde "SÍ" o "NO" por WhatsApp.
- Si cancela, la hora queda libre automáticamente para otra cita.
- Historial de citas por paciente.
Dolor que resuelve: "Antes me olvidaba de llamar para confirmar. Ahora el sistema llama solo y la clínica se llena."
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'modulo',
                'slug'       => 'modulo-caja-ventas',
                'title'      => 'Caja y Ventas',
                'sort_order' => 3,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
Qué hace: controla todos los ingresos de la clínica en tiempo real.
Cómo funciona:
- Registro de cada venta: servicio prestado, productos vendidos, precio, descuento.
- Catálogo de servicios y productos con precios configurables.
- Apertura y cierre de caja diario con resumen automático.
- Métodos de pago: efectivo, tarjeta, transferencia, Yape/Plin.
- Facturación electrónica (boletas y facturas SUNAT) en planes Pro y Clínica.
- Reporte diario, semanal y mensual de ingresos.
- Historial de ventas por cliente.
Dolor que resuelve: "Antes no sabía cuánto gané en el mes. Ahora abro el reporte y lo veo al instante."
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'modulo',
                'slug'       => 'modulo-grooming',
                'title'      => 'Grooming (Peluquería Canina)',
                'sort_order' => 4,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
Qué hace: gestiona el servicio de grooming como un negocio separado dentro de la clínica.
Cómo funciona:
- Ficha de grooming por mascota: tipo de corte preferido, notas especiales (reactivo, sensible), historial de visitas.
- Agenda de grooming independiente del área médica.
- Registro del servicio: tipo de baño/corte, productos usados, precio cobrado.
- Fotos antes y después del servicio (opcional).
- Recordatorio automático al dueño cuando la mascota está lista para recoger.
- El ingreso del grooming se registra automáticamente en caja.
Dolor que resuelve: "Antes mezclaba el grooming con las consultas y perdía el control. Ahora tengo agendas separadas y sé cuánto me da cada área."
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'modulo',
                'slug'       => 'modulo-hospitalizacion',
                'title'      => 'Hospitalización',
                'sort_order' => 5,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
Qué hace: controla los pacientes internados con seguimiento diario.
Cómo funciona:
- Registro de ingreso al internamiento: diagnóstico de ingreso, veterinario responsable, jaula asignada.
- Hoja de evolución diaria: temperatura, peso, signos vitales, tratamientos aplicados.
- Control de medicamentos administrados (hora, dosis, quien lo aplicó).
- Notificación automática al dueño con la evolución del día por WhatsApp.
- Registro de alta con informe final.
- Todo queda en el historial clínico del paciente.
Dolor que resuelve: "Antes escribía en papel y el dueño llamaba 5 veces al día para saber cómo estaba su mascota. Ahora le llega el reporte solo."
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'modulo',
                'slug'       => 'modulo-cirugia',
                'title'      => 'Cirugías',
                'sort_order' => 6,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
Qué hace: documenta el protocolo quirúrgico completo para cada cirugía.
Cómo funciona:
- Registro pre-quirúrgico: ayuno, exámenes pre-anestésicos, consentimiento informado firmado.
- Protocolo anestésico: fármaco, dosis, vía, tiempo de inducción.
- Registro intraoperatorio: tiempo quirúrgico, hallazgos, técnica usada.
- Seguimiento post-operatorio: indicaciones de alta, medicación en casa, citas de control.
- Todo queda en el historial del paciente.
- El cargo de la cirugía se genera automáticamente en caja.
Dolor que resuelve: "Tener el protocolo documentado me protege legalmente si el dueño cuestiona algo después."
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'modulo',
                'slug'       => 'modulo-laboratorio',
                'title'      => 'Laboratorio',
                'sort_order' => 7,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
Qué hace: registra y entrega resultados de análisis clínicos digitalmente.
Cómo funciona:
- Solicitud de análisis desde la consulta: hemograma, bioquímica, orina, cultivo, etc.
- Registro de resultados con valores de referencia por especie.
- Los resultados se envían automáticamente al dueño por WhatsApp con un PDF adjunto.
- Historial de laboratorio por paciente para ver la evolución en el tiempo.
- Integración con la historia clínica: el veterinario ve los análisis mientras atiende.
Dolor que resuelve: "Antes el dueño tenía que venir a recoger los resultados. Ahora le llegan por WhatsApp y ya no hay colas."
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'modulo',
                'slug'       => 'modulo-stock',
                'title'      => 'Stock e Inventario',
                'sort_order' => 8,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
Qué hace: controla el inventario de medicamentos, vacunas e insumos.
Cómo funciona:
- Catálogo de productos con precio de compra y precio de venta.
- Cada vez que se vende o usa un producto en consulta, el stock se descuenta automáticamente.
- Alertas de stock mínimo: cuando queda poco de un producto, te avisa.
- Registro de entradas (compras) con proveedor, fecha y precio.
- Lotes y fechas de vencimiento para medicamentos.
- Reporte de productos más usados y margen de ganancia por producto.
Dolor que resuelve: "Antes me quedaba sin vacunas en plena temporada porque no llevaba el control. Ahora el sistema me avisa antes."
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'modulo',
                'slug'       => 'modulo-whatsapp',
                'title'      => 'WhatsApp Automático',
                'sort_order' => 9,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
Qué hace: automatiza la comunicación con los dueños de mascotas por WhatsApp.
Cómo funciona:
- Recordatorios de citas: mensaje automático 24h antes con nombre de la mascota y hora.
- Confirmación de cita: el dueño responde SÍ o NO directamente.
- Aviso de resultados de laboratorio: PDF con los resultados enviado automáticamente.
- Aviso de evolución en hospitalización: resumen diario del estado del paciente.
- Aviso de que la mascota está lista en grooming.
- Recordatorio de vacunas pendientes (mensual).
Todo se envía desde el número de WhatsApp de la clínica, no desde un número genérico.
Dolor que resuelve: "Antes pagaba una chica solo para hacer llamadas de confirmación. Con esto ese costo desaparece."
TXT,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // OBJECIONES
    // ─────────────────────────────────────────────────────────────────────────

    private function objeciones(): array
    {
        return [
            [
                'product'    => 'vetsaas',
                'section'    => 'objecion',
                'slug'       => 'objecion-precio-caro',
                'title'      => 'Objeción: "Está muy caro" / "Es mucho para mí"',
                'sort_order' => 1,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
Respuesta sugerida:
"Entiendo que S/59.90 puede parecer un gasto más. Pero piénsalo así: si el WhatsApp automático te confirma solo 3 citas que ibas a perder al mes, ya se pagó solo. ¿Cuánto te vale una cita en tu clínica?"
Alternativa: ofrece el plan Free para que lo pruebe sin pagar. "Empieza gratis esta semana y en 30 días me cuentas si te sirvió."
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'objecion',
                'slug'       => 'objecion-ya-tengo-sistema',
                'title'      => 'Objeción: "Ya tengo otro sistema" / "Uso Excel"',
                'sort_order' => 2,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
Respuesta sugerida:
"Qué bien que ya tienes algo. Cuéntame, ¿qué es lo que más te falta o te frustra de ese sistema?"
Escucha la respuesta y conecta exactamente ese dolor con el módulo de VetSaaS que lo resuelve.
Si usa Excel: "Excel no te avisa cuando hay citas mañana, no le escribe a los dueños y no te genera la boleta SUNAT. ¿Eso te está generando problemas?"
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'objecion',
                'slug'       => 'objecion-no-tengo-tiempo',
                'title'      => 'Objeción: "No tengo tiempo para aprender algo nuevo"',
                'sort_order' => 3,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
Respuesta sugerida:
"Es exactamente para eso que existe el demo: entras, lo tocas 10 minutos, y si no lo entiendes en ese tiempo, me avisas y no sigas. La mayoría de nuestros clientes aprendieron solos en menos de una hora."
Ofrecer la credencial demo: demo.orvae.pe / demo@vetsaas.pe / demo1234
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'objecion',
                'slug'       => 'objecion-no-confio',
                'title'      => 'Objeción: "¿Y si pierdo mis datos?" / "No confío en la nube"',
                'sort_order' => 4,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
Respuesta sugerida:
"Tus datos se guardan en servidores con respaldo automático diario. Es más seguro que un cuaderno que se puede perder o mojar. Y si mañana se te rompe la computadora, entras desde el celular y todo está ahí."
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'objecion',
                'slug'       => 'objecion-pocos-pacientes',
                'title'      => 'Objeción: "Tengo pocos pacientes, no necesito un sistema"',
                'sort_order' => 5,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
Respuesta sugerida:
"Precisamente porque eres pequeño es el mejor momento para ordenarte. Los veterinarios que empiezan con sistema crecen más rápido porque no pierden pacientes ni citas. ¿Cuántos pacientes tienes ahora? Con el plan Free entras gratis y sin límite de tiempo."
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'objecion',
                'slug'       => 'objecion-cierre',
                'title'      => 'Objeción: "Déjame pensarlo" cuando ya vio el plan',
                'sort_order' => 6,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
Respuesta cuando el prospecto ya vio el demo y el plan pero no cierra:
"Claro, piénsalo tranquilo. Para que decidas con información real: el plan anual del Pro sale S/599 y te regalan 2 meses. O si prefieres ir sin riesgo, empieza con el Free gratis y en un mes me cuentas.
¿Qué te falta para decidir — el precio, ver algo más del sistema, o ayuda con el pago?"
Si dice que le falta ayuda con el pago → ofrecer pasarlo con el administrador.
Si dice precio → reforzar ROI (WhatsApp automático, facturación, tiempo ahorrado).
Si dice tiempo → ofrecer videollamada de 10 min.
TXT,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FAQS
    // ─────────────────────────────────────────────────────────────────────────

    private function faqs(): array
    {
        return [
            [
                'product'    => 'vetsaas',
                'section'    => 'faq',
                'slug'       => 'faq-comprobantes',
                'title'      => '¿Cuántos comprobantes puedo emitir?',
                'sort_order' => 1,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
En los planes Pro y Clínica no hay límite de comprobantes electrónicos (boletas y facturas SUNAT).
Emites todos los que necesites sin costo adicional.
En los planes Free y Starter no incluye facturación electrónica.
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'faq',
                'slug'       => 'faq-migracion',
                'title'      => '¿Puedo migrar mis datos desde otro sistema?',
                'sort_order' => 2,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
Sí. Si tienes tu data en Excel o en otro sistema, el equipo de Orvae te ayuda a importarla sin costo adicional.
Contactar a soporte: soporte@orvae.pe o WhatsApp directo con el equipo.
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'faq',
                'slug'       => 'faq-contrato',
                'title'      => '¿Hay contrato o permanencia mínima?',
                'sort_order' => 3,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
No. El pago es mensual y puedes cancelar cuando quieras.
No hay contrato de permanencia ni penalidades.
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'faq',
                'slug'       => 'faq-soporte',
                'title'      => '¿Qué soporte tienen?',
                'sort_order' => 4,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
- Plan Free y Starter: soporte por correo con respuesta en 24-48 horas.
- Plan Pro: soporte por WhatsApp en horario de oficina (Lun-Vie 9am-6pm).
- Plan Clínica: soporte prioritario con respuesta garantizada en 2 horas.
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'faq',
                'slug'       => 'faq-movil',
                'title'      => '¿Funciona desde el celular?',
                'sort_order' => 5,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
Sí. VetSaaS es 100% web y funciona desde cualquier dispositivo con internet: celular, tablet o computadora.
No necesitas instalar nada. Solo abres el navegador y entras.
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'faq',
                'slug'       => 'faq-como-pagar',
                'title'      => '¿Cómo pago y activo mi plan de VetSaaS?',
                'sort_order' => 6,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
El pago se hace en la web oficial de Orvae, no por WhatsApp ni transferencia directa al bot.

Pasos:
1. Entrar a https://orvae.pe/software/VETSAAS
2. Elegir el plan (Free, Starter, Pro o Clínica) según lo que necesite la clínica.
3. Seleccionar ciclo de pago: mensual o anual (el anual tiene descuento — ver FAQ plan anual).
4. Completar datos de la clínica: nombre, email, teléfono, RUC si aplica.
5. Elegir método de pago: Yape o tarjeta de crédito/débito.
6. Confirmar el pago.

Después del pago exitoso recibe un correo con el acceso a su clínica (subdominio personalizado).
Si el prospecto tiene problemas en algún paso, ofrecer pasarlo con el administrador por WhatsApp para guiarlo en vivo.
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'faq',
                'slug'       => 'faq-plan-anual-descuento',
                'title'      => '¿Cuál es la ventaja del plan anual? ¿Hay descuento?',
                'sort_order' => 7,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
Sí. Los planes de pago tienen opción anual con descuento equivalente a 2 meses gratis.

Ejemplo Plan Pro (el más popular):
- Mensual: S/59.90/mes → 12 meses = S/718.80
- Anual: S/599/año → ahorras S/119.80 (como si te regalaran 2 meses)

Mensaje de venta sugerido:
"Si ya estás convencido del sistema, el plan anual te sale más barato: pagas 10 meses y usas 12."

Los precios exactos de cada plan los lee el bot desde la base de datos. Solo mencionar el plan anual cuando el prospecto ya mostró interés real en contratar, no al inicio de la conversación.
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'faq',
                'slug'       => 'faq-metodos-pago',
                'title'      => '¿Qué métodos de pago aceptan?',
                'sort_order' => 8,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
En https://orvae.pe/software/VETSAAS se puede pagar con:
- Yape (pago digital rápido desde el celular)
- Tarjeta de crédito
- Tarjeta de débito

No se acepta pago en efectivo ni transferencia bancaria directa por el bot de WhatsApp.

Si el prospecto no puede pagar online o prefiere que alguien le guíe:
"Sin problema, te paso ahora con nuestro administrador y te ayuda a completar el pago paso a paso."
En ese caso ofrecer handoff a humano (pausar bot) y Rodrigo cierra la venta manualmente.
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'faq',
                'slug'       => 'faq-pasos-seleccion-plan',
                'title'      => '¿Qué plan debo elegir en la página de pago?',
                'sort_order' => 9,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
Guía rápida para ayudar al prospecto a elegir en orvae.pe/software/VETSAAS:

- Plan Free: quiere probar con su propia clínica sin pagar nada. Sin tarjeta.
- Plan Starter (S/39.90/mes): clínica pequeña, 1-2 personas, sin facturación SUNAT.
- Plan Pro (S/59.90/mes): el más elegido. Facturación electrónica, grooming, laboratorio, hospitalización, cirugías y WhatsApp automático. Ideal para 2-3 veterinarios.
- Plan Clínica: múltiples sedes, más usuarios, pacientes ilimitados.

Regla del bot: NO listar todos los planes de golpe. Recomendar solo UNO según lo que el prospecto contó.

En la página de pago debe:
1. Hacer clic en el plan recomendado.
2. Elegir "Mensual" o "Anual".
3. Seguir el formulario de registro.
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'faq',
                'slug'       => 'faq-agendar-ayuda-pago',
                'title'      => '¿Puedo agendar una llamada o que me ayuden con el pago?',
                'sort_order' => 10,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
Sí. Hay dos caminos:

OPCIÓN 1 — Pagar solo (rápido, 5 minutos):
Enviar el link https://orvae.pe/software/VETSAAS y los pasos de pago. Ideal si el prospecto ya vio el demo y está decidido.

OPCIÓN 2 — Videollamada o ayuda guiada (10-15 minutos):
Para prospectos que quieren ver el sistema en vivo antes de pagar, o que no se sienten cómodos pagando solos.

Frase sugerida:
"¿Prefieres pagar directo en la web o que te ayude nuestro administrador en una llamada rápida de WhatsApp? Son 10 minutos y te guiamos paso a paso."

Para agendar: proponer 2-3 horarios concretos (ej: "hoy a las 4pm", "mañana 10am") y confirmar por WhatsApp.

Si el prospecto dice "sí, ayúdame con el pago" → handoff inmediato a humano (pausar bot).
TXT,
            ],
            [
                'product'    => 'vetsaas',
                'section'    => 'faq',
                'slug'       => 'faq-despues-del-pago',
                'title'      => '¿Qué pasa después de pagar?',
                'sort_order' => 11,
                'is_active'  => true,
                'meta'       => null,
                'content'    => <<<'TXT'
Tras un pago exitoso en orvae.pe:

1. Se crea automáticamente la clínica del cliente (su propio sistema VetSaaS).
2. Recibe un email con el enlace de acceso (subdominio personalizado).
3. Entra con el email y contraseña que registró en el checkout.
4. Puede empezar a cargar pacientes, propietarios y citas de inmediato.
5. Si necesita migrar datos desde Excel u otro sistema, el equipo de Orvae ayuda sin costo adicional (soporte@orvae.pe).

Tiempo estimado: el acceso llega en minutos después del pago.

Mensaje post-venta sugerido:
"¡Listo! Revisa tu correo — ahí está el acceso a tu clínica. Cualquier duda me escribes por aquí 🐾"
TXT,
            ],
        ];
    }
}
