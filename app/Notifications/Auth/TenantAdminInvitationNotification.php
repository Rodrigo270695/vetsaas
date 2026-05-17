<?php

namespace App\Notifications\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo de "Tu clínica está lista en VetSaaS".
 *
 * Lo envía el comando `vetsaas:tenant-create-admin` (Fase 2.6) cuando
 * se crea un admin sin password explícito: en lugar de mostrar la
 * contraseña en consola y exigir que soporte la transmita por canales
 * inseguros, generamos un token de reset de larga vida y dejamos que
 * el admin defina su contraseña él mismo desde el correo.
 *
 * El token se almacena en `password_reset_tokens` con `tenant_id`
 * incluido (gracias al {@see \App\Auth\TenantAwarePasswordTokenRepository}),
 * así que es exactamente el mismo flujo que un "forgot password" pero
 * con un copy distinto.
 */
class TenantAdminInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        #[\SensitiveParameter] public string $token,
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
        /** @var User $notifiable */
        $tenant = $notifiable->tenant_id
            ? Tenant::query()->find($notifiable->tenant_id)
            : null;

        $brand = $tenant
            ? ($tenant->nombre_comercial ?: $tenant->razon_social)
            : (string) config('app.name', 'VetSaaS');

        $url = (new PasswordResetLinkNotification($this->token))->buildResetUrl($notifiable, $tenant);

        return (new MailMessage)
            ->subject(__('Bienvenido a :brand · activa tu cuenta', ['brand' => $brand]))
            ->greeting(__('¡Hola :name!', ['name' => $notifiable->name ?? '']))
            ->line(__('Tu clínica :brand ya está lista en VetSaaS. Para empezar a usarla, define la contraseña de tu cuenta de administrador.', [
                'brand' => $brand,
            ]))
            ->action(__('Definir contraseña'), $url)
            ->line(__('El enlace expira en 60 minutos por seguridad. Si caduca antes de que lo uses, podrás solicitar otro desde el botón "¿Olvidaste tu contraseña?" en la pantalla de inicio de sesión.'))
            ->line(__('Si no esperabas este correo, puedes ignorarlo: tu cuenta queda inactiva hasta que actives la contraseña.'))
            ->salutation(__('— Equipo VetSaaS'));
    }
}
