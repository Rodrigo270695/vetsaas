<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BotIaAnnouncement;
use Illuminate\Database\Seeder;

/**
 * Ejemplo de novedad in-app para tenants con Asistente IA (WhatsApp).
 *
 * Ejecutar:
 *   php artisan db:seed --class=BotIaAnnouncementSeeder
 */
final class BotIaAnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        BotIaAnnouncement::query()->updateOrCreate(
            ['title' => 'Tu Asistente IA ahora trabaja por ti en WhatsApp'],
            [
                'badge' => BotIaAnnouncement::BADGE_NUEVO,
                'bullet_1' => 'Registra clientes y mascotas nuevos sin abrir VetSaaS: el bot lo hace desde el chat de WhatsApp.',
                'bullet_2' => 'Agenda citas y turnos de grooming confirmando fecha, hora y mascota en segundos.',
                'bullet_3' => 'Cada conversación queda en Chats para auditar, pausar la IA o intervenir cuando tu equipo lo necesite.',
                'guide_title' => 'Ponlo a trabajar en 3 pasos',
                'guide_body' => 'Tu clínica ya tiene el add-on activo. Solo asegúrate de que el asistente tenga información real y permisos para actuar.',
                'guide_tip_1' => 'Completa horarios, servicios y FAQs en la pestaña Base de conocimiento.',
                'guide_tip_2' => 'Mantén WhatsApp sincronizado y las respuestas automáticas encendidas.',
                'guide_tip_3' => 'Si respondes manualmente desde el celular de la clínica, la IA se pausa sola en ese chat.',
                'is_active' => true,
                'published_at' => now(),
                'expires_at' => null,
            ],
        );

        // Solo una novedad publicada: desactiva el resto salvo esta.
        $active = BotIaAnnouncement::query()
            ->where('title', 'Tu Asistente IA ahora trabaja por ti en WhatsApp')
            ->first();

        if ($active !== null) {
            BotIaAnnouncement::query()
                ->where('id', '!=', $active->id)
                ->update(['is_active' => false]);
        }

        BotIaAnnouncement::flushCache();
    }
}
