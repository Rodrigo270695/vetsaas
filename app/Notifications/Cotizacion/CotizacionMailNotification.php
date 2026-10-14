<?php

declare(strict_types=1);

namespace App\Notifications\Cotizacion;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CotizacionMailNotification extends Notification
{
    public function __construct(
        private readonly string $clinicName,
        private readonly string $clienteNombre,
        private readonly string $numero,
        private readonly string $total,
        private readonly string $vigencia,
        private readonly string $pdfBinary,
        private readonly string $pdfFilename,
        private readonly ?string $replyTo = null,
        private readonly ?string $mensaje = null,
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
        $mail = (new MailMessage)
            ->subject("{$this->clinicName}: cotización {$this->numero}")
            ->greeting("Hola {$this->clienteNombre},")
            ->line("{$this->clinicName} te envía la cotización {$this->numero}. La encontrarás adjunta en PDF.")
            ->line("Total: {$this->total}")
            ->line("Válida hasta el {$this->vigencia}.");

        if ($this->mensaje !== null && trim($this->mensaje) !== '') {
            $mail->line(trim($this->mensaje));
        }

        $mail->salutation("— {$this->clinicName}")
            ->attachData($this->pdfBinary, $this->pdfFilename, ['mime' => 'application/pdf']);

        if ($this->replyTo !== null) {
            $mail->replyTo($this->replyTo, $this->clinicName);
        }

        return $mail;
    }
}
