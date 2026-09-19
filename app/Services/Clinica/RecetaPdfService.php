<?php

declare(strict_types=1);

namespace App\Services\Clinica;

use App\Http\Controllers\Concerns\ResolvesClinicPdfBranding;
use App\Models\ClinicSetting;
use App\Models\Receta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class RecetaPdfService
{
    use ResolvesClinicPdfBranding;

    /**
     * @return array{binary: string, filename: string}
     */
    public function render(Receta $receta): array
    {
        $receta->loadMissing([
            'paciente.propietario:id,nombres,apellidos,razon_social',
            'lineas' => fn ($q) => $q->orderBy('orden')->with('producto:id,nombre,sku,unidad'),
            'veterinario:id,name',
            'sede:id,nombre,codigo',
            'consulta:id,atendido_at',
        ]);

        if ($receta->paciente === null) {
            throw new \RuntimeException('La receta no tiene paciente.');
        }

        $clinic = ClinicSetting::current();
        $tz = (string) config('app.timezone', 'America/Lima');
        $emitidaAt = $receta->emitida_at !== null
            ? $receta->emitida_at->copy()->timezone($tz)->format('d/m/Y H:i')
            : '—';

        $consultaAt = '—';
        if ($receta->consulta?->atendido_at !== null) {
            $consultaAt = Carbon::parse($receta->consulta->atendido_at)->timezone($tz)->format('d/m/Y H:i');
        }

        $pdf = Pdf::loadView('pdf.receta', [
            'clinicNombre' => $clinic->nombre_comercial
                ?: $clinic->razon_social
                ?: (string) config('app.name', 'Clínica'),
            'logoDataUri' => $this->clinicLogoDataUri($clinic),
            'colorPrimario' => $this->sanitizeHexColor($clinic->color_primario, '#166534'),
            'colorSecundario' => $this->sanitizeHexColor($clinic->color_secundario, '#f0fdf4'),
            'clinicEmail' => $clinic->email_institucional,
            'clinicTelefono' => $clinic->telefono_principal,
            'clinicWeb' => $clinic->web_url,
            'clinicDireccion' => $clinic->direccion_fiscal,
            'receta' => $receta,
            'propietarioNombre' => $this->propietarioNombreParaPdf($receta->paciente),
            'emitidaAt' => $emitidaAt,
            'consultaAt' => $consultaAt,
            'generadoEn' => now($tz)->format('d/m/Y H:i'),
        ]);
        $pdf->setPaper('a4', 'portrait');

        $slug = Str::slug($receta->paciente->nombre ?? 'paciente') ?: 'paciente';
        $filename = 'receta-'.$slug.'.pdf';
        $binary = $pdf->output();

        if (! is_string($binary) || $binary === '') {
            throw new \RuntimeException('No se pudo generar el PDF de la receta.');
        }

        return [
            'binary' => $binary,
            'filename' => $filename,
        ];
    }
}
