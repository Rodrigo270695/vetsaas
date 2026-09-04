<?php

declare(strict_types=1);

namespace App\Notifications\Clinica;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class DocumentoAutorizacionMailNotification extends Notification
{
    public function __construct(
        private readonly string $clinicName,
        private readonly string $ownerName,
        private readonly string $pacienteNombre,
        private readonly string $titulo,
        private readonly string $url,
        private readonly int $expiresDays,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->clinicName}: firma {$this->titulo}")
            ->greeting("Hola {$this->ownerName},")
            ->line("{$this->clinicName} te envía un documento para leer y firmar sobre {$this->pacienteNombre}.")
            ->line($this->titulo)
            ->action('Leer y firmar', $this->url)
            ->line("El enlace estará disponible por {$this->expiresDays} día(s).")
            ->salutation("— {$this->clinicName}");
    }
}
