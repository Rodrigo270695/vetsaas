<?php

declare(strict_types=1);

namespace App\Services\Prospectos;

use App\Models\VeterinariaProspecto;
use App\Models\VeterinariaProspectoScrapeRun;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Scraper de prospección comercial: recorre un catálogo curado de páginas
 * públicas de directorios de veterinarias en Perú (ver `config('prospectos')`)
 * y guarda clínicas/hospitales nuevos (sin duplicar) en `veterinaria_prospectos`.
 *
 * Diseñado para ser tolerante a fallos: si una ubicación no responde o cambia
 * de formato, se registra el error y se continúa con la siguiente, sin
 * tumbar la corrida completa.
 */
class VeterinariaProspectoScraperService
{
    /**
     * @return array{
     *     run_id: string,
     *     nuevos: int,
     *     duplicados: int,
     *     sin_datos: int,
     *     ubicaciones: list<string>,
     *     errores: list<string>,
     * }
     */
    public function run(string $origen = 'cron', ?string $iniciadoPorId = null, ?string $departamento = null): array
    {
        $maxNuevos = (int) config('prospectos.max_por_corrida', 40);
        $maxUbicaciones = (int) config('prospectos.max_ubicaciones_por_corrida', 6);

        $locations = $this->pickLocations($maxUbicaciones, $departamento);

        $nuevos = 0;
        $duplicados = 0;
        $sinDatos = 0;
        $visitadas = [];
        $errores = [];

        foreach ($locations as $location) {
            if ($nuevos >= $maxNuevos) {
                break;
            }

            $visitadas[] = $location['slug'];

            try {
                $entries = $this->scrapeLocation($location);
            } catch (Throwable $e) {
                Log::warning('[prospectos] Error scrapeando ubicación', [
                    'slug' => $location['slug'],
                    'error' => $e->getMessage(),
                ]);
                $errores[] = "{$location['slug']}: {$e->getMessage()}";

                continue;
            }

            if ($entries === []) {
                $sinDatos++;

                continue;
            }

            foreach ($entries as $entry) {
                if ($nuevos >= $maxNuevos) {
                    break;
                }

                if ($this->existeDuplicado($entry)) {
                    $duplicados++;

                    continue;
                }

                VeterinariaProspecto::query()->create([
                    'nombre' => $entry['nombre'],
                    'tipo' => $entry['tipo'],
                    'telefono_normalizado' => $entry['telefono_normalizado'],
                    'telefono' => $entry['telefono'],
                    'correo' => $entry['correo'],
                    'direccion' => $entry['direccion'],
                    'departamento' => $location['departamento'],
                    'provincia' => $location['provincia'],
                    'distrito' => $location['distrito'],
                    'horario' => $entry['horario'],
                    'es_24_horas' => $entry['es_24_horas'],
                    'fuente_sitio' => $entry['fuente_sitio'],
                    'fuente_url' => $entry['fuente_url'],
                    'ubicacion_slug' => $location['slug'],
                    'origen' => VeterinariaProspecto::ORIGEN_SCRAPING,
                    'estado' => 'nuevo',
                    'capturado_at' => now(),
                ]);

                $nuevos++;
            }
        }

        $run = VeterinariaProspectoScrapeRun::query()->create([
            'origen' => $origen,
            'iniciado_at' => now(),
            'finalizado_at' => now(),
            'estado' => $errores !== [] && $nuevos === 0 ? 'error' : ($errores !== [] ? 'parcial' : 'ok'),
            'nuevos' => $nuevos,
            'duplicados' => $duplicados,
            'sin_datos' => $sinDatos,
            'ubicaciones_visitadas' => $visitadas,
            'errores' => $errores,
            'iniciado_por_id' => $iniciadoPorId,
        ]);

        return [
            'run_id' => $run->id,
            'nuevos' => $nuevos,
            'duplicados' => $duplicados,
            'sin_datos' => $sinDatos,
            'ubicaciones' => $visitadas,
            'errores' => $errores,
        ];
    }

    /**
     * Elige las próximas `$max` ubicaciones a visitar.
     *
     * - Si se indica `$departamento`, se limita el catálogo a ese
     *   departamento y se prioriza por recencia (nunca visitada primero,
     *   luego la más antigua) — modo "manual dirigido".
     * - Si no se indica (modo "automático/variado", el default del cron y
     *   del botón "Traer nuevos"), se agrupa el catálogo por departamento
     *   y se reparte en **round-robin**: una ubicación de cada departamento
     *   (priorizando los departamentos menos visitados) antes de repetir
     *   ninguno. Así una sola corrida trae variedad geográfica real en vez
     *   de agotar Lima (que tiene muchos más distritos en el catálogo)
     *   antes de tocar el resto del país.
     *
     * @return list<array{slug: string, departamento: ?string, provincia: ?string, distrito: ?string}>
     */
    private function pickLocations(int $max, ?string $departamento = null): array
    {
        $catalog = config('prospectos.ubicaciones', []);

        if ($catalog === []) {
            return [];
        }

        if ($departamento !== null && $departamento !== '') {
            $catalog = array_values(array_filter(
                $catalog,
                static fn (array $loc): bool => $loc['departamento'] === $departamento,
            ));

            if ($catalog === []) {
                return [];
            }
        }

        $lastVisited = VeterinariaProspecto::query()
            ->selectRaw('ubicacion_slug, MAX(capturado_at) as ultima')
            ->whereNotNull('ubicacion_slug')
            ->groupBy('ubicacion_slug')
            ->pluck('ultima', 'ubicacion_slug');

        // null (nunca visitada) ordena primero; luego la más antigua.
        $sortKey = static fn (array $loc): string => (string) ($lastVisited->get($loc['slug']) ?? '');

        if ($departamento !== null && $departamento !== '') {
            usort($catalog, static fn (array $a, array $b): int => $sortKey($a) <=> $sortKey($b));

            return array_slice($catalog, 0, $max);
        }

        /** @var array<string, list<array{slug: string, departamento: ?string, provincia: ?string, distrito: ?string}>> $porDepartamento */
        $porDepartamento = [];
        foreach ($catalog as $loc) {
            $porDepartamento[$loc['departamento'] ?? '—'][] = $loc;
        }

        foreach ($porDepartamento as $dep => $locs) {
            usort($locs, static fn (array $a, array $b): int => $sortKey($a) <=> $sortKey($b));
            $porDepartamento[$dep] = $locs;
        }

        $ordenDepartamentos = array_keys($porDepartamento);
        usort(
            $ordenDepartamentos,
            static fn (string $a, string $b): int => $sortKey($porDepartamento[$a][0]) <=> $sortKey($porDepartamento[$b][0]),
        );

        $picked = [];
        $cursor = array_fill_keys($ordenDepartamentos, 0);

        while (count($picked) < $max) {
            $agregoAlgo = false;

            foreach ($ordenDepartamentos as $dep) {
                if (count($picked) >= $max) {
                    break;
                }

                $idx = $cursor[$dep];
                if (! isset($porDepartamento[$dep][$idx])) {
                    continue;
                }

                $picked[] = $porDepartamento[$dep][$idx];
                $cursor[$dep]++;
                $agregoAlgo = true;
            }

            if (! $agregoAlgo) {
                break;
            }
        }

        return $picked;
    }

    /**
     * @param  array{slug: string, departamento: ?string, provincia: ?string, distrito: ?string}  $location
     * @return list<array{
     *     nombre: string,
     *     tipo: string,
     *     telefono: ?string,
     *     telefono_normalizado: ?string,
     *     correo: ?string,
     *     direccion: ?string,
     *     horario: ?string,
     *     es_24_horas: bool,
     *     fuente_sitio: string,
     *     fuente_url: string,
     * }>
     */
    private function scrapeLocation(array $location): array
    {
        $baseUrl = rtrim((string) config('prospectos.base_url'), '/');
        $url = "{$baseUrl}/{$location['slug']}/";
        $timeout = (int) config('prospectos.timeout_seg', 15);

        $response = Http::timeout($timeout)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; VetSaaSBot/1.0; +https://vetsaas.orvae.pe)',
                'Accept-Language' => 'es-PE,es;q=0.9',
            ])
            ->get($url);

        if ($response->failed()) {
            throw new \RuntimeException("HTTP {$response->status()}");
        }

        $lines = $this->htmlToLines($response->body());
        $blocks = $this->parseClinicBlocks($lines);

        $entries = [];
        foreach ($blocks as $block) {
            $telefonoNormalizado = VeterinariaProspecto::normalizarTelefono($block['telefono']);
            $nombre = trim($block['nombre']);

            if ($nombre === '') {
                continue;
            }

            $entries[] = [
                'nombre' => Str::limit($nombre, 195, ''),
                'tipo' => Str::contains(mb_strtolower($nombre), 'hospital')
                    ? VeterinariaProspecto::TIPO_HOSPITAL
                    : VeterinariaProspecto::TIPO_CLINICA,
                'telefono' => $block['telefono'] !== '' ? $block['telefono'] : null,
                'telefono_normalizado' => $telefonoNormalizado,
                'correo' => null,
                'direccion' => $block['direccion'] !== '' ? Str::limit($block['direccion'], 295, '') : null,
                'horario' => $block['horario'] !== '' ? Str::limit($block['horario'], 195, '') : null,
                'es_24_horas' => Str::contains(mb_strtolower($block['horario']), ['24 horas', 'abierto 24']),
                'fuente_sitio' => 'veterinariasperu.net',
                'fuente_url' => $url,
            ];
        }

        return $entries;
    }

    /**
     * Convierte HTML crudo a un arreglo de líneas de texto plano,
     * preservando saltos por bloque (encabezados, `<li>`, `<br>`, etc.)
     * para poder parsear por etiqueta ("Número de Teléfono:", "Dirección:"…).
     *
     * @return list<string>
     */
    private function htmlToLines(string $html): array
    {
        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/(li|h1|h2|h3|h4|h5|p|div|tr|td)>/i', "\n", $html) ?? $html;

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $lines = array_map(static function (string $line): string {
            return trim(preg_replace('/\s+/u', ' ', $line) ?? $line);
        }, $lines);

        return array_values(array_filter($lines, static fn (string $l): bool => $l !== ''));
    }

    /**
     * @param  list<string>  $lines
     * @return list<array{nombre: string, telefono: string, direccion: string, horario: string}>
     */
    private function parseClinicBlocks(array $lines): array
    {
        $blocks = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            if (! preg_match('/^N[uú]mero de Tel[eé]fono\s*:\s*(.*)$/iu', $lines[$i], $m)) {
                continue;
            }

            $telefono = trim($m[1]);

            $nombre = '';
            for ($j = $i - 1; $j >= 0; $j--) {
                if ($this->isLabelLine($lines[$j])) {
                    continue;
                }
                $nombre = $lines[$j];
                break;
            }

            if ($nombre === '') {
                continue;
            }

            $direccion = '';
            $horario = '';
            for ($k = $i + 1; $k < $count; $k++) {
                if (preg_match('/^N[uú]mero de Tel[eé]fono\s*:/iu', $lines[$k])) {
                    break;
                }
                if (preg_match('/^Direcci[oó]n\s*:\s*(.*)$/iu', $lines[$k], $mm)) {
                    $direccion = trim($mm[1]);

                    continue;
                }
                if (preg_match('/^Horario\s*:\s*(.*)$/iu', $lines[$k], $mm)) {
                    $horario = trim($mm[1]);

                    continue;
                }
            }

            $blocks[] = [
                'nombre' => $nombre,
                'telefono' => $telefono,
                'direccion' => $direccion,
                'horario' => $horario,
            ];
        }

        return $blocks;
    }

    private function isLabelLine(string $line): bool
    {
        return (bool) preg_match('/^(N[uú]mero de Tel[eé]fono|Direcci[oó]n|Web|Horario)\s*:/iu', $line);
    }

    /**
     * @param  array{telefono_normalizado: ?string, nombre: string, direccion: ?string}  $entry
     */
    private function existeDuplicado(array $entry): bool
    {
        if ($entry['telefono_normalizado'] !== null) {
            return VeterinariaProspecto::query()
                ->where('telefono_normalizado', $entry['telefono_normalizado'])
                ->exists();
        }

        return VeterinariaProspecto::query()
            ->whereRaw('lower(nombre) = ?', [mb_strtolower($entry['nombre'])])
            ->when($entry['direccion'], fn ($q) => $q->whereRaw('lower(direccion) = ?', [mb_strtolower($entry['direccion'])]))
            ->exists();
    }
}
