<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\ClinicSetting;
use Carbon\CarbonInterface;

final class ReminderMessageBuilder
{
    public function cita48h(
        string $clinicName,
        string $ownerName,
        string $petName,
        CarbonInterface $inicioAt,
    ): string {
        return sprintf(
            "Hola %s 👋\n\n⏰ Te recordamos la cita de *%s* en *%s*\n📅 *%s* a las *%s*\n\nSi necesitas reprogramar, contáctanos.\n\nTe esperamos 🐾\n\n— %s",
            $ownerName,
            $petName,
            $clinicName,
            $inicioAt->timezone(config('app.timezone'))->translatedFormat('d/m/Y'),
            $inicioAt->timezone(config('app.timezone'))->format('H:i'),
            $clinicName,
        );
    }

    public function cita2h(
        string $clinicName,
        string $ownerName,
        string $petName,
        CarbonInterface $inicioAt,
    ): string {
        return sprintf(
            "Hola %s 👋\n\n⏳ En *2 horas* tienes cita de *%s* en *%s*\n🕒 *%s*\n\n¡Nos vemos pronto! 🐾\n\n— %s",
            $ownerName,
            $petName,
            $clinicName,
            $inicioAt->timezone(config('app.timezone'))->format('H:i'),
            $clinicName,
        );
    }

    public function citaCreada(
        string $clinicName,
        string $ownerName,
        string $petName,
        CarbonInterface $inicioAt,
    ): string {
        return sprintf(
            "Hola %s 👋\n\n✅ Registramos la cita de *%s* en *%s*\n📅 *%s* a las *%s*\n\nTe esperamos 🐾\n\n— %s",
            $ownerName,
            $petName,
            $clinicName,
            $inicioAt->timezone(config('app.timezone'))->translatedFormat('d/m/Y'),
            $inicioAt->timezone(config('app.timezone'))->format('H:i'),
            $clinicName,
        );
    }

    public function citaReprogramada(
        string $clinicName,
        string $ownerName,
        string $petName,
        CarbonInterface $inicioAt,
    ): string {
        return sprintf(
            "Hola %s 👋\n\n🔄 Reprogramamos la cita de *%s* en *%s*\n📅 Nueva fecha: *%s* a las *%s*\n\nTe esperamos 🐾\n\n— %s",
            $ownerName,
            $petName,
            $clinicName,
            $inicioAt->timezone(config('app.timezone'))->translatedFormat('d/m/Y'),
            $inicioAt->timezone(config('app.timezone'))->format('H:i'),
            $clinicName,
        );
    }

    public function vacuna(
        string $clinicName,
        string $ownerName,
        string $petName,
        string $vacunaNombre,
        CarbonInterface $fechaRefuerzo,
    ): string {
        return sprintf(
            "Hola %s 👋\n\n💉 El refuerzo de *%s* para *%s* vence el *%s*\n📋 Agenda con *%s* para mantenerlo al día.\n\nCuidamos de *%s* 🐾\n\n— %s",
            $ownerName,
            $vacunaNombre,
            $petName,
            $fechaRefuerzo->timezone(config('app.timezone'))->translatedFormat('d/m/Y'),
            $clinicName,
            $petName,
            $clinicName,
        );
    }

    public function cumple(
        string $clinicName,
        string $ownerName,
        string $petName,
    ): string {
        return sprintf(
            "Hola %s 👋\n\n🎂 ¡Hoy es el cumpleaños de *%s*! 🎉🥳\n\nDesde *%s* le enviamos un cariñoso saludo 🐾💚\n\n— %s",
            $ownerName,
            $petName,
            $clinicName,
            $clinicName,
        );
    }

    public function clinicDisplayName(?ClinicSetting $setting): string
    {
        if ($setting === null) {
            return (string) config('app.name', 'VetSaaS');
        }

        return $setting->nombre_comercial
            ?: $setting->razon_social
            ?: (string) config('app.name', 'VetSaaS');
    }
}
