<?php

declare(strict_types=1);

namespace App\Support\Clinica;

use App\Models\ClinicSetting;
use App\Models\Consulta;
use App\Models\DocumentoAutorizacionPlantilla;
use App\Models\Paciente;
use App\Models\Propietario;
use Illuminate\Support\Carbon;

final class DocumentoAutorizacionRenderer
{
    public static function defaultCuerpo(): string
    {
        return "Yo, {{propietario}}, identificada(o) con documento {{documento}}, titular de {{paciente}}, autorizo a {{clinica}} a realizar los procedimientos clínicos correspondientes a la atención del {{fecha}}.\n\nDeclaro haber leído este documento y haber podido formular preguntas.";
    }

    /**
     * @return array<string, string>
     */
    public static function variablesFor(Consulta $consulta, Paciente $paciente, ?Propietario $owner): array
    {
        $clinic = ClinicSetting::current();
        $clinicName = trim((string) ($clinic->nombre_comercial ?: $clinic->razon_social))
            ?: (string) config('app.name', 'Clínica');

        $doc = trim(implode(' ', array_filter([
            $owner?->tipo_documento,
            $owner?->numero_documento,
        ])));

        return [
            'paciente' => $paciente->nombre,
            'propietario' => $owner?->displayName() ?: '—',
            'documento' => $doc !== '' ? $doc : '—',
            'fecha' => ($consulta->atendido_at ?? Carbon::now())->timezone((string) config('app.timezone'))->format('d/m/Y H:i'),
            'clinica' => $clinicName,
            'veterinario' => trim((string) ($consulta->medico_tratante ?: $consulta->veterinario?->name)) ?: '—',
        ];
    }

    /**
     * @param  array<string, string>  $vars
     */
    public static function render(string $cuerpo, array $vars): string
    {
        $replaced = $cuerpo;
        foreach ($vars as $key => $value) {
            $replaced = str_replace('{{'.$key.'}}', $value, $replaced);
        }

        return $replaced;
    }

    public static function renderPlantilla(
        DocumentoAutorizacionPlantilla $plantilla,
        Consulta $consulta,
        Paciente $paciente,
        ?Propietario $owner,
    ): string {
        return self::render($plantilla->cuerpo, self::variablesFor($consulta, $paciente, $owner));
    }
}
