<?php

declare(strict_types=1);

namespace App\Services\Prospectos;

use App\Models\VeterinariaProspecto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

final class VeterinariaProspectoRutaService
{
    /**
     * Vecino más cercano desde un origen (ruta de volanteo del día).
     *
     * @param  Collection<int, VeterinariaProspecto>  $puntos
     * @return list<array<string, mixed>>
     */
    public function ordenar(Collection $puntos, float $originLat, float $originLng, int $max): array
    {
        $pendientes = $puntos
            ->filter(fn (VeterinariaProspecto $p): bool => $p->lat !== null && $p->lng !== null)
            ->values();

        $ruta = [];
        $lat = $originLat;
        $lng = $originLng;

        while ($pendientes->isNotEmpty() && count($ruta) < $max) {
            $bestIdx = 0;
            $bestKm = PHP_FLOAT_MAX;
            foreach ($pendientes as $i => $p) {
                $km = $this->haversineKm($lat, $lng, (float) $p->lat, (float) $p->lng);
                if ($km < $bestKm) {
                    $bestKm = $km;
                    $bestIdx = $i;
                }
            }

            /** @var VeterinariaProspecto $pick */
            $pick = $pendientes->get($bestIdx);
            $pendientes->forget($bestIdx);
            $pendientes = $pendientes->values();

            $ruta[] = $this->serialize($pick, count($ruta) + 1, $bestKm);
            $lat = (float) $pick->lat;
            $lng = (float) $pick->lng;
        }

        return $ruta;
    }

    /**
     * Trazado por calles (OSRM) + indicaciones en español.
     *
     * @param  list<array{lat: float, lng: float, nombre?: string}>  $stops
     * @return array{
     *     polyline: list<array{0: float, 1: float}>,
     *     km: float,
     *     minutos: int,
     *     pasos: list<array{hasta: string, textos: list<string>}>,
     *     ok: bool
     * }
     */
    public function trazarCalles(float $originLat, float $originLng, array $stops): array
    {
        $vacio = [
            'polyline' => [],
            'km' => 0.0,
            'minutos' => 0,
            'pasos' => [],
            'ok' => false,
        ];

        if ($stops === []) {
            return $vacio;
        }

        $coords = $originLng.','.$originLat;
        foreach ($stops as $stop) {
            $coords .= ';'.$stop['lng'].','.$stop['lat'];
        }

        $base = rtrim((string) config('prospectos.osrm_url', 'https://router.project-osrm.org'), '/');

        try {
            $response = Http::timeout(25)
                ->withHeaders(['User-Agent' => 'VetSaaSProspectos/1.0 (https://vetsaas.orvae.pe)'])
                ->get($base.'/route/v1/driving/'.$coords, [
                    'overview' => 'full',
                    'geometries' => 'geojson',
                    'steps' => 'true',
                    'alternatives' => 'false',
                ]);
        } catch (Throwable) {
            return $vacio;
        }

        if ($response->failed() || $response->json('code') !== 'Ok') {
            return $vacio;
        }

        $route = $response->json('routes.0');
        if (! is_array($route)) {
            return $vacio;
        }

        $polyline = [];
        $coordsJson = $route['geometry']['coordinates'] ?? [];
        if (is_array($coordsJson)) {
            foreach ($coordsJson as $pair) {
                if (is_array($pair) && isset($pair[0], $pair[1])) {
                    $polyline[] = [(float) $pair[1], (float) $pair[0]];
                }
            }
        }

        $pasos = [];
        $legs = is_array($route['legs'] ?? null) ? $route['legs'] : [];
        foreach ($legs as $i => $leg) {
            if (! is_array($leg)) {
                continue;
            }
            $hasta = $stops[$i]['nombre'] ?? ('Parada '.($i + 1));
            $textos = [];
            $steps = is_array($leg['steps'] ?? null) ? $leg['steps'] : [];
            foreach ($steps as $step) {
                if (! is_array($step)) {
                    continue;
                }
                $texto = $this->pasoEnEspanol($step);
                if ($texto !== '') {
                    $textos[] = $texto;
                }
            }
            $pasos[] = [
                'hasta' => (string) $hasta,
                'textos' => array_values(array_unique($textos)),
            ];
        }

        return [
            'polyline' => $polyline,
            'km' => round(((float) ($route['distance'] ?? 0)) / 1000, 1),
            'minutos' => (int) round(((float) ($route['duration'] ?? 0)) / 60),
            'pasos' => $pasos,
            'ok' => $polyline !== [],
        ];
    }

    /**
     * @param  array<string, mixed>  $step
     */
    public function pasoEnEspanol(array $step): string
    {
        $maneuver = is_array($step['maneuver'] ?? null) ? $step['maneuver'] : [];
        $type = (string) ($maneuver['type'] ?? '');
        $mod = (string) ($maneuver['modifier'] ?? '');
        $calle = trim((string) ($step['name'] ?? ''));
        $metros = (int) round((float) ($step['distance'] ?? 0));
        $dist = $metros >= 1000
            ? round($metros / 1000, 1).' km'
            : $metros.' m';

        $giro = match ($mod) {
            'left', 'sharp left' => 'a la izquierda',
            'slight left' => 'leve a la izquierda',
            'right', 'sharp right' => 'a la derecha',
            'slight right' => 'leve a la derecha',
            'uturn' => 'en U',
            'straight' => 'derecho',
            default => '',
        };

        $base = match ($type) {
            'depart' => 'Salí',
            'arrive' => 'Llegás',
            'turn' => $giro !== '' ? 'Girá '.$giro : 'Girá',
            'new name', 'continue' => $giro === 'derecho' || $giro === '' ? 'Seguí derecho' : 'Seguí '.$giro,
            'merge' => 'Incorporate',
            'on ramp' => 'Tomá la rampa',
            'off ramp', 'exit rotary', 'exit roundabout' => 'Salí',
            'roundabout', 'rotary' => 'Entrá a la rotonda',
            'fork' => $giro !== '' ? 'En el desvío tomá '.$giro : 'Tomá el desvío',
            'end of road' => $giro !== '' ? 'Al final de la calle girá '.$giro : 'Al final de la calle',
            default => '',
        };

        if ($base === '') {
            return '';
        }

        if ($type === 'arrive') {
            return 'Llegás a la parada';
        }

        $linea = $base;
        if ($calle !== '' && $type !== 'depart') {
            $linea .= ' por '.$calle;
        } elseif ($calle !== '' && $type === 'depart') {
            $linea .= ' por '.$calle;
        }
        if ($metros > 15 && $type !== 'arrive') {
            $linea .= ' ('.$dist.')';
        }

        return $linea;
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $stops
     */
    public function googleMapsDirUrl(float $originLat, float $originLng, array $stops, bool $navegar = false): ?string
    {
        if ($stops === []) {
            return null;
        }

        $chunk = array_slice($stops, 0, 10);
        $dest = $chunk[count($chunk) - 1];
        $waypoints = array_slice($chunk, 0, -1);

        $params = [
            'api' => '1',
            'origin' => $originLat.','.$originLng,
            'destination' => $dest['lat'].','.$dest['lng'],
            'travelmode' => 'driving',
        ];
        if ($navegar) {
            $params['dir_action'] = 'navigate';
        }
        if ($waypoints !== []) {
            $params['waypoints'] = implode('|', array_map(
                static fn (array $s): string => $s['lat'].','.$s['lng'],
                $waypoints,
            ));
        }

        return 'https://www.google.com/maps/dir/?'.http_build_query($params);
    }

    /**
     * Navegación por calles sin cuenta Google (OSRM público).
     *
     * @param  list<array{lat: float, lng: float}>  $stops
     */
    public function osrmNavUrl(float $originLat, float $originLng, array $stops): ?string
    {
        if ($stops === []) {
            return null;
        }

        $chunk = array_slice($stops, 0, 12);
        $locs = ['loc='.$originLat.','.$originLng];
        foreach ($chunk as $stop) {
            $locs[] = 'loc='.$stop['lat'].','.$stop['lng'];
        }

        return 'https://map.project-osrm.org/?z=14&center='.$originLat.','.$originLng
            .'&'.implode('&', $locs)
            .'&hl=es&alt=0&srv=1';
    }

    public function osmDireccionUrl(float $fromLat, float $fromLng, float $toLat, float $toLng): string
    {
        return 'https://www.openstreetmap.org/directions?engine=fossgis_osrm_car&route='
            .$fromLat.'%2C'.$fromLng.';'.$toLat.'%2C'.$toLng;
    }

    public function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round($earth * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(VeterinariaProspecto $p, int $orden, float $kmDesdeAnterior): array
    {
        return [
            'id' => $p->id,
            'orden' => $orden,
            'nombre' => $p->nombre,
            'telefono' => $p->telefono,
            'direccion' => $p->direccion,
            'departamento' => $p->departamento,
            'distrito' => $p->distrito,
            'estado' => $p->estado,
            'lat' => (float) $p->lat,
            'lng' => (float) $p->lng,
            'km_desde_anterior' => $kmDesdeAnterior,
            'volante_visitado_at' => $p->volante_visitado_at?->toIso8601String(),
            'maps_url' => 'https://www.openstreetmap.org/?mlat='.$p->lat.'&mlon='.$p->lng.'#map=18/'.$p->lat.'/'.$p->lng,
        ];
    }
}
