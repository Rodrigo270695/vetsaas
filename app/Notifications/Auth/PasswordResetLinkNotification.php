<?php

namespace App\Notifications\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Correo de "Restablecer contraseña" en VetSaaS.
 *
 * Reemplaza al `Illuminate\Auth\Notifications\ResetPassword` por
 * defecto para:
 *   1. Encolar el envío (cola `mails`) y no bloquear el HTTP.
 *   2. Generar la URL apuntando al subdominio correcto cuando el
 *      usuario pertenece a un tenant. Si lo dejáramos en manos del
 *      helper estándar, el enlace siempre saldría con el host del
 *      request actual (que puede ser el dispatcher del job).
 *   3. Texto en español y branding (cuando el usuario es de una
 *      clínica, el saludo y el footer usan el nombre comercial).
 *
 * Token storage: ver {@see \App\Auth\TenantAwarePasswordTokenRepository}.
 */
class PasswordResetLinkNotification extends Notification implements ShouldQueue
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
        $expireMinutes = (int) config('auth.passwords.users.expire', 60);

        $tenant = $notifiable->tenant_id
            ? Tenant::query()->find($notifiable->tenant_id)
            : null;

        $resetUrl = $this->buildResetUrl($notifiable, $tenant);

        $brand = $tenant
            ? ($tenant->nombre_comercial ?: $tenant->razon_social)
            : (string) config('app.name', 'VetSaaS');

        $expiresIn = Carbon::now()->addMinutes($expireMinutes);

        return (new MailMessage)
            ->subject(__('Restablece tu contraseña en :brand', ['brand' => $brand]))
            ->greeting(__('Hola :name,', ['name' => $notifiable->name ?? '']))
            ->line(__('Recibimos una solicitud para restablecer la contraseña de tu cuenta en :brand.', [
                'brand' => $brand,
            ]))
            ->action(__('Crear nueva contraseña'), $resetUrl)
            ->line(__('Este enlace expira el :expires (en :minutes minutos).', [
                'expires' => $expiresIn->isoFormat('LLLL'),
                'minutes' => $expireMinutes,
            ]))
            ->line(__('Si no fuiste tú, ignora este correo: tu contraseña actual no ha cambiado.'))
            ->salutation(__('— Equipo :brand', ['brand' => $brand]));
    }

    /**
     * Construye la URL firmada de reset apuntando al host correcto.
     *
     * - Si el usuario tiene `tenant_id`, la URL queda
     *   `https://<slug>.<root_domain>/reset-password/<token>?email=...`.
     * - Si es central, queda `https://<central_domain>/...`.
     *
     * El token va en el segmento path porque así está configurada la
     * ruta `password.reset` de Fortify; el `email` va en query string.
     */
    public function buildResetUrl(User $user, ?Tenant $tenant): string
    {
        $token = $this->token;
        $email = $user->getEmailForPasswordReset();

        $rootDomain = (string) config('tenant.root_domain', 'localhost');
        $appUrl = (string) config('app.url', 'http://localhost');
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'http';

        $appPort = parse_url($appUrl, PHP_URL_PORT);
        $portSuffix = $appPort ? ':'.$appPort : '';

        $host = $tenant && is_string($tenant->slug) && $tenant->slug !== ''
            ? $tenant->slug.'.'.$rootDomain.$portSuffix
            : (parse_url($appUrl, PHP_URL_HOST) ?: $rootDomain).$portSuffix;

        return $scheme.'://'.$host.'/reset-password/'.$token.'?'.http_build_query([
            'email' => $email,
        ]);
    }
}
