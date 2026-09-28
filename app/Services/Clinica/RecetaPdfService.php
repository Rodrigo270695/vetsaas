<?php

declare(strict_types=1);

namespace App\Services\Clinica;

use App\Http\Controllers\Concerns\ResolvesClinicPdfBranding;
use App\Models\Receta;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
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

        if (Schema::hasColumn('historias_clinicas', 'numero')) {
            $receta->loadMissing([
                'consulta.historiaClinica:id,paciente_id,numero',
                'paciente.historiaClinica:id,paciente_id,numero',
            ]);
        }

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
        $examenes = trim((string) ($receta->examenes_complementarios ?? ''));
        if ($examenes === '' && $receta->consulta !== null) {
            $examenes = $receta->consulta->examenes
                ->pluck('nombre')
                ->map(static fn (mixed $nombre): string => trim((string) $nombre))
                ->filter(static fn (string $nombre): bool => $nombre !== '')
                ->implode(', ');
        }
        $control = $receta->consulta_control_at ?? $receta->consulta?->planTratamiento?->fecha_fin;
        $historiaNumero = (string) (
            $receta->consulta?->historiaClinica?->numero
            ?? $receta->paciente?->historiaClinica?->numero
            ?? ''
        );
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
                'historiaNumero' => $historiaNumero,
                'edad' => $this->edadTexto($paciente->fecha_nacimiento, $atencion),
                'peso' => $this->pesoTexto($peso),
                'fechaAtencion' => $atencion->format('d-m-Y h:i:s A'),
                'atendidoPor' => trim((string) ($receta->veterinario?->name ?? '')),
                'motivo' => trim((string) ($receta->consulta?->motivo ?? '')),
                'indicaciones' => $this->indicaciones($receta),
                'observacionesReceta' => trim((string) ($receta->observaciones ?? '')),
                'examenes' => $examenes,
                'consultaControl' => $this->fechaCorta($control),
                'signosAlarma' => trim((string) ($receta->signos_alarma ?? '')),
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

    /**
     * @return list<array{nombre: string, detalle: string}>
     */
    private function indicaciones(Receta $receta): array
    {
        $items = [];
        foreach ($receta->lineas as $ln) {
            $nombre = trim((string) $ln->nombre_medicamento);
            $detalle = [];
            $posologia = trim((string) ($ln->posologia ?? ''));
            if ($posologia !== '') {
                $detalle[] = $posologia;
            }
            if ($ln->duracion_dias !== null && (int) $ln->duracion_dias > 0) {
                $detalle[] = __('recetas.pdf.por_dias', ['n' => (int) $ln->duracion_dias]);
            }
            $instrucciones = trim((string) ($ln->instrucciones ?? ''));
            if ($instrucciones !== '') {
                $detalle[] = $instrucciones;
            }
            if ($nombre === '' && $detalle === []) {
                continue;
            }

            $items[] = [
                'nombre' => $nombre,
                'detalle' => implode(', ', $detalle),
            ];
        }

        return $items;
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

    private function fechaCorta(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('d/m/y');
        }

        return Carbon::parse((string) $value)->format('d/m/y');
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
