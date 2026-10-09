<?php

declare(strict_types=1);

namespace App\Support\Clinica;

use App\Models\Antipulga;
use App\Models\Cita;
use App\Models\Cirugia;
use App\Models\ControlClinico;
use App\Models\Defuncion;
use App\Models\Desparasitacion;
use App\Models\GroomingTurno;
use App\Models\HotelEstancia;
use App\Models\Internamiento;
use App\Models\PedidoLaboratorio;
use App\Models\Triaje;
use Illuminate\Support\Carbon;

/**
 * Bloques de «Ver resumen» para los registros de la línea de tiempo
 * que no son una consulta ni una aplicación.
 */
final class HistorialTimelineResumen
{
    /**
     * @param  list<array{key: string, text?: string, seccion?: string, opciones?: list<string>}>  $bloques
     * @param  array<string, string|int>|null  $constantes
     * @return array{bloques: list<array<string, mixed>>, constantes: array<string, string|int>|null}|null
     */
    public static function armar(array $bloques, ?array $constantes = null): ?array
    {
        $limpios = [];
        foreach ($bloques as $bloque) {
            $text = isset($bloque['text']) ? trim($bloque['text']) : '';
            $opciones = $bloque['opciones'] ?? null;
            $tieneOpciones = is_array($opciones) && $opciones !== [];
            if ($text === '' && ! $tieneOpciones) {
                continue;
            }

            if ($text !== '') {
                $bloque['text'] = mb_substr($text, 0, 2000);
            }

            $limpios[] = $bloque;
        }

        if ($limpios === [] && ($constantes === null || $constantes === [])) {
            return null;
        }

        return [
            'bloques' => $limpios,
            'constantes' => $constantes === [] ? null : $constantes,
        ];
    }

    public static function control(ControlClinico $row): ?array
    {
        $motivo = trim((string) ($row->motivo ?? ''));
        $diagnosticos = array_values(array_filter(
            is_array($row->diagnosticos) ? $row->diagnosticos : [],
            static fn (mixed $item): bool => is_string($item) && trim($item) !== '',
        ));

        return self::armar([
            ['key' => 'motivo', 'text' => $motivo !== '' && strcasecmp($motivo, 'Control') !== 0 ? $motivo : ''],
            ['key' => 'anamnesis', 'text' => (string) ($row->anamnesis ?? '')],
            ['key' => 'anamnesis_detalle', 'text' => (string) ($row->anamnesis_detalle ?? '')],
            ['key' => 'examen', 'text' => (string) ($row->examen_clinico ?? '')],
            ['key' => 'examen_detalle', 'text' => (string) ($row->examen_detalle ?? '')],
            ['key' => 'diagnostico', 'text' => implode("\n", $diagnosticos)],
            ['key' => 'examenes', 'text' => (string) (self::lineas($row->examenes) ?? '')],
            ['key' => 'tratamiento', 'text' => (string) (self::lineas($row->tratamiento) ?? '')],
            ['key' => 'receta', 'text' => (string) (self::lineas($row->receta) ?? '')],
            ['key' => 'tlc', 'text' => (string) ($row->tlc ?? '')],
            ['key' => 'pa', 'text' => (string) ($row->pa ?? '')],
            ['key' => 'hidratacion', 'text' => (string) ($row->hidratacion ?? '')],
            ['key' => 'comentarios', 'text' => (string) ($row->comentarios ?? '')],
        ], self::constantes($row->peso_kg, $row->temperatura_c, $row->fc_lpm, $row->fr_rpm));
    }

    public static function antiparasitario(Desparasitacion|Antipulga $row): ?array
    {
        return self::armar([
            ['key' => 'anamnesis', 'text' => (string) ($row->anamnesis ?? '')],
            ['key' => 'apetito', 'text' => (string) ($row->apetito ?? '')],
            ['key' => 'ingesta', 'text' => (string) ($row->ingesta_agua ?? '')],
            ['key' => 'vomitos', 'text' => self::par($row->vomitos_frecuencia, $row->vomitos_descripcion) ?? ''],
            ['key' => 'heces', 'text' => self::par($row->heces_frecuencia, $row->heces_descripcion) ?? ''],
            ['key' => 'orina', 'text' => self::par(
                self::par($row->orina_frecuencia, $row->orina_color),
                $row->orina_olor,
            ) ?? ''],
            ['key' => 'ultimo_celo', 'text' => $row->ultimo_celo instanceof Carbon ? $row->ultimo_celo->toDateString() : ''],
            ['key' => 'aplicados', 'text' => (string) (self::aplicados($row->aplicados) ?? '')],
            ['key' => 'receta', 'text' => (string) (self::lineas($row->receta) ?? '')],
            ['key' => 'tlc', 'text' => (string) ($row->tlc ?? '')],
            ['key' => 'pa', 'text' => (string) ($row->pa ?? '')],
            ['key' => 'hidratacion', 'text' => (string) ($row->hidratacion ?? '')],
            ['key' => 'comentarios', 'text' => (string) ($row->comentarios ?? '')],
        ], self::constantes($row->peso_kg, $row->temperatura_c, $row->fc_lpm, $row->fr_rpm));
    }

    public static function defuncion(Defuncion $row): ?array
    {
        return self::armar([
            ['key' => 'motivo', 'text' => (string) ($row->motivo ?? '')],
            ['key' => 'sitio', 'text' => (string) ($row->sitio ?? '')],
            ['key' => 'testigo', 'text' => (string) ($row->testigo ?? '')],
            ['key' => 'comentarios', 'text' => (string) ($row->comentarios ?? '')],
        ]);
    }

    /**
     * @param  array<string, mixed>  $hallazgos
     * @param  list<string>|null  $recomendaciones
     */
    public static function triaje(array $hallazgos, ?array $recomendaciones, Triaje $row): ?array
    {
        $bloques = [];
        foreach (array_keys(TriajeCatalogo::SECCIONES) as $seccion) {
            $marcadas = $hallazgos[$seccion] ?? null;
            if (! is_array($marcadas) || $marcadas === []) {
                continue;
            }

            $opciones = array_values(array_filter(
                $marcadas,
                static fn (mixed $item): bool => is_string($item) && $item !== '',
            ));
            if ($opciones === []) {
                continue;
            }

            $bloques[] = [
                'key' => 'hallazgo',
                'seccion' => $seccion,
                'opciones' => $opciones,
            ];
        }

        $recs = array_values(array_filter(
            is_array($recomendaciones) ? $recomendaciones : [],
            static fn (mixed $item): bool => is_string($item) && $item !== '',
        ));
        if ($recs !== []) {
            $bloques[] = ['key' => 'recomendaciones', 'opciones' => $recs];
        }

        $bloques[] = ['key' => 'tlc', 'text' => (string) ($row->tlc ?? '')];
        $bloques[] = ['key' => 'pa', 'text' => (string) ($row->pa ?? '')];
        $bloques[] = ['key' => 'hidratacion', 'text' => (string) ($row->hidratacion ?? '')];
        $bloques[] = ['key' => 'comentarios', 'text' => (string) ($row->comentarios ?? '')];

        return self::armar($bloques, self::constantes($row->peso_kg, $row->temperatura_c, $row->fc_lpm, $row->fr_rpm));
    }

    public static function laboratorio(PedidoLaboratorio $pedido): ?array
    {
        $lineas = [];
        foreach ($pedido->lineas as $linea) {
            $nombre = trim((string) $linea->nombre_examen);
            if ($nombre === '') {
                continue;
            }
            $extra = self::par(
                is_string($linea->indicaciones) ? $linea->indicaciones : null,
                is_string($linea->resultado) ? $linea->resultado : null,
            );
            $lineas[] = $extra !== null ? $nombre.' · '.$extra : $nombre;
        }

        return self::armar([
            ['key' => 'destino', 'text' => (string) ($pedido->laboratorio_destino ?? '')],
            ['key' => 'examenes', 'text' => implode("\n", $lineas)],
            ['key' => 'observaciones', 'text' => (string) ($pedido->observaciones ?? '')],
        ]);
    }

    public static function cirugia(Cirugia $cirugia): ?array
    {
        return self::armar([
            ['key' => 'anestesia', 'text' => (string) ($cirugia->tipo_anestesia ?? '')],
            ['key' => 'observaciones', 'text' => (string) ($cirugia->observaciones ?? '')],
        ]);
    }

    public static function internamiento(Internamiento $internamiento, string $tz): ?array
    {
        $alta = $internamiento->alta_at instanceof Carbon
            ? $internamiento->alta_at->timezone($tz)->format('d/m/Y H:i')
            : '';

        return self::armar([
            ['key' => 'diagnostico_ingreso', 'text' => (string) ($internamiento->diagnostico_ingreso ?? '')],
            ['key' => 'ubicacion', 'text' => (string) ($internamiento->ubicacion ?? '')],
            ['key' => 'alta', 'text' => $alta],
            ['key' => 'notas', 'text' => (string) ($internamiento->notas ?? '')],
        ]);
    }

    public static function grooming(GroomingTurno $turno): ?array
    {
        $duracion = (int) $turno->duracion_minutos;

        return self::armar([
            ['key' => 'duracion', 'text' => $duracion > 0 ? $duracion.' min' : ''],
            ['key' => 'notas', 'text' => (string) ($turno->notas ?? '')],
        ]);
    }

    public static function hotel(HotelEstancia $estancia, string $tz): ?array
    {
        $egreso = $estancia->egreso_at instanceof Carbon
            ? $estancia->egreso_at->timezone($tz)->format('d/m/Y H:i')
            : '';

        return self::armar([
            ['key' => 'egreso', 'text' => $egreso],
            ['key' => 'notas', 'text' => (string) ($estancia->notas ?? '')],
        ]);
    }

    public static function cita(Cita $cita): ?array
    {
        return self::armar([
            ['key' => 'motivo', 'text' => (string) ($cita->motivo ?? '')],
            ['key' => 'notas', 'text' => (string) ($cita->notas ?? '')],
        ]);
    }

    /**
     * @return array<string, string|int>|null
     */
    public static function constantes(mixed $peso, mixed $temp, mixed $fc, mixed $fr): ?array
    {
        $out = [];
        if ($peso !== null && trim((string) $peso) !== '') {
            $out['peso_kg'] = rtrim(rtrim(number_format((float) $peso, 3, '.', ''), '0'), '.');
        }
        if ($temp !== null && trim((string) $temp) !== '') {
            $out['temperatura_c'] = rtrim(rtrim(number_format((float) $temp, 2, '.', ''), '0'), '.');
        }
        if ($fc !== null && (int) $fc > 0) {
            $out['fc_lpm'] = (int) $fc;
        }
        if ($fr !== null && (int) $fr > 0) {
            $out['fr_rpm'] = (int) $fr;
        }

        return $out === [] ? null : $out;
    }

    /**
     * @param  list<array<string, mixed>>|null  $lineas
     */
    public static function lineas(?array $lineas): ?string
    {
        if (! is_array($lineas) || $lineas === []) {
            return null;
        }

        $parts = [];
        foreach ($lineas as $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $nombre = trim((string) ($linea['nombre'] ?? ''));
            if ($nombre === '') {
                continue;
            }
            $cantidad = trim((string) ($linea['cantidad'] ?? ''));
            $esp = trim((string) ($linea['especificaciones'] ?? ''));
            $bits = array_filter([
                $nombre,
                $cantidad !== '' ? '× '.$cantidad : null,
                $esp !== '' ? $esp : null,
            ]);
            $parts[] = implode(' · ', $bits);
        }

        return $parts === [] ? null : implode("\n", $parts);
    }

    /**
     * @param  list<array<string, mixed>>|null  $aplicados
     */
    public static function aplicados(?array $aplicados): ?string
    {
        if (! is_array($aplicados) || $aplicados === []) {
            return null;
        }

        $parts = [];
        foreach ($aplicados as $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $nombre = trim((string) ($linea['nombre'] ?? ''));
            if ($nombre === '') {
                continue;
            }
            $esp = trim((string) ($linea['especificaciones'] ?? ''));
            $proxima = trim((string) ($linea['proxima_at'] ?? ''));
            $bits = array_filter([
                $nombre,
                $esp !== '' ? $esp : null,
                $proxima !== '' ? $proxima : null,
            ]);
            $parts[] = implode(' · ', $bits);
        }

        return $parts === [] ? null : implode("\n", $parts);
    }

    private static function par(?string $a, ?string $b): ?string
    {
        $parts = array_values(array_filter([
            is_string($a) && trim($a) !== '' ? trim($a) : null,
            is_string($b) && trim($b) !== '' ? trim($b) : null,
        ]));

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
