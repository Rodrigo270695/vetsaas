<?php

declare(strict_types=1);

namespace App\Services\Prospectos;

use App\Models\VeterinariaProspecto;
use App\Models\VolanteRuta;
use App\Models\VolanteRutaParada;
use Illuminate\Support\Facades\DB;
use App\Support\Database\PublicSchema;
use RuntimeException;

final class VolanteRutaPlanService
{
    public function __construct(
        private readonly VeterinariaProspectoRutaService $rutas,
    ) {}

    public function tablasListas(): bool
    {
        return PublicSchema::hasTable('volante_rutas') && PublicSchema::hasTable('volante_ruta_paradas');
    }

    public function abierta(): ?VolanteRuta
    {
        if (! $this->tablasListas()) {
            return null;
        }

        return VolanteRuta::query()
            ->where('estado', VolanteRuta::ABIERTA)
            ->with('paradas')
            ->latest()
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function historial(int $limit = 20): array
    {
        if (! $this->tablasListas()) {
            return [];
        }

        return VolanteRuta::query()
            ->withCount([
                'paradas as visitadas_count' => fn ($q) => $q->whereNotNull('visitado_at'),
            ])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (VolanteRuta $r): array => [
                'id' => $r->id,
                'departamento' => $r->departamento,
                'estado' => $r->estado,
                'paradas' => $r->paradas_count,
                'visitadas' => (int) ($r->visitadas_count ?? 0),
                'km' => $r->km,
                'minutos' => $r->minutos,
                'fecha' => $r->created_at?->timezone((string) config('app.timezone'))->format('d M H:i'),
            ])
            ->all();
    }

    public function find(string $id): ?VolanteRuta
    {
        if (! $this->tablasListas()) {
            return null;
        }

        return VolanteRuta::query()->with('paradas')->whereKey($id)->first();
    }

    public function generar(
        string $departamento,
        float $originLat,
        float $originLng,
        int $max,
        ?string $userId,
    ): VolanteRuta {
        if (! $this->tablasListas()) {
            throw new RuntimeException('Falta migrar las tablas de rutas de volantes.');
        }

        if ($this->abierta() !== null) {
            throw new RuntimeException('Ya hay una ruta abierta. Completala o cancelala antes de armar otra.');
        }

        $ocupados = VolanteRutaParada::query()
            ->whereHas('ruta', fn ($q) => $q->where('estado', VolanteRuta::ABIERTA))
            ->pluck('prospecto_id');

        $query = VeterinariaProspecto::query()
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->whereNull('volante_visitado_at')
            ->where('estado', '!=', 'no_interesado')
            ->whereNotIn('id', $ocupados);

        if ($departamento !== 'todos') {
            $query->where('departamento', $departamento);
        }

        $ordenadas = $this->rutas->ordenar($query->limit(400)->get(), $originLat, $originLng, $max);
        if ($ordenadas === []) {
            throw new RuntimeException('No hay clínicas con XY pendientes de volante en esa zona.');
        }

        $calles = $this->rutas->trazarCalles($originLat, $originLng, array_map(
            static fn (array $s): array => ['lat' => $s['lat'], 'lng' => $s['lng'], 'nombre' => $s['nombre']],
            $ordenadas,
        ));

        return DB::transaction(function () use ($departamento, $originLat, $originLng, $max, $userId, $ordenadas, $calles): VolanteRuta {
            $ruta = VolanteRuta::query()->create([
                'departamento' => $departamento,
                'estado' => VolanteRuta::ABIERTA,
                'origin_lat' => $originLat,
                'origin_lng' => $originLng,
                'max_paradas' => $max,
                'paradas_count' => count($ordenadas),
                'km' => $calles['ok'] ? $calles['km'] : round(array_sum(array_column($ordenadas, 'km_desde_anterior')), 1),
                'minutos' => $calles['minutos'] ?: null,
                'polyline' => $calles['polyline'] !== [] ? $calles['polyline'] : null,
                'creado_por_id' => $userId,
                'iniciado_at' => now(),
            ]);

            foreach ($ordenadas as $stop) {
                VolanteRutaParada::query()->create([
                    'ruta_id' => $ruta->id,
                    'prospecto_id' => $stop['id'],
                    'orden' => $stop['orden'],
                    'nombre' => $stop['nombre'],
                    'direccion' => $stop['direccion'] ?? null,
                    'distrito' => $stop['distrito'] ?? null,
                    'telefono' => $stop['telefono'] ?? null,
                    'lat' => $stop['lat'],
                    'lng' => $stop['lng'],
                    'km_desde_anterior' => $stop['km_desde_anterior'],
                ]);
            }

            return $ruta->load('paradas');
        });
    }

    public function completar(VolanteRuta $ruta): void
    {
        abort_unless($ruta->estado === VolanteRuta::ABIERTA, 422, 'Esa ruta ya no está abierta.');
        $ruta->forceFill([
            'estado' => VolanteRuta::COMPLETADA,
            'completado_at' => now(),
        ])->save();
    }

    public function cancelar(VolanteRuta $ruta): void
    {
        abort_unless($ruta->estado === VolanteRuta::ABIERTA, 422, 'Esa ruta ya no está abierta.');
        $ruta->forceFill([
            'estado' => VolanteRuta::CANCELADA,
            'completado_at' => now(),
        ])->save();
    }

    public function marcarParada(VeterinariaProspecto $prospecto, bool $dejarVolante = true): void
    {
        $ahora = now();
        if ($dejarVolante) {
            $prospecto->forceFill(['volante_visitado_at' => $prospecto->volante_visitado_at ?? $ahora])->save();
        } else {
            $prospecto->forceFill(['volante_visitado_at' => null])->save();
        }

        $abierta = $this->abierta();
        if ($abierta === null) {
            return;
        }

        $parada = $abierta->paradas->firstWhere('prospecto_id', $prospecto->id);
        if ($parada === null) {
            return;
        }

        $parada->forceFill([
            'visitado_at' => $dejarVolante ? ($parada->visitado_at ?? $ahora) : null,
            'resultado' => $dejarVolante ? 'volante' : null,
        ])->save();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function paradasComoRuta(VolanteRuta $ruta, VeterinariaProspectoRutaService $nav): array
    {
        $out = [];
        foreach ($ruta->paradas as $p) {
            $out[] = [
                'id' => $p->prospecto_id,
                'parada_id' => $p->id,
                'orden' => $p->orden,
                'nombre' => $p->nombre,
                'telefono' => $p->telefono,
                'direccion' => $p->direccion,
                'departamento' => $ruta->departamento,
                'distrito' => $p->distrito,
                'lat' => (float) $p->lat,
                'lng' => (float) $p->lng,
                'km_desde_anterior' => (float) $p->km_desde_anterior,
                'visitado' => $p->visitado_at !== null,
                'nav_url' => $nav->osmDireccionUrl(
                    (float) $ruta->origin_lat,
                    (float) $ruta->origin_lng,
                    (float) $p->lat,
                    (float) $p->lng,
                ),
                'maps_url' => 'https://www.openstreetmap.org/?mlat='.$p->lat.'&mlon='.$p->lng.'#map=18/'.$p->lat.'/'.$p->lng,
            ];
        }

        return $out;
    }
}
