<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Envío de correos de autenticación (reset, invitación admin).
 *
 * Por defecto se envían en el mismo request (`notifyNow`) para que funcionen
 * sin `queue:work`. Activar cola con MAIL_QUEUE_AUTH_NOTIFICATIONS=true.
 */
final class AuthNotifier
{
    public static function send(object $notifiable, Notification $notification): void
    {
        if (config('mail.queue_auth_notifications', false)) {
            $notifiable->notify($notification);

            return;
        }

        NotificationFacade::sendNow($notifiable, $notification);
    }
}
