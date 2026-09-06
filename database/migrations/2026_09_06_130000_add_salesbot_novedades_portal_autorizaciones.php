<?php

declare(strict_types=1);

use App\Models\SalesBotKnowledge;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Novedades de producto: portal del titular, autorizaciones, referidos,
 * chat interno y cuadre de caja con billeteras.
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
                'slug'       => 'novedad-portal-propietario',
                'title'      => 'Portal del propietario: el dueño ve a su mascota con un PIN',
                'sort_order' => 4,
                'content'    => <<<'TXT'
Gancho para reactivación: Ahora el titular entra a un portal propio (con PIN de 4 dígitos) y ve la ficha de su mascota: citas, vacunas, historias clínicas, grooming con fotos de antes/después. Se instala como app y puede recibir avisos.
Ideal para leads que dijeron "el cliente siempre pregunta por WhatsApp cómo está", "quieren ver el historial" o "mandamos fotos del baño a mano".

Qué es: un acceso para el dueño, no para el personal de la clínica. La clínica envía o reenvía el acceso desde la ficha del titular. El dueño no ve caja ni configuración.

Qué ve el dueño:
- Próxima cita y vacunas.
- Historial clínico de la mascota.
- Grooming / baños con fotos Antes y Después.
- Filtro por mes.
- Instalar en el celular (PWA) y notificaciones.

Mensaje corto sugerido: "Oye, ahora el dueño entra con un PIN y ve a su mascota: citas, vacunas, HC y fotos del grooming. Dejas de reenviar WhatsApps a cada rato. ¿Tus clientes te piden mucho el historial?"

Cuando deje de ser novedad, desactivar esta entrada.
TXT,
            ],
            [
                'slug'       => 'novedad-documentos-autorizacion',
                'title'      => 'Autorizaciones y consentimientos firmados desde el celular',
                'sort_order' => 5,
                'content'    => <<<'TXT'
Gancho para reactivación: Ya no imprimes el consentimiento de cirugía, anestesia o internamiento en papel. Armas plantillas (incluso subiendo un PDF o foto y la IA las convierte), las envías al titular desde la consulta y quedan en el historial.
Ideal para leads que mencionaron "consentimiento informado", "autorización de cirugía", "el dueño no está en la clínica" o "firmamos en papel y se pierde".

Cómo funciona:
1. Creas plantillas (cirugía, anestesia, eutanasia, internamiento, etc.) con el logo de la clínica.
2. Desde la consulta o el paciente, envías el documento al titular.
3. El dueño lo lee y autoriza; queda registro en la ficha.

Mensaje corto sugerido: "Una cosa nueva: los consentimientos salen por plantilla, se los mandas al dueño y quedan guardados en la HC. Adiós al papel firmado que se pierde. ¿Hoy cómo autorizan las cirugías?"

Cuando deje de ser novedad, desactivar esta entrada.
TXT,
            ],
            [
                'slug'       => 'novedad-programa-referidos',
                'title'      => 'Programa de referidos: invitas clínicas y ganas días gratis',
                'sort_order' => 6,
                'content'    => <<<'TXT'
Gancho para reactivación: Cada clínica VetSaaS tiene un código y un enlace para invitar a colegas. Cuando la clínica invitada paga su primer mes, quien invitó suma días gratis en la próxima renovación (según el plan que elijan).
Ideal para leads que preguntaron "hay descuento si traigo a otra clínica", "trabajo con colegas" o clientes actuales a reactivar.

Flujo: compartes código o enlace → se registran con tu código → pagan → se acreditan días en tu bolsa → se aplican en el siguiente cobro.
Se comparte por WhatsApp en un toque desde Configuración → Referidos.

Mensaje corto sugerido: "Si conoces a otro veterinario, con tu código de referidos ganas días gratis cuando ellos pagan. ¿Tienes alguna clínica amiga a la que se lo recomendarías?"

Cuando deje de ser novedad, desactivar esta entrada.
TXT,
            ],
            [
                'slug'       => 'novedad-chat-interno',
                'title'      => 'Chat interno del equipo: avisas a caja y al doctor sin WhatsApp personal',
                'sort_order' => 7,
                'content'    => <<<'TXT'
Gancho para reactivación: El equipo de la clínica ya tiene chat propio dentro de VetSaaS: mensajes directos, grupos, fotos, PDF, menciones, visto y notificaciones. No se mezcla con el WhatsApp del celular personal.
Ideal para leads que dijeron "nos escribimos por WhatsApp del celular", "caja no se entera del cobro" o "el doctor está en el otro piso".

Qué incluye:
- Chat 1 a 1 y grupos del tenant (solo usuarios de esa clínica).
- Adjuntar fotos y documentos.
- Responder, mencionar, silenciar, recibo de visto.
- Avisos en el navegador / app.

Mensaje corto sugerido: "Ahora recepción le escribe a caja o al vet desde el sistema, con grupos y archivos, sin usar el WhatsApp personal. ¿Hoy cómo se avisan entre ustedes?"

Cuando deje de ser novedad, desactivar esta entrada.
TXT,
            ],
            [
                'slug'       => 'novedad-caja-billeteras',
                'title'      => 'Caja con Yape, Plin y transferencia: abres y cierras cada canal',
                'sort_order' => 8,
                'content'    => <<<'TXT'
Gancho para reactivación: El cierre de caja ya no es solo efectivo. Al abrir y al cerrar indicas el saldo de Yape, Plin y transferencia (además del efectivo). Al arqueo ves lo esperado vs lo contado en cada medio.
Ideal para leads que cobran mucho por Yape/Plin y decían "el efectivo cuadra pero las billeteras no las controlo".

Cómo funciona:
- Apertura: efectivo inicial + saldo de cada billetera (0 si no las usas).
- Durante el turno: las ventas se registran por método (efectivo, Yape, Plin, tarjeta, transferencia).
- Cierre: cuentas cada canal. Lo esperado de una billetera es su apertura + lo cobrado en ese medio. Los egresos siguen saliendo solo del efectivo.
- Tarjeta (POS) se reporta en métodos de pago; no se "cuenta" como billetera.

Mensaje corto sugerido: "Al cerrar caja ahora cuadran Yape, Plin y transferencia aparte del efectivo. ¿En tu clínica entra más por Yape que en billetes?"

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
                'novedad-portal-propietario',
                'novedad-documentos-autorizacion',
                'novedad-programa-referidos',
                'novedad-chat-interno',
                'novedad-caja-billeteras',
            ])
            ->delete();

        SalesBotKnowledge::flushCache('vetsaas');
    }
};
