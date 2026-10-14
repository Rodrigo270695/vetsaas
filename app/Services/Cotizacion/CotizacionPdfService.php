<?php

declare(strict_types=1);

namespace App\Services\Cotizacion;

use App\Http\Controllers\Concerns\ResolvesClinicPdfBranding;
use App\Models\ClinicSetting;
use App\Models\Cotizacion;
use Barryvdh\DomPDF\Facade\Pdf;
use RuntimeException;

final class CotizacionPdfService
{
    use ResolvesClinicPdfBranding;

    /**
     * @return array{binary: string, filename: string}
     */
    public function render(Cotizacion $cotizacion): array
    {
        $cotizacion->loadMissing([
            'lineas',
            'sede:id,nombre,direccion,telefono,email,distrito,provincia,departamento',
            'paciente:id,nombre,especie,raza',
            'creadoPor:id,name',
        ]);

        $branding = $this->clinicPdfBranding();
        $clinic = ClinicSetting::current();
        $primario = (string) $branding['colorPrimario'];
        $secundario = (string) $branding['colorSecundario'];

        $pdf = Pdf::loadView('pdf.cotizacion', array_merge(
            $branding,
            $this->encabezado($cotizacion, $branding),
            [
                'cotizacion' => $cotizacion,
                'clinicRuc' => trim((string) ($clinic->ruc ?? '')),
                'clinicRazonSocial' => trim((string) ($clinic->razon_social ?? '')),
                'monedaSimbolo' => $this->simbolo((string) $cotizacion->moneda),
                'atendidoPor' => trim((string) ($cotizacion->creadoPor?->name ?? '')),
                'textoSobrePrimario' => self::textoSobre($primario),
                'textoSobreSecundario' => self::textoSobre($secundario),
                'tintaPrimario' => self::tintaSobreBlanco($primario),
            ],
        ));
        $pdf->setPaper('a4', 'portrait');

        $binary = $pdf->output();
        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('No se pudo generar el PDF de la cotización.');
        }

        return [
            'binary' => $binary,
            'filename' => 'cotizacion-'.strtolower($cotizacion->numero).'.pdf',
        ];
    }

    /**
     * @param  array<string, mixed>  $branding
     * @return array{encabezadoNombre: string, encabezadoSede: string, encabezadoDireccion: string, encabezadoContacto: string}
     */
    private function encabezado(Cotizacion $cotizacion, array $branding): array
    {
        $sede = $cotizacion->sede;
        $nombre = trim((string) $branding['clinicNombre']);
        $sedeNombre = trim((string) ($sede?->nombre ?? ''));
        if ($sedeNombre !== '' && str_contains(mb_strtolower($nombre), mb_strtolower($sedeNombre))) {
            $sedeNombre = '';
        }

        $direccion = trim((string) ($sede?->direccion ?: ($branding['clinicDireccion'] ?? '') ?: ''));
        foreach ([$sede?->distrito, $sede?->provincia, $sede?->departamento] as $parte) {
            $parte = trim((string) $parte);
            if ($parte === '' || str_contains(mb_strtolower($direccion), mb_strtolower($parte))) {
                continue;
            }
            $direccion = trim($direccion.($direccion !== '' ? ', ' : '').$parte);
        }

        $contactos = [];
        foreach ([$sede?->telefono, $branding['clinicTelefono'] ?? null, $sede?->email, $branding['clinicEmail'] ?? null] as $item) {
            $item = trim((string) $item);
            if ($item !== '' && ! in_array($item, $contactos, true)) {
                $contactos[] = $item;
            }
        }

        return [
            'encabezadoNombre' => $nombre,
            'encabezadoSede' => $sedeNombre,
            'encabezadoDireccion' => $direccion,
            'encabezadoContacto' => implode(' | ', $contactos),
        ];
    }

    /**
     * Blanco o casi negro, el que contraste más con el fondo (WCAG).
     */
    public static function textoSobre(string $fondo): string
    {
        $l = self::luminancia($fondo);
        $contrasteBlanco = 1.05 / ($l + 0.05);
        $contrasteOscuro = ($l + 0.05) / (self::luminancia('#111827') + 0.05);

        return $contrasteBlanco >= $contrasteOscuro ? '#ffffff' : '#111827';
    }

    /**
     * Color de marca para texto sobre papel blanco; si es muy claro, no se leería.
     */
    public static function tintaSobreBlanco(string $color): string
    {
        $contraste = 1.05 / (self::luminancia($color) + 0.05);

        return $contraste >= 3 ? $color : '#111827';
    }

    private static function luminancia(string $hex): float
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (preg_match('/^[0-9a-fA-F]{6}$/', $hex) !== 1) {
            return 1.0;
        }

        $canal = static function (string $par): float {
            $c = hexdec($par) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $canal(substr($hex, 0, 2))
            + 0.7152 * $canal(substr($hex, 2, 2))
            + 0.0722 * $canal(substr($hex, 4, 2));
    }

    private function simbolo(string $moneda): string
    {
        return match (strtoupper($moneda)) {
            'USD' => 'US$',
            'EUR' => '€',
            default => 'S/',
        };
    }
}
