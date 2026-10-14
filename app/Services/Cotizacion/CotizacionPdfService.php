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

        $pdf = Pdf::loadView('pdf.cotizacion', array_merge(
            $branding,
            $this->encabezado($cotizacion, $branding),
            [
                'cotizacion' => $cotizacion,
                'clinicRuc' => trim((string) ($clinic->ruc ?? '')),
                'clinicRazonSocial' => trim((string) ($clinic->razon_social ?? '')),
                'monedaSimbolo' => $this->simbolo((string) $cotizacion->moneda),
                'atendidoPor' => trim((string) ($cotizacion->creadoPor?->name ?? '')),
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

    private function simbolo(string $moneda): string
    {
        return match (strtoupper($moneda)) {
            'USD' => 'US$',
            'EUR' => '€',
            default => 'S/',
        };
    }
}
