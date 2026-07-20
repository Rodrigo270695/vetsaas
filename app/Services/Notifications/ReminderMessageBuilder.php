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

    public function ventaComprobante(
        string $clinicName,
        string $ownerName,
        string $numeroDisplay,
        string $totalFormatted,
        string $fechaDisplay,
        ?string $pdfUrl = null,
    ): string {
        $lines = [
            "Hola {$ownerName} 👋",
            '',
            "🧾 Ticket de *{$clinicName}*",
            "📄 *{$numeroDisplay}*",
            "💰 Total: *{$totalFormatted}*",
            "📅 {$fechaDisplay}",
        ];

        if ($pdfUrl !== null && $pdfUrl !== '') {
            $lines[] = '';
            $lines[] = "📎 PDF: {$pdfUrl}";
        }

        $lines[] = '';
        $lines[] = 'Gracias por tu preferencia 🐾';
        $lines[] = '';
        $lines[] = "— {$clinicName}";

        return implode("\n", $lines);
    }

    public function felDocumento(
        string $clinicName,
        string $recipientName,
        string $numeroCompleto,
        string $tipoLabel,
        string $totalFormatted,
        string $fechaDisplay,
    ): string {
        $lines = [
            "Hola {$recipientName} 👋",
            '',
            "🧾 *{$tipoLabel}* de *{$clinicName}*",
            "📄 *{$numeroCompleto}*",
            "💰 Total: *{$totalFormatted}*",
            "📅 {$fechaDisplay}",
            '',
            'Gracias por tu preferencia 🐾',
            '',
            "— {$clinicName}",
        ];

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $examenes
     */
    public function laboratorioResultados(
        string $clinicName,
        string $recipientName,
        string $petName,
        array $examenes,
        string $fechaDisplay,
    ): string {
        $examenesLabel = $examenes !== []
            ? implode(', ', array_slice($examenes, 0, 5))
            : 'análisis de laboratorio';

        if (count($examenes) > 5) {
            $examenesLabel .= '…';
        }

        return implode("\n", [
            "Hola {$recipientName} 👋",
            '',
            "🧪 Resultados de laboratorio de *{$petName}*",
            "📋 {$examenesLabel}",
            "📅 {$fechaDisplay}",
            '',
            'Te compartimos el(los) documento(s) adjunto(s).',
            '',
            "— {$clinicName}",
        ]);
    }

    public function groomingProcesoFoto(
        string $clinicName,
        string $ownerName,
        string $petName,
        string $servicioLabel,
        bool $esFinal,
    ): string {
        $headline = $esFinal
            ? '✨ ¡*%s* ya terminó su grooming!'
            : '✂️ *%s* está en grooming';

        $lines = [
            "Hola {$ownerName} 👋",
            '',
            sprintf($headline, $petName),
            "🧴 Servicio: *{$servicioLabel}*",
            '',
            $esFinal
                ? 'Te compartimos la foto final 📸'
                : 'Te compartimos una foto del proceso 📸',
            '',
            "— {$clinicName}",
        ];

        return implode("\n", $lines);
    }

    public function groomingEstadoInicio(
        string $clinicName,
        string $ownerName,
        string $petName,
        string $servicioLabel,
    ): string {
        return implode("\n", [
            "Hola {$ownerName} 👋",
            '',
            "✂️ *{$petName}* ya está en grooming",
            "🧴 Servicio: *{$servicioLabel}*",
            '',
            'Te avisaremos cuando termine 🐾',
            '',
            "— {$clinicName}",
        ]);
    }

    public function groomingEstadoCompletada(
        string $clinicName,
        string $ownerName,
        string $petName,
        string $servicioLabel,
    ): string {
        return implode("\n", [
            "Hola {$ownerName} 👋",
            '',
            "✨ ¡*{$petName}* ya terminó su grooming!",
            "🧴 Servicio: *{$servicioLabel}*",
            '',
            'Ya puede pasar a recogerlo 🐾',
            '',
            "— {$clinicName}",
        ]);
    }

    public function groomingEstadoCancelada(
        string $clinicName,
        string $ownerName,
        string $petName,
        string $servicioLabel,
    ): string {
        return implode("\n", [
            "Hola {$ownerName} 👋",
            '',
            "El turno de grooming de *{$petName}* fue *cancelado*.",
            "🧴 Servicio: *{$servicioLabel}*",
            '',
            'Si deseas reagendar, escríbenos o llama a la clínica.',
            '',
            "— {$clinicName}",
        ]);
    }

    public function groomingEstadoNoAsistio(
        string $clinicName,
        string $ownerName,
        string $petName,
        string $servicioLabel,
    ): string {
        return implode("\n", [
            "Hola {$ownerName} 👋",
            '',
            "Registramos que *{$petName}* *no asistió* a su turno de grooming.",
            "🧴 Servicio: *{$servicioLabel}*",
            '',
            'Si fue un imprevisto, podemos ayudarte a reagendar.',
            '',
            "— {$clinicName}",
        ]);
    }

    public function groomingTurnoProgramado(
        string $clinicName,
        string $ownerName,
        string $petName,
        string $servicioLabel,
        CarbonInterface $inicioAt,
    ): string {
        $fecha = $inicioAt->timezone(config('app.timezone'))->translatedFormat('d/m/Y');
        $hora = $inicioAt->timezone(config('app.timezone'))->format('H:i');

        return implode("\n", [
            "Hola {$ownerName} 👋",
            '',
            "✅ Agendamos el grooming de *{$petName}*",
            "🧴 Servicio: *{$servicioLabel}*",
            "📅 *{$fecha}* a las *{$hora}*",
            '',
            'Te esperamos 🐾',
            '',
            "— {$clinicName}",
        ]);
    }

    public function groomingTurnoReprogramado(
        string $clinicName,
        string $ownerName,
        string $petName,
        string $servicioLabel,
        CarbonInterface $inicioAt,
    ): string {
        $fecha = $inicioAt->timezone(config('app.timezone'))->translatedFormat('d/m/Y');
        $hora = $inicioAt->timezone(config('app.timezone'))->format('H:i');

        return implode("\n", [
            "Hola {$ownerName} 👋",
            '',
            "🔄 Reprogramamos el grooming de *{$petName}*",
            "🧴 Servicio: *{$servicioLabel}*",
            "📅 Nueva fecha: *{$fecha}* a las *{$hora}*",
            '',
            'Te esperamos 🐾',
            '',
            "— {$clinicName}",
        ]);
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
