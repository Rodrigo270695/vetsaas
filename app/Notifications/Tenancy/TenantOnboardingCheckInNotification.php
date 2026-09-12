<?php

declare(strict_types=1);

namespace App\Notifications\Tenancy;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Seguimiento de onboarding cuando la clínica no tiene celular de WhatsApp.
 */
final class TenantOnboardingCheckInNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Tenant $tenant,
        public string $loginUrl,
        public bool $isFree,
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
        $brand = trim((string) ($this->tenant->nombre_comercial ?: $this->tenant->razon_social ?: $this->tenant->slug));
        $planLine = $this->isFree
            ? 'Te escribimos de VetSaaS para ver cómo te está yendo con el plan Free.'
            : 'Te escribimos de VetSaaS para ver cómo te está yendo con tu clínica.';
        $upgradeLine = $this->isFree
            ? 'Cuando quieras pasar a un plan de pago, responde este correo y te ayudamos.'
            : 'Si necesitas algo del plan o de la clínica, responde este correo.';

        return (new MailMessage)
            ->subject('¿Cómo te va con VetSaaS? · '.$brand)
            ->greeting('Hola, '.$brand)
            ->line($planLine)
            ->line('¿Pudiste entrar a tu clínica y cargar pacientes o una cita?')
            ->line('Correo de ingreso: '.$this->tenant->email_admin)
            ->action('Entrar a mi clínica', $this->loginUrl)
            ->line('Si te trabaste en algún paso, responde este correo y te ayudamos.')
            ->line($upgradeLine)
            ->salutation('— Equipo VetSaaS / Orvae');
    }
}
