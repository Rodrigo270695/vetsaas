<?php

declare(strict_types=1);

namespace App\Services\Clinica;

use App\Http\Controllers\Concerns\ResolvesClinicPdfBranding;
use App\Models\Receta;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
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
            'paciente.propietario:id,nombres,apellidos,razon_social,numero_documento,direccion',
            'lineas' => fn ($q) => $q->orderBy('orden'),
            'veterinario:id,name',
            'sede:id,nombre,direccion,telefono,email,distrito,provincia,departamento',
            'consulta:id,atendido_at,motivo,peso_kg',
            'consulta.examenes:id,consulta_id,nombre,orden',
            'consulta.planTratamiento:id,consulta_id,fecha_fin',
        ]);

        if ($receta->paciente === null) {
            throw new \RuntimeException('La receta no tiene paciente.');
        }

        $tz = (string) config('app.timezone', 'America/Lima');
        $atencion = $receta->consulta?->atendido_at !== null
            ? Carbon::parse($receta->consulta->atendido_at)->timezone($tz)
            : ($receta->emitida_at?->copy()->timezone($tz) ?? now($tz));

        $paciente = $receta->paciente;
        $propietario = $paciente->propietario;
        $peso = $receta->consulta?->peso_kg ?: $paciente->peso_kg;
        $examenes = '';
        if ($receta->consulta !== null) {
            $examenes = $receta->consulta->examenes
                ->pluck('nombre')
                ->map(static fn (mixed $nombre): string => trim((string) $nombre))
                ->filter(static fn (string $nombre): bool => $nombre !== '')
                ->implode(', ');
        }
        $control = $receta->consulta?->planTratamiento?->fecha_fin;
        $branding = $this->clinicPdfBranding('receta');

        $pdf = Pdf::loadView('pdf.receta', array_merge(
            $branding,
            $this->encabezado(
                $receta,
                (string) $branding['clinicNombre'],
                $branding['clinicDireccion'],
                $branding['clinicEmail'],
                $branding['clinicTelefono'],
            ),
            [
                'receta' => $receta,
                'propietarioNombre' => $this->mayus($this->propietarioNombreParaPdf($paciente)),
                'documento' => trim((string) ($propietario?->numero_documento ?? '')),
                'direccionPropietario' => $this->mayus(trim((string) ($propietario?->direccion ?? ''))),
                'mascota' => $this->mayus((string) $paciente->nombre),
                'especie' => $this->mayus(trim((string) ($paciente->especie ?? ''))),
                'raza' => $this->mayus(trim((string) ($paciente->raza ?? ''))),
                'sexo' => $this->sexoTexto($paciente->sexo),
                'reproductivo' => $this->reproductivoTexto($paciente->esterilizado),
                'fechaNacimiento' => $paciente->fecha_nacimiento?->format('d-m-Y') ?? '',
                'microchip' => trim((string) ($paciente->microchip ?? '')),
                'edad' => $this->edadTexto($paciente->fecha_nacimiento, $atencion),
                'peso' => $this->pesoTexto($peso),
                'fechaAtencion' => $atencion->format('d-m-Y h:i:s A'),
                'atendidoPor' => trim((string) ($receta->veterinario?->name ?? '')),
                'motivo' => trim((string) ($receta->consulta?->motivo ?? '')),
                'indicacionMedica' => $this->indicacionMedica($receta),
                'examenes' => $examenes,
                'consultaControl' => $control !== null ? $control->format('d/m/y') : '',
            ],
        ));
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

    /**
     * @return array{encabezadoNombre: string, encabezadoDireccion: string, encabezadoContacto: string}
     */
    private function encabezado(
        Receta $receta,
        string $clinicNombre,
        ?string $clinicDireccion,
        ?string $clinicEmail,
        ?string $clinicTelefono,
    ): array {
        $sede = $receta->sede;
        $nombre = $this->mayus($clinicNombre);
        $sedeNombre = trim((string) ($sede?->nombre ?? ''));
        if ($sedeNombre !== '' && ! str_contains(mb_strtolower($nombre), mb_strtolower($sedeNombre))) {
            $nombre .= ' '.$this->mayus($sedeNombre);
        }

        $direccion = trim((string) ($sede?->direccion ?: $clinicDireccion ?: ''));
        foreach ([$sede?->distrito, $sede?->provincia, $sede?->departamento] as $parte) {
            $parte = trim((string) $parte);
            if ($parte === '' || str_contains(mb_strtolower($direccion), mb_strtolower($parte))) {
                continue;
            }
            $direccion = trim($direccion.' '.$parte);
        }

        $contactos = [];
        foreach ([$sede?->email, $clinicEmail, $sede?->telefono, $clinicTelefono] as $item) {
            $item = trim((string) $item);
            if ($item !== '' && ! in_array($item, $contactos, true)) {
                $contactos[] = $item;
            }
        }

        return [
            'encabezadoNombre' => $nombre,
            'encabezadoDireccion' => $direccion !== '' ? $this->mayus($direccion) : '',
            'encabezadoContacto' => implode(' | ', $contactos),
        ];
    }

    private function indicacionMedica(Receta $receta): string
    {
        $trozos = [];
        foreach ($receta->lineas as $ln) {
            $partes = [trim((string) $ln->nombre_medicamento)];
            $posologia = trim((string) ($ln->posologia ?? ''));
            if ($posologia !== '') {
                $partes[] = $posologia;
            }
            if ($ln->duracion_dias !== null && (int) $ln->duracion_dias > 0) {
                $partes[] = __('recetas.pdf.por_dias', ['n' => (int) $ln->duracion_dias]);
            }
            $texto = implode(' ', array_filter($partes, static fn (string $parte): bool => $parte !== ''));
            $instrucciones = trim((string) ($ln->instrucciones ?? ''));
            if ($instrucciones !== '') {
                $texto .= ', '.$instrucciones;
            }
            if ($texto !== '') {
                $trozos[] = $texto;
            }
        }

        $cuerpo = $trozos === []
            ? ''
            : __('recetas.pdf.tratamiento_prefix').implode(', ', $trozos).'.';
        $obs = trim((string) ($receta->observaciones ?? ''));
        if ($obs === '') {
            return $cuerpo;
        }

        return $cuerpo === '' ? $obs : $cuerpo.' '.$obs;
    }

    private function sexoTexto(?string $sexo): string
    {
        return match (strtoupper(trim((string) $sexo))) {
            'M', 'MACHO' => __('recetas.pdf.sexo_macho'),
            'H', 'F', 'HEMBRA' => __('recetas.pdf.sexo_hembra'),
            '' => '',
            default => $this->mayus((string) $sexo),
        };
    }

    private function reproductivoTexto(?bool $esterilizado): string
    {
        return match ($esterilizado) {
            true => __('recetas.pdf.reproductivo_castrado'),
            false => __('recetas.pdf.reproductivo_entero'),
            default => '',
        };
    }

    private function edadTexto(?CarbonInterface $nacimiento, CarbonInterface $al): string
    {
        if ($nacimiento === null) {
            return '';
        }

        $desde = $nacimiento->copy()->startOfDay();
        $hasta = $al->copy()->startOfDay();
        if ($desde->greaterThan($hasta)) {
            return '';
        }

        $diff = $desde->diff($hasta);
        $years = (int) $diff->y;
        $months = (int) $diff->m;
        if ($years >= 1 && $months === 0) {
            return trans_choice('recetas.pdf.edad_anios', $years, ['n' => $years]);
        }
        if ($years >= 1) {
            return __('recetas.pdf.edad_anios_meses', [
                'anios' => trans_choice('recetas.pdf.edad_anios', $years, ['n' => $years]),
                'meses' => trans_choice('recetas.pdf.edad_meses', $months, ['n' => $months]),
            ]);
        }
        if ($months >= 1) {
            return trans_choice('recetas.pdf.edad_meses', $months, ['n' => $months]);
        }

        $days = (int) $diff->d;

        return trans_choice('recetas.pdf.edad_dias', $days, ['n' => $days]);
    }

    private function pesoTexto(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '';
        }

        $n = (float) $raw;
        if ($n <= 0) {
            return '';
        }

        $texto = rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');

        return $texto.' kg';
    }

    private function mayus(string $value): string
    {
        $value = trim($value);
        if ($value === '' || $value === '—') {
            return '';
        }

        return mb_strtoupper($value, 'UTF-8');
    }
}
