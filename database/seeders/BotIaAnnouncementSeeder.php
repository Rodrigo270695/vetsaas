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
            ['title' => 'Activa el Asistente IA en tu clínica'],
            [
                'badge' => BotIaAnnouncement::BADGE_NUEVO,
                'bullet_1' => 'Responde consultas frecuentes 24/7 por WhatsApp con la base de conocimiento de tu clínica.',
                'bullet_2' => 'Registra clientes, mascotas y agenda citas directamente desde el chat — sin que tu equipo abra VetSaaS.',
                'bullet_3' => 'Add-on desde S/. 15/mes en tu renovación: actívalo en Mi suscripción o pide a soporte que lo habilite.',
                'guide_title' => '¿Cómo lo activo?',
                'guide_body' => 'El Asistente IA es un complemento de tu plan VetSaaS. Una vez activo, conectas el mismo WhatsApp de tu clínica y el bot empieza a atender.',
                'guide_tip_1' => 'Entra a Configuración → Mi suscripción y revisa el add-on Asistente IA.',
                'guide_tip_2' => 'O contacta a soporte VetSaaS para activarlo en tu cuenta.',
                'guide_tip_3' => 'Cuando esté activo, completa horarios, servicios y FAQs para mejores respuestas.',
                'is_active' => true,
                'published_at' => now(),
                'expires_at' => null,
            ],
        );

        // Solo una novedad publicada: desactiva el resto salvo esta.
        $active = BotIaAnnouncement::query()
            ->where('title', 'Activa el Asistente IA en tu clínica')
            ->first();

        if ($active !== null) {
            BotIaAnnouncement::query()
                ->where('id', '!=', $active->id)
                ->update(['is_active' => false]);
        }

        BotIaAnnouncement::flushCache();
    }
}
