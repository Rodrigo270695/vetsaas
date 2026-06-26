<?php

namespace App\Notifications\Tenancy;

use App\Models\Tenant;
use App\Support\Tenancy\TenantSubdomainUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al administrador de la clínica cuando soporte cambia su subdominio.
 */
class TenantSubdomainChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Tenant $tenant,
        public string $previousSlug,
        public string $newSlug,
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
        $previousHost = TenantSubdomainUrl::host($this->previousSlug);
        $newHost = TenantSubdomainUrl::host($this->newSlug);
        $loginUrl = TenantSubdomainUrl::login($this->newSlug);

        return (new MailMessage)
            ->subject(__('Tu subdominio VetSaaS ha sido actualizado'))
            ->greeting(__('¡Hola!'))
            ->line(__('El subdominio de :brand ha sido actualizado por el equipo de soporte de VetSaaS.', [
                'brand' => $brand,
            ]))
            ->line(__('Subdominio anterior: :host', ['host' => $previousHost]))
            ->line(__('Subdominio nuevo: :host', ['host' => $newHost]))
            ->action(__('Ir a mi clínica'), $loginUrl)
            ->line(__('Guarda el nuevo enlace: el subdominio anterior dejará de funcionar.'))
            ->salutation(__('— Equipo VetSaaS'));
    }
}
