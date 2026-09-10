<?php

declare(strict_types=1);

namespace App\Services\Prospectos;

use App\Models\VeterinariaProspecto;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Importa veterinarias con lat/lng desde OpenStreetMap (Overpass)
 * y, si hay GOOGLE_PLACES_API_KEY, densifica con Places.
 */
final class VeterinariaProspectoMapaImportService
{
    public const ORIGEN_OSM = 'osm_mapa';

    public const ORIGEN_PLACES = 'google_places';

    /**
     * @return array{nuevos: int, actualizados: int, ciudades: list<string>, errores: list<string>, places: bool}
     */
    public function importNorte(?string $iniciadoPorId = null, ?string $departamento = null): array
    {
        $bboxes = config('prospectos.norte_bboxes', []);
        $deps = $departamento !== null && $departamento !== '' && $departamento !== 'todos'
            ? [$departamento]
            : ['Lambayeque'];

        $nuevos = 0;
        $actualizados = 0;
        $visitadas = [];
        $errores = [];

        foreach ($deps as $dep) {
            if (! isset($bboxes[$dep]) || ! is_array($bboxes[$dep])) {
                $errores[] = $dep.': sin bbox';

                continue;
            }

            $visitadas[] = $dep;

            try {
                $puntos = $this->fetchOverpassBbox($dep, $bboxes[$dep]);
                foreach ($puntos as $punto) {
                    $ciudad = $this->ciudadCercana($punto['lat'], $punto['lng'], $dep);
                    $result = $this->upsertPunto($punto, $ciudad, self::ORIGEN_OSM, $iniciadoPorId);
                    if ($result === 'nuevo') {
                        $nuevos++;
                    } elseif ($result === 'actualizado') {
                        $actualizados++;
                    }
                }

                $matched = $this->pegarOsmAListaExistente($dep, $puntos);
                $actualizados += $matched;
            } catch (Throwable $e) {
                Log::warning('[prospectos-mapa] Overpass falló', [
                    'departamento' => $dep,
                    'error' => $e->getMessage(),
                ]);
                $errores[] = $dep.': '.$e->getMessage();
            }

            $placesKey = trim((string) config('prospectos.places_api_key', ''));
            if ($placesKey !== '') {
                $hub = $this->hubDepartamento($dep);
                try {
                    foreach ($this->fetchPlaces($hub, $placesKey) as $punto) {
                        $result = $this->upsertPunto($punto, $hub, self::ORIGEN_PLACES, $iniciadoPorId);
                        if ($result === 'nuevo') {
                            $nuevos++;
                        } elseif ($result === 'actualizado') {
                            $actualizados++;
                        }
                    }
                } catch (Throwable $e) {
                    $errores[] = $dep.' Places: '.$e->getMessage();
                }
            }
        }

        return [
            'nuevos' => $nuevos,
            'actualizados' => $actualizados,
            'ciudades' => $visitadas,
            'errores' => $errores,
            'places' => trim((string) config('prospectos.places_api_key', '')) !== '',
        ];
    }

    /**
     * Completa XY de prospectos norte que ya están en la lista pero sin GPS.
     * Photon (rápido) + sesgo al departamento. Descarta resultados de “ciudad”.
     *
     * @return array{geocodificados: int, fallidos: int}
     */
    public function geocodePendientesNorte(int $max = 25, ?string $departamento = null): array
    {
        $deps = $departamento && $departamento !== 'todos'
            ? [$departamento]
            : $this->departamentosNorte();

        $rows = VeterinariaProspecto::query()
            ->whereNull('lat')
            ->whereIn('departamento', $deps)
            ->where(function ($q): void {
                $q->whereNotNull('direccion')->where('direccion', '!=', '');
            })
            ->orderByDesc('capturado_at')
            ->limit($max)
            ->get();

        $ok = 0;
        $fail = 0;
        $hubs = [];

        foreach ($rows as $row) {
            $dep = (string) $row->departamento;
            $hubs[$dep] ??= $this->hubDepartamento($dep);
            $q = trim(implode(', ', array_filter([
                $row->direccion,
                $row->distrito,
                $row->departamento,
                'Perú',
            ])));

            $hit = $this->photon($q, (float) $hubs[$dep]['lat'], (float) $hubs[$dep]['lng']);
            if ($hit === null) {
                $fail++;

                continue;
            }

            $row->forceFill([
                'lat' => $hit['lat'],
                'lng' => $hit['lng'],
                'geo_source' => 'photon',
            ])->save();
            $ok++;
        }

        return ['geocodificados' => $ok, 'fallidos' => $fail];
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox  south, west, north, east
     * @return list<array{nombre: string, lat: float, lng: float, telefono: ?string, direccion: ?string, osm_id: ?string}>
     */
    private function fetchOverpassBbox(string $departamento, array $bbox): array
    {
        [$s, $w, $n, $e] = array_map('floatval', $bbox);
        $ql = <<<QL
[out:json][timeout:20];
(
  nwr["amenity"="veterinary"]({$s},{$w},{$n},{$e});
  nwr["healthcare"="veterinary"]({$s},{$w},{$n},{$e});
);
out center tags;
QL;

        $urls = array_values(array_unique(array_filter([
            (string) config('prospectos.overpass_url'),
            ...config('prospectos.overpass_mirrors', []),
        ])));

        $lastError = 'sin respuesta';
        foreach ($urls as $url) {
            try {
                $response = Http::timeout(22)
                    ->withHeaders([
                        'User-Agent' => 'VetSaaSProspectos/1.0 (https://vetsaas.orvae.pe)',
                        'Accept' => 'application/json',
                    ])
                    ->asForm()
                    ->post($url, ['data' => $ql]);

                if ($response->failed()) {
                    $lastError = 'HTTP '.$response->status();

                    continue;
                }

                return $this->parseOverpassElements($response->json('elements'));
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        throw new \RuntimeException($departamento.': '.$lastError);
    }

    /**
     * @param  mixed  $elements
     * @return list<array{nombre: string, lat: float, lng: float, telefono: ?string, direccion: ?string, osm_id: ?string}>
     */
    private function parseOverpassElements(mixed $elements): array
    {
        if (! is_array($elements)) {
            return [];
        }

        $out = [];
        foreach ($elements as $el) {
            if (! is_array($el)) {
                continue;
            }
            $tags = is_array($el['tags'] ?? null) ? $el['tags'] : [];
            $nombre = trim((string) ($tags['name'] ?? $tags['name:es'] ?? ''));
            if ($nombre === '') {
                continue;
            }
            $ptLat = isset($el['lat']) ? (float) $el['lat'] : (float) ($el['center']['lat'] ?? 0);
            $ptLng = isset($el['lon']) ? (float) $el['lon'] : (float) ($el['center']['lon'] ?? 0);
            if ($ptLat === 0.0 || $ptLng === 0.0) {
                continue;
            }

            $phone = $tags['phone'] ?? $tags['contact:phone'] ?? $tags['mobile'] ?? null;
            $out[] = [
                'nombre' => Str::limit($nombre, 195, ''),
                'lat' => $ptLat,
                'lng' => $ptLng,
                'telefono' => is_string($phone) ? $phone : null,
                'direccion' => $this->osmAddress($tags),
                'osm_id' => ($el['type'] ?? 'node').'/'.($el['id'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $tags
     */
    private function osmAddress(array $tags): ?string
    {
        $parts = array_filter([
            $tags['addr:street'] ?? null,
            $tags['addr:housenumber'] ?? null,
            $tags['addr:suburb'] ?? null,
        ]);
        $line = trim(implode(' ', $parts));

        return $line !== '' ? Str::limit($line, 295, '') : null;
    }

    /**
     * @param  array{slug: string, departamento: string, provincia: string, distrito: ?string, lat: float, lng: float, radio_m: int}  $ciudad
     * @return list<array{nombre: string, lat: float, lng: float, telefono: ?string, direccion: ?string, osm_id: ?string}>
     */
    private function fetchPlaces(array $ciudad, string $key): array
    {
        $response = Http::timeout(25)->get(
            'https://maps.googleapis.com/maps/api/place/nearbysearch/json',
            [
                'location' => $ciudad['lat'].','.$ciudad['lng'],
                'radius' => 40000,
                'type' => 'veterinary_care',
                'language' => 'es',
                'key' => $key,
            ],
        );

        if ($response->failed()) {
            throw new \RuntimeException('Places HTTP '.$response->status());
        }

        $status = (string) $response->json('status');
        if (! in_array($status, ['OK', 'ZERO_RESULTS'], true)) {
            throw new \RuntimeException('Places '.$status);
        }

        $results = $response->json('results');
        if (! is_array($results)) {
            return [];
        }

        $out = [];
        foreach ($results as $place) {
            if (! is_array($place)) {
                continue;
            }
            $nombre = trim((string) ($place['name'] ?? ''));
            $geo = $place['geometry']['location'] ?? null;
            if ($nombre === '' || ! is_array($geo)) {
                continue;
            }
            $out[] = [
                'nombre' => Str::limit($nombre, 195, ''),
                'lat' => (float) $geo['lat'],
                'lng' => (float) $geo['lng'],
                'telefono' => null,
                'direccion' => isset($place['vicinity']) ? Str::limit((string) $place['vicinity'], 295, '') : null,
                'osm_id' => isset($place['place_id']) ? 'ggl/'.$place['place_id'] : null,
            ];
        }

        return $out;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function photon(string $q, float $biasLat, float $biasLng): ?array
    {
        if ($q === '') {
            return null;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'VetSaaSProspectos/1.0 (https://vetsaas.orvae.pe)'])
                ->get('https://photon.komoot.io/api/', [
                    'q' => $q,
                    'lat' => $biasLat,
                    'lon' => $biasLng,
                    'limit' => 1,
                    'lang' => 'es',
                ]);
        } catch (Throwable) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $props = $response->json('features.0.properties');
        $coords = $response->json('features.0.geometry.coordinates');
        if (! is_array($coords) || ! isset($coords[0], $coords[1])) {
            return null;
        }

        $osmValue = is_array($props) ? (string) ($props['osm_value'] ?? '') : '';
        if (in_array($osmValue, ['city', 'town', 'state', 'county', 'country', 'region'], true)) {
            return null;
        }

        $lat = (float) $coords[1];
        $lng = (float) $coords[0];
        $km = $this->haversineKm($biasLat, $biasLng, $lat, $lng);
        if ($km > 40) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng];
    }

    /**
     * Pega coordenadas OSM (buenas) a prospectos de la lista sin XY o con geocode flojo.
     *
     * @param  list<array{nombre: string, lat: float, lng: float, osm_id: ?string, direccion: ?string}>  $puntos
     */
    private function pegarOsmAListaExistente(string $departamento, array $puntos): int
    {
        if ($puntos === []) {
            return 0;
        }

        $rows = VeterinariaProspecto::query()
            ->where('departamento', $departamento)
            ->where(function ($q): void {
                $q->whereNull('lat')
                    ->orWhereIn('geo_source', ['nominatim', 'photon']);
            })
            ->get();

        $n = 0;
        foreach ($rows as $row) {
            foreach ($puntos as $punto) {
                if (! $this->nombresParecidos((string) $row->nombre, $punto['nombre'])) {
                    continue;
                }
                $row->forceFill([
                    'lat' => $punto['lat'],
                    'lng' => $punto['lng'],
                    'osm_id' => $row->osm_id ?: $punto['osm_id'],
                    'geo_source' => 'osm',
                    'direccion' => $row->direccion ?: $punto['direccion'],
                ])->save();
                $n++;
                break;
            }
        }

        return $n;
    }

    private function nombresParecidos(string $a, string $b): bool
    {
        $na = $this->normNombre($a);
        $nb = $this->normNombre($b);
        if ($na === '' || $nb === '') {
            return false;
        }
        if ($na === $nb) {
            return true;
        }
        if (mb_strlen($na) >= 5 && (str_contains($nb, $na) || str_contains($na, $nb))) {
            return true;
        }
        similar_text($na, $nb, $pct);

        return $pct >= 84;
    }

    private function normNombre(string $name): string
    {
        $n = mb_strtolower($name);
        $n = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'u', 'n'], $n);
        $n = (string) preg_replace('/\b(clinica|clínica|veterinaria|veterinario|hospital|consultorio|centro|pet|shop|spa)\b/u', '', $n);

        return trim((string) preg_replace('/[^a-z0-9]+/u', ' ', $n));
    }

    /**
     * @return array{departamento: string, provincia: string, distrito: ?string, lat: float, lng: float, radio_m: int, slug: string}
     */
    private function ciudadCercana(float $lat, float $lng, string $departamento): array
    {
        $best = $this->hubDepartamento($departamento);
        $bestKm = PHP_FLOAT_MAX;
        foreach (config('prospectos.norte_ciudades', []) as $c) {
            if (($c['departamento'] ?? '') !== $departamento) {
                continue;
            }
            $km = $this->haversineKm($lat, $lng, (float) $c['lat'], (float) $c['lng']);
            if ($km < $bestKm) {
                $bestKm = $km;
                $best = $c;
            }
        }

        return $best;
    }

    /**
     * @return array{departamento: string, provincia: string, distrito: ?string, lat: float, lng: float, radio_m: int, slug: string}
     */
    private function hubDepartamento(string $departamento): array
    {
        foreach (config('prospectos.norte_ciudades', []) as $c) {
            if (($c['departamento'] ?? '') === $departamento) {
                return $c;
            }
        }

        $origin = config('prospectos.norte_origin');

        return [
            'slug' => Str::slug($departamento),
            'departamento' => $departamento,
            'provincia' => $departamento,
            'distrito' => null,
            'lat' => (float) ($origin['lat'] ?? -6.77),
            'lng' => (float) ($origin['lng'] ?? -79.84),
            'radio_m' => 20000,
        ];
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @param  array{nombre: string, lat: float, lng: float, telefono: ?string, direccion: ?string, osm_id: ?string}  $punto
     * @param  array{departamento: string, provincia: string, distrito: ?string}  $ciudad
     */
    private function upsertPunto(array $punto, array $ciudad, string $origen, ?string $userId): string
    {
        $existing = $this->findExistente($punto, $ciudad);
        if ($existing !== null) {
            $dirty = false;
            if ($existing->lat === null || in_array((string) $existing->geo_source, ['nominatim', 'photon', ''], true)) {
                $existing->lat = $punto['lat'];
                $existing->lng = $punto['lng'];
                $existing->geo_source = $origen === self::ORIGEN_PLACES ? 'places' : 'osm';
                $dirty = true;
            }
            if ($existing->osm_id === null && $punto['osm_id']) {
                $existing->osm_id = $punto['osm_id'];
                $dirty = true;
            }
            if ($existing->direccion === null && $punto['direccion']) {
                $existing->direccion = $punto['direccion'];
                $dirty = true;
            }
            if ($dirty) {
                $existing->save();

                return 'actualizado';
            }

            return 'duplicado';
        }

        VeterinariaProspecto::query()->create([
            'nombre' => $punto['nombre'],
            'tipo' => Str::contains(mb_strtolower($punto['nombre']), 'hospital')
                ? VeterinariaProspecto::TIPO_HOSPITAL
                : VeterinariaProspecto::TIPO_CLINICA,
            'telefono' => $punto['telefono'],
            'telefono_normalizado' => VeterinariaProspecto::normalizarTelefono($punto['telefono']),
            'direccion' => $punto['direccion'],
            'departamento' => $ciudad['departamento'],
            'provincia' => $ciudad['provincia'],
            'distrito' => $ciudad['distrito'],
            'lat' => $punto['lat'],
            'lng' => $punto['lng'],
            'osm_id' => $punto['osm_id'],
            'geo_source' => $origen === self::ORIGEN_PLACES ? 'places' : 'osm',
            'origen' => $origen,
            'estado' => 'nuevo',
            'capturado_at' => now(),
            'creado_por_id' => $userId,
            'ubicacion_slug' => 'mapa-'.$ciudad['departamento'],
        ]);

        return 'nuevo';
    }

    /**
     * @param  array{nombre: string, lat: float, lng: float, telefono: ?string, osm_id: ?string}  $punto
     * @param  array{departamento: string}  $ciudad
     */
    private function findExistente(array $punto, array $ciudad): ?VeterinariaProspecto
    {
        if ($punto['osm_id']) {
            $byOsm = VeterinariaProspecto::query()->where('osm_id', $punto['osm_id'])->first();
            if ($byOsm !== null) {
                return $byOsm;
            }
        }

        $tel = VeterinariaProspecto::normalizarTelefono($punto['telefono']);
        if ($tel !== null) {
            $byTel = VeterinariaProspecto::query()->where('telefono_normalizado', $tel)->first();
            if ($byTel !== null) {
                return $byTel;
            }
        }

        $exact = VeterinariaProspecto::query()
            ->where('departamento', $ciudad['departamento'])
            ->whereRaw('lower(nombre) = ?', [mb_strtolower($punto['nombre'])])
            ->first();
        if ($exact !== null) {
            return $exact;
        }

        $candidatos = VeterinariaProspecto::query()
            ->where('departamento', $ciudad['departamento'])
            ->get(['id', 'nombre']);

        foreach ($candidatos as $row) {
            if ($this->nombresParecidos((string) $row->nombre, $punto['nombre'])) {
                return VeterinariaProspecto::query()->whereKey($row->id)->first();
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function departamentosNorte(): array
    {
        $deps = array_keys(config('prospectos.norte_bboxes', []));
        foreach (config('prospectos.norte_ciudades', []) as $c) {
            $dep = $c['departamento'] ?? null;
            if (is_string($dep) && ! in_array($dep, $deps, true)) {
                $deps[] = $dep;
            }
        }

        return array_values($deps);
    }
}
