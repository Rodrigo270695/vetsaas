<?php

namespace App\Notifications\Tenancy;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo al admin de la clínica con el acceso recuperado por soporte SaaS.
 */
class TenantAdminAccessRecoveredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Tenant $tenant,
        public string $adminEmail,
        public string $loginUrl,
        public ?string $bootstrapUrl,
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
        $brand = $this->tenant->nombre_comercial ?: $this->tenant->razon_social;

        $mail = (new MailMessage)
            ->subject(__('Acceso de administrador VetSaaS · :brand', ['brand' => $brand]))
            ->greeting(__('¡Hola!'))
            ->line(__('El equipo de VetSaaS restableció el acceso de administrador de :brand.', [
                'brand' => $brand,
            ]))
            ->line(__('Correo de ingreso: :email', ['email' => $this->adminEmail]));

        if (is_string($this->bootstrapUrl) && $this->bootstrapUrl !== '') {
            $mail
                ->action(__('Definir o restablecer contraseña'), $this->bootstrapUrl)
                ->line(__('El enlace de bienvenida es válido unas 48 horas. Si caduca, usa «Olvidé mi contraseña» en el inicio de sesión.'));
        } else {
            $mail->action(__('Ir a mi clínica'), $this->loginUrl);
        }

        return $mail
            ->line(__('También puedes entrar desde: :url', ['url' => $this->loginUrl]))
            ->salutation(__('— Equipo VetSaaS'));
    }
}
