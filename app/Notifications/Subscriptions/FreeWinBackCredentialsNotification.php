<?php

declare(strict_types=1);

namespace App\Notifications\Subscriptions;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Credenciales tras aceptar win-back Free (subdominio, email, password temporal).
 */
final class FreeWinBackCredentialsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Tenant $tenant,
        public string $loginEmail,
        #[\SensitiveParameter]
        public string $temporaryPassword,
        public string $loginUrl,
        public int $grantedDays = 30,
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
        $slug = (string) $this->tenant->slug;

        return (new MailMessage)
            ->subject('¡Listo! Tu mes gratis VetSaaS está activo · '.$brand)
            ->greeting('¡Bienvenido de nuevo!')
            ->line("Ya activamos {$this->grantedDays} días gratis de VetSaaS para {$brand}.")
            ->line('Datos de acceso:')
            ->line('• Subdominio: '.$slug)
            ->line('• Correo: '.$this->loginEmail)
            ->line('• Contraseña temporal: '.$this->temporaryPassword)
            ->action('Entrar a mi clínica', $this->loginUrl)
            ->line('Por seguridad, cambia la contraseña al iniciar sesión.')
            ->line('Si no solicitaste esto, responde a este correo o escríbenos por WhatsApp.')
            ->salutation('— Equipo Orvae / VetSaaS');
    }
}
