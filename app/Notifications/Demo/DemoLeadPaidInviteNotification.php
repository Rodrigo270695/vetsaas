<?php

declare(strict_types=1);

namespace App\Notifications\Demo;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invita a un lead de la demo a crear su clínica de pago.
 */
final class DemoLeadPaidInviteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?string $clinicName,
        public string $registerUrl,
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
        $brand = trim((string) ($this->clinicName ?: 'tu clínica'));

        return (new MailMessage)
            ->subject('¿Listos para tu propia clínica en VetSaaS?')
            ->greeting('Hola'.($this->clinicName ? ', '.$brand : '').' 👋')
            ->line('Vimos que entraste a la demo de VetSaaS. Si te gustó, el siguiente paso es abrir tu propia clínica (no la demo compartida) y empezar a atender con tu equipo.')
            ->line('Planes desde Starter: historias clínicas, agenda, caja, inventario y WhatsApp.')
            ->action('Crear mi clínica', $this->registerUrl)
            ->line('Si el botón no funciona, copia este enlace:')
            ->line($this->registerUrl)
            ->line('¿Prefieres un tour de 15 min? Responde este correo y te agendamos.')
            ->salutation('— Equipo Orvae / VetSaaS');
    }
}
