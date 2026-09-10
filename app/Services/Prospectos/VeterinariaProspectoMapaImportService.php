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
    public function importNorte(?string $iniciadoPorId = null): array
    {
        $ciudades = config('prospectos.norte_ciudades', []);
        $nuevos = 0;
        $actualizados = 0;
        $visitadas = [];
        $errores = [];

        foreach ($ciudades as $ciudad) {
            $visitadas[] = (string) $ciudad['slug'];

            try {
                $puntos = $this->fetchOverpass($ciudad);
                foreach ($puntos as $punto) {
                    $result = $this->upsertPunto($punto, $ciudad, self::ORIGEN_OSM, $iniciadoPorId);
                    if ($result === 'nuevo') {
                        $nuevos++;
                    } elseif ($result === 'actualizado') {
                        $actualizados++;
                    }
                }
            } catch (Throwable $e) {
                Log::warning('[prospectos-mapa] Overpass falló', [
                    'slug' => $ciudad['slug'] ?? null,
                    'error' => $e->getMessage(),
                ]);
                $errores[] = ($ciudad['slug'] ?? '?').': '.$e->getMessage();
            }

            $placesKey = trim((string) config('prospectos.places_api_key', ''));
            if ($placesKey !== '') {
                try {
                    foreach ($this->fetchPlaces($ciudad, $placesKey) as $punto) {
                        $result = $this->upsertPunto($punto, $ciudad, self::ORIGEN_PLACES, $iniciadoPorId);
                        if ($result === 'nuevo') {
                            $nuevos++;
                        } elseif ($result === 'actualizado') {
                            $actualizados++;
                        }
                    }
                } catch (Throwable $e) {
                    $errores[] = ($ciudad['slug'] ?? '?').' Places: '.$e->getMessage();
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
     *
     * @return array{geocodificados: int, fallidos: int}
     */
    public function geocodePendientesNorte(int $max = 25): array
    {
        $deps = $this->departamentosNorte();
        $rows = VeterinariaProspecto::query()
            ->whereNull('lat')
            ->whereIn('departamento', $deps)
            ->orderByDesc('capturado_at')
            ->limit($max)
            ->get();

        $ok = 0;
        $fail = 0;

        foreach ($rows as $row) {
            $q = trim(implode(', ', array_filter([
                $row->nombre,
                $row->direccion,
                $row->distrito,
                $row->provincia,
                $row->departamento,
                'Perú',
            ])));

            try {
                $hit = $this->nominatim($q);
                if ($hit === null) {
                    $fail++;
                    usleep(1_100_000);

                    continue;
                }
                $row->forceFill([
                    'lat' => $hit['lat'],
                    'lng' => $hit['lng'],
                    'geo_source' => 'nominatim',
                ])->save();
                $ok++;
            } catch (Throwable) {
                $fail++;
            }

            usleep(1_100_000);
        }

        return ['geocodificados' => $ok, 'fallidos' => $fail];
    }

    /**
     * @param  array{slug: string, departamento: string, provincia: string, distrito: ?string, lat: float, lng: float, radio_m: int}  $ciudad
     * @return list<array{nombre: string, lat: float, lng: float, telefono: ?string, direccion: ?string, osm_id: ?string}>
     */
    private function fetchOverpass(array $ciudad): array
    {
        $lat = (float) $ciudad['lat'];
        $lng = (float) $ciudad['lng'];
        $radio = (int) $ciudad['radio_m'];
        $ql = <<<QL
[out:json][timeout:40];
(
  nwr["amenity"="veterinary"](around:{$radio},{$lat},{$lng});
  nwr["healthcare"="veterinary"](around:{$radio},{$lat},{$lng});
);
out center tags;
QL;

        $url = (string) config('prospectos.overpass_url');
        $response = Http::timeout(50)
            ->withHeaders([
                'User-Agent' => 'VetSaaSProspectos/1.0 (https://vetsaas.orvae.pe)',
                'Accept' => 'application/json',
            ])
            ->asForm()
            ->post($url, ['data' => $ql]);

        if ($response->failed()) {
            throw new \RuntimeException('HTTP '.$response->status());
        }

        $elements = $response->json('elements');
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
            $addr = $this->osmAddress($tags);

            $out[] = [
                'nombre' => Str::limit($nombre, 195, ''),
                'lat' => $ptLat,
                'lng' => $ptLng,
                'telefono' => is_string($phone) ? $phone : null,
                'direccion' => $addr,
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
                'radius' => min(40000, (int) $ciudad['radio_m']),
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
    private function nominatim(string $q): ?array
    {
        $response = Http::timeout(20)
            ->withHeaders([
                'User-Agent' => 'VetSaaSProspectos/1.0 (https://vetsaas.orvae.pe)',
            ])
            ->get('https://nominatim.openstreetmap.org/search', [
                'q' => $q,
                'format' => 'json',
                'limit' => 1,
                'countrycodes' => 'pe',
            ]);

        if ($response->failed()) {
            return null;
        }

        $first = $response->json('0');
        if (! is_array($first) || ! isset($first['lat'], $first['lon'])) {
            return null;
        }

        return [
            'lat' => (float) $first['lat'],
            'lng' => (float) $first['lon'],
        ];
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
            if ($existing->lat === null) {
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

        return VeterinariaProspecto::query()
            ->where('departamento', $ciudad['departamento'])
            ->whereRaw('lower(nombre) = ?', [mb_strtolower($punto['nombre'])])
            ->first();
    }

    /**
     * @return list<string>
     */
    public function departamentosNorte(): array
    {
        $deps = [];
        foreach (config('prospectos.norte_ciudades', []) as $c) {
            $dep = $c['departamento'] ?? null;
            if (is_string($dep) && ! in_array($dep, $deps, true)) {
                $deps[] = $dep;
            }
        }

        return $deps;
    }
}
