<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\ClinicSetting;
use App\Models\RecordatorioTemplate;
use App\Support\Notifications\RecordatorioTemplateCatalog;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

final class ReminderMessageBuilder
{
    public function cita48h(
        string $clinicName,
        string $ownerName,
        string $petName,
        CarbonInterface $inicioAt,
        ?string $motivo = null,
    ): string {
        return $this->render('cita_dias_antes', [
            'propietario' => $ownerName,
            'mascota' => $petName,
            'clinica' => $clinicName,
            'motivo_linea' => $this->citaMotivoLine($motivo),
            'fecha' => $inicioAt->timezone(config('app.timezone'))->translatedFormat('d/m/Y'),
            'hora' => $inicioAt->timezone(config('app.timezone'))->format('H:i'),
        ]);
    }

    public function cita2h(
        string $clinicName,
        string $ownerName,
        string $petName,
        CarbonInterface $inicioAt,
        ?string $motivo = null,
    ): string {
        return $this->render('cita_2h', [
            'propietario' => $ownerName,
            'mascota' => $petName,
            'clinica' => $clinicName,
            'motivo_linea' => $this->citaMotivoLine($motivo),
            'hora' => $inicioAt->timezone(config('app.timezone'))->format('H:i'),
        ]);
    }

    public function citaCreada(
        string $clinicName,
        string $ownerName,
        string $petName,
        CarbonInterface $inicioAt,
        ?string $motivo = null,
    ): string {
        return $this->render('cita_creada', [
            'propietario' => $ownerName,
            'mascota' => $petName,
            'clinica' => $clinicName,
            'motivo_linea' => $this->citaMotivoLine($motivo),
            'fecha' => $inicioAt->timezone(config('app.timezone'))->translatedFormat('d/m/Y'),
            'hora' => $inicioAt->timezone(config('app.timezone'))->format('H:i'),
        ]);
    }

    public function citaReprogramada(
        string $clinicName,
        string $ownerName,
        string $petName,
        CarbonInterface $inicioAt,
        ?string $motivo = null,
    ): string {
        return $this->render('cita_reprogramada', [
            'propietario' => $ownerName,
            'mascota' => $petName,
            'clinica' => $clinicName,
            'motivo_linea' => $this->citaMotivoLine($motivo),
            'fecha' => $inicioAt->timezone(config('app.timezone'))->translatedFormat('d/m/Y'),
            'hora' => $inicioAt->timezone(config('app.timezone'))->format('H:i'),
        ]);
    }

    public function citaActualizada(
        string $clinicName,
        string $ownerName,
        string $petName,
        CarbonInterface $inicioAt,
        ?string $motivo = null,
    ): string {
        return $this->render('cita_actualizada', [
            'propietario' => $ownerName,
            'mascota' => $petName,
            'clinica' => $clinicName,
            'motivo_linea' => $this->citaMotivoLine($motivo),
            'fecha' => $inicioAt->timezone(config('app.timezone'))->translatedFormat('d/m/Y'),
            'hora' => $inicioAt->timezone(config('app.timezone'))->format('H:i'),
        ]);
    }

    /**
     * Línea de motivo para WhatsApp de citas (vacía si no hay motivo).
     */
    private function citaMotivoLine(?string $motivo): string
    {
        $motivo = trim((string) $motivo);
        if ($motivo === '') {
            return '';
        }

        return '📋 Motivo: *'.$motivo."*\n";
    }

    public function vacuna(
        string $clinicName,
        string $ownerName,
        string $petName,
        string $vacunaNombre,
        CarbonInterface $fechaRefuerzo,
    ): string {
        return $this->render('vacuna_proxima', [
            'propietario' => $ownerName,
            'mascota' => $petName,
            'clinica' => $clinicName,
            'vacuna' => $vacunaNombre,
            'fecha' => $fechaRefuerzo->timezone(config('app.timezone'))->translatedFormat('d/m/Y'),
        ]);
    }

    public function cumple(
        string $clinicName,
        string $ownerName,
        string $petName,
    ): string {
        return $this->render('cumple_mascota', [
            'propietario' => $ownerName,
            'mascota' => $petName,
            'clinica' => $clinicName,
        ]);
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
        return $this->render($esFinal ? 'grooming_foto_final' : 'grooming_foto_proceso', [
            'propietario' => $ownerName,
            'mascota' => $petName,
            'clinica' => $clinicName,
            'servicio' => $servicioLabel,
        ]);
    }

    public function groomingEstadoInicio(
        string $clinicName,
        string $ownerName,
        string $petName,
        string $servicioLabel,
    ): string {
        return $this->render('grooming_en_proceso', [
            'propietario' => $ownerName,
            'mascota' => $petName,
            'clinica' => $clinicName,
            'servicio' => $servicioLabel,
        ]);
    }

    public function groomingEstadoCompletada(
        string $clinicName,
        string $ownerName,
        string $petName,
        string $servicioLabel,
    ): string {
        return $this->render('grooming_completada', [
            'propietario' => $ownerName,
            'mascota' => $petName,
            'clinica' => $clinicName,
            'servicio' => $servicioLabel,
        ]);
    }

    public function groomingEstadoCancelada(
        string $clinicName,
        string $ownerName,
        string $petName,
        string $servicioLabel,
    ): string {
        return $this->render('grooming_cancelada', [
            'propietario' => $ownerName,
            'mascota' => $petName,
            'clinica' => $clinicName,
            'servicio' => $servicioLabel,
        ]);
    }

    public function groomingEstadoNoAsistio(
        string $clinicName,
        string $ownerName,
        string $petName,
        string $servicioLabel,
    ): string {
        return $this->render('grooming_no_asistio', [
            'propietario' => $ownerName,
            'mascota' => $petName,
            'clinica' => $clinicName,
            'servicio' => $servicioLabel,
        ]);
    }

    public function groomingTurnoProgramado(
        string $clinicName,
        string $ownerName,
        string $petName,
        string $servicioLabel,
        CarbonInterface $inicioAt,
        ?string $adelantoMonto = null,
        ?string $adelantoMoneda = null,
    ): string {
        $fecha = $inicioAt->timezone(config('app.timezone'))->translatedFormat('d/m/Y');
        $hora = $inicioAt->timezone(config('app.timezone'))->format('H:i');

        return $this->render('grooming_programado', [
            'propietario' => $ownerName,
            'mascota' => $petName,
            'clinica' => $clinicName,
            'servicio' => $servicioLabel,
            'fecha' => $fecha,
            'hora' => $hora,
            'adelanto_linea' => $this->groomingAdelantoLine($adelantoMonto, $adelantoMoneda),
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

        return $this->render('grooming_reprogramado', [
            'propietario' => $ownerName,
            'mascota' => $petName,
            'clinica' => $clinicName,
            'servicio' => $servicioLabel,
            'fecha' => $fecha,
            'hora' => $hora,
        ]);
    }

    public function groomingDiasAntes(
        string $clinicName,
        string $ownerName,
        string $petName,
        string $servicioLabel,
        CarbonInterface $inicioAt,
    ): string {
        $fecha = $inicioAt->timezone(config('app.timezone'))->translatedFormat('d/m/Y');
        $hora = $inicioAt->timezone(config('app.timezone'))->format('H:i');

        return $this->render('grooming_dias_antes', [
            'propietario' => $ownerName,
            'mascota' => $petName,
            'clinica' => $clinicName,
            'servicio' => $servicioLabel,
            'fecha' => $fecha,
            'hora' => $hora,
        ]);
    }

    public function grooming2h(
        string $clinicName,
        string $ownerName,
        string $petName,
        string $servicioLabel,
        CarbonInterface $inicioAt,
    ): string {
        $hora = $inicioAt->timezone(config('app.timezone'))->format('H:i');

        return $this->render('grooming_2h', [
            'propietario' => $ownerName,
            'mascota' => $petName,
            'clinica' => $clinicName,
            'servicio' => $servicioLabel,
            'hora' => $hora,
        ]);
    }

    public function hotelDiasAntes(
        string $clinicName,
        string $ownerName,
        string $petName,
        CarbonInterface $ingresoAt,
        ?CarbonInterface $egresoAt = null,
    ): string {
        $ingreso = $ingresoAt->timezone(config('app.timezone'));
        $fechaEgreso = $egresoAt?->timezone(config('app.timezone'))->translatedFormat('d/m/Y');

        return $this->render('hotel_dias_antes', [
            'propietario' => $ownerName,
            'mascota' => $petName,
            'clinica' => $clinicName,
            'fecha_ingreso' => $ingreso->translatedFormat('d/m/Y'),
            'hora_ingreso' => $ingreso->format('H:i'),
            'egreso_linea' => $fechaEgreso !== null
                ? "\n📅 Egreso previsto: *{$fechaEgreso}*"
                : '',
        ]);
    }

    public function hotel2h(
        string $clinicName,
        string $ownerName,
        string $petName,
        CarbonInterface $ingresoAt,
    ): string {
        return $this->render('hotel_2h', [
            'propietario' => $ownerName,
            'mascota' => $petName,
            'clinica' => $clinicName,
            'hora_ingreso' => $ingresoAt->timezone(config('app.timezone'))->format('H:i'),
        ]);
    }

    public function hotelEstanciaEvento(
        string $clinicName,
        string $ownerName,
        string $petName,
        string $evento,
        CarbonInterface $ingresoAt,
        ?CarbonInterface $egresoAt = null,
    ): string {
        $ingreso = $ingresoAt->timezone(config('app.timezone'));
        $fechaIngreso = $ingreso->translatedFormat('d/m/Y');
        $horaIngreso = $ingreso->format('H:i');
        $fechaEgreso = $egresoAt?->timezone(config('app.timezone'))->translatedFormat('d/m/Y');

        $tipo = match ($evento) {
            'reprogramada' => 'hotel_reprogramada',
            'confirmada' => 'hotel_confirmada',
            'en_estancia' => 'hotel_en_estancia',
            'completada' => 'hotel_completada',
            'cancelada' => 'hotel_cancelada',
            'no_presento' => 'hotel_no_presento',
            default => 'hotel_registrada',
        };

        return $this->render($tipo, [
            'propietario' => $ownerName,
            'mascota' => $petName,
            'clinica' => $clinicName,
            'fecha_ingreso' => $fechaIngreso,
            'hora_ingreso' => $horaIngreso,
            'egreso_linea' => $fechaEgreso !== null
                ? "\n📅 Egreso previsto: *{$fechaEgreso}*"
                : '',
        ]);
    }

    public function hotelBitacora(
        string $clinicName,
        string $ownerName,
        string $petName,
        CarbonInterface $fecha,
        ?string $notas,
    ): string {
        $detalle = trim((string) $notas);

        return $this->render('hotel_bitacora', [
            'propietario' => $ownerName,
            'mascota' => $petName,
            'clinica' => $clinicName,
            'fecha' => $fecha->translatedFormat('d/m/Y'),
            'notas' => $detalle !== '' ? $detalle : 'Sin observaciones adicionales.',
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

    /**
     * @param  array<string, string>  $variables
     */
    private function render(string $tipo, array $variables): string
    {
        $body = $this->resolveBody($tipo);

        $interpolated = $this->interpolate($body, $variables);

        return $interpolated;
    }

    private function resolveBody(string $tipo): string
    {
        $default = RecordatorioTemplateCatalog::defaultBody($tipo);
        if ($default === null) {
            return '';
        }

        if (! Schema::hasTable('cfg_recordatorio_templates')) {
            return $default;
        }

        try {
            $row = RecordatorioTemplate::query()
                ->where('tipo', $tipo)
                ->first(['cuerpo', 'activo']);
        } catch (\Throwable) {
            return $default;
        }

        if ($row === null) {
            return $default;
        }

        // Desactivada → no inventar vacío: se usa el texto de fábrica.
        if (! $row->activo) {
            return $default;
        }

        $cuerpo = trim((string) $row->cuerpo);

        return $cuerpo !== '' ? (string) $row->cuerpo : $default;
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function interpolate(string $body, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{{'.$key.'}}'] = $value;
        }

        return strtr($body, $replacements);
    }

    private function groomingAdelantoLine(?string $adelantoMonto, ?string $adelantoMoneda): string
    {
        $monto = is_string($adelantoMonto) ? trim($adelantoMonto) : '';
        if ($monto === '' || (float) $monto <= 0) {
            return '';
        }

        $moneda = trim((string) ($adelantoMoneda ?? 'PEN')) ?: 'PEN';
        $montoFmt = number_format((float) $monto, 2, '.', ',');

        return "\n💵 Adelanto recibido: *{$moneda} {$montoFmt}*";
    }
}
