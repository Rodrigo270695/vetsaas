<?php

declare(strict_types=1);

namespace App\Services\Prospectos;

use App\Models\VeterinariaProspecto;
use Illuminate\Support\Collection;

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
     * @param  list<array{lat: float, lng: float}>  $stops
     */
    public function googleMapsDirUrl(float $originLat, float $originLng, array $stops): ?string
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
        if ($waypoints !== []) {
            $params['waypoints'] = implode('|', array_map(
                static fn (array $s): string => $s['lat'].','.$s['lng'],
                $waypoints,
            ));
        }

        return 'https://www.google.com/maps/dir/?'.http_build_query($params);
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
            'maps_url' => 'https://www.google.com/maps/search/?api=1&query='.$p->lat.','.$p->lng,
        ];
    }
}
