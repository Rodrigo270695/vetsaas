<?php

declare(strict_types=1);

namespace App\Notifications\Subscriptions;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Oferta de reenganche Free: 1 mes gratis + CTA de aceptación.
 */
final class FreeWinBackOfferNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Tenant $tenant,
        public string $acceptUrl,
        public int $offerDays = 30,
    ) {
        $this->onQueue('mails');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = trim((string) ($this->tenant->nombre_comercial ?: $this->tenant->razon_social ?: 'tu clínica'));

        return (new MailMessage)
            ->subject('Te extrañamos en VetSaaS · 1 mes gratis para '.$brand)
            ->greeting('Hola, '.$brand.' 👋')
            ->line('Notamos que tu periodo Free de VetSaaS ya venció y queremos que vuelvas a probar la plataforma con tranquilidad.')
            ->line('Novedades recientes: chat interno del equipo, plantillas de WhatsApp y programa de referidos.')
            ->line("Como gesto, te regalamos {$this->offerDays} días gratis. Solo confirma con el botón de abajo.")
            ->action('Sí, quiero el mes gratis', $this->acceptUrl)
            ->line('Si el botón no funciona, copia y pega este enlace en tu navegador:')
            ->line($this->acceptUrl)
            ->line('Si no te interesa, puedes ignorar este correo.')
            ->salutation('— Equipo Orvae / VetSaaS');
    }
}
