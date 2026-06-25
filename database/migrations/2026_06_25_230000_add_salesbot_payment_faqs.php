<?php

declare(strict_types=1);

use App\Models\SalesBotKnowledge;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * FAQs y objeción de cierre/pago para el bot de ventas.
 * Usa firstOrCreate: no sobrescribe entradas ya creadas en el panel.
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

        foreach ($entries as $entry) {
            SalesBotKnowledge::query()->firstOrCreate(
                ['slug' => $entry['slug']],
                array_merge($entry, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
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
                'objecion-cierre',
                'faq-como-pagar',
                'faq-plan-anual-descuento',
                'faq-metodos-pago',
                'faq-pasos-seleccion-plan',
                'faq-agendar-ayuda-pago',
                'faq-despues-del-pago',
            ])
            ->delete();

        SalesBotKnowledge::flushCache('vetsaas');
    }
};
