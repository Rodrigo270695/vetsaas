<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BotIaAnnouncement;
use Illuminate\Database\Seeder;

/**
 * Novedad promocional para tenants sin Asistente IA.
 *
 * Ejecutar:
 *   php artisan db:seed --class=BotIaAnnouncementSeeder
 */
final class BotIaAnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        BotIaAnnouncement::query()->updateOrCreate(
            ['title' => 'Tu recepción virtual en WhatsApp, 24/7'],
            [
                'badge' => BotIaAnnouncement::BADGE_NUEVO,
                'bullet_1' => 'Responde al instante horarios, precios y FAQs con la voz de tu clínica — sin saturar a tu equipo.',
                'bullet_2' => 'Registra tutores y mascotas nuevos desde el chat y deja todo listo en VetSaaS automáticamente.',
                'bullet_3' => 'Agenda citas y turnos confirmando fecha y hora con el cliente, mientras tú te enfocas en la consulta.',
                'guide_title' => 'Actívalo en minutos',
                'guide_body' => 'Por solo S/. 15 adicionales a tu plan mensual, tu clínica tendrá un asistente que nunca duerme. Usa el mismo WhatsApp que ya tienes en VetSaaS.',
                'guide_tip_1' => 'Entra a Configuración → Mi suscripción y activa el add-on Asistente IA.',
                'guide_tip_2' => 'Sincroniza WhatsApp en Comunicaciones → Cola saliente.',
                'guide_tip_3' => 'Completa horarios, servicios y FAQs para respuestas más precisas desde el día uno.',
                'is_active' => true,
                'published_at' => now(),
                'expires_at' => null,
            ],
        );

        $active = BotIaAnnouncement::query()
            ->where('title', 'Tu recepción virtual en WhatsApp, 24/7')
            ->first();

        if ($active !== null) {
            BotIaAnnouncement::query()
                ->where('id', '!=', $active->id)
                ->update(['is_active' => false]);
        }

        BotIaAnnouncement::flushCache();
    }
}
