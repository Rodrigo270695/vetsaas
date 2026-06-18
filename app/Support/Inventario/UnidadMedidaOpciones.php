<?php

namespace App\Support\Inventario;

use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class UnidadMedidaOpciones
{
    /**
     * Catálogo mínimo cuando aún no existe `unidades_medida` en el tenant.
     *
     * @var list<array{codigo: string, nombre: string}>
     */
    private const CATALOGO_SISTEMA = [
        ['codigo' => 'UN', 'nombre' => 'Unidad'],
        ['codigo' => 'KG', 'nombre' => 'Kilogramo'],
        ['codigo' => 'G', 'nombre' => 'Gramo'],
        ['codigo' => 'MG', 'nombre' => 'Miligramo'],
        ['codigo' => 'L', 'nombre' => 'Litro'],
        ['codigo' => 'ML', 'nombre' => 'Mililitro'],
        ['codigo' => 'M', 'nombre' => 'Metro'],
        ['codigo' => 'CM', 'nombre' => 'Centímetro'],
        ['codigo' => 'UI', 'nombre' => 'Unidad internacional (UI)'],
        ['codigo' => 'DOSIS', 'nombre' => 'Dosis'],
        ['codigo' => 'CAJA', 'nombre' => 'Caja'],
        ['codigo' => 'BLISTER', 'nombre' => 'Blíster'],
        ['codigo' => 'TIRA', 'nombre' => 'Tira'],
        ['codigo' => 'BOLSA', 'nombre' => 'Bolsa'],
        ['codigo' => 'FRASCO', 'nombre' => 'Frasco'],
        ['codigo' => 'VIAL', 'nombre' => 'Vial'],
        ['codigo' => 'AMP', 'nombre' => 'Ampolla'],
        ['codigo' => 'TUBO', 'nombre' => 'Tubo'],
        ['codigo' => 'SOBRE', 'nombre' => 'Sobre'],
        ['codigo' => 'BOTE', 'nombre' => 'Bote'],
        ['codigo' => 'ROLLO', 'nombre' => 'Rollo'],
        ['codigo' => 'PAR', 'nombre' => 'Par'],
        ['codigo' => 'COMP', 'nombre' => 'Comprimido'],
        ['codigo' => 'GOTAS', 'nombre' => 'Gotas'],
        ['codigo' => 'JERINGA', 'nombre' => 'Jeringa'],
        ['codigo' => 'TEST', 'nombre' => 'Test / kit'],
        ['codigo' => 'LATA', 'nombre' => 'Lata'],
        ['codigo' => 'PACK', 'nombre' => 'Pack'],
        ['codigo' => 'DPA', 'nombre' => 'Dosis por aplicación'],
        ['codigo' => 'ENV', 'nombre' => 'Envase'],
    ];

    /**
     * @return list<array{id: string, codigo: string, nombre: string, es_sistema: bool, created_at: string|null}>
     */
    public static function forProductoForm(): array
    {
        if (self::canQueryUnidadesMedidaTable()) {
            try {
                return self::fromDatabase();
            } catch (Throwable $e) {
                report($e);
            }
        }

        return self::fallbackFromLegacyProductos();
    }

    /**
     * Códigos válidos para validación de formularios (store/update producto).
     *
     * @return list<string>
     */
    public static function allowedCodigos(): array
    {
        return array_values(array_unique(array_map(
            static fn (array $row): string => (string) $row['codigo'],
            self::forProductoForm(),
        )));
    }

    /**
     * @return list<array{id: string, codigo: string, nombre: string, es_sistema: bool, created_at: string|null}>
     */
    private static function fromDatabase(): array
    {
        $query = UnidadMedida::query()->where('activo', true);

        if (Schema::hasColumn('unidades_medida', 'es_sistema')) {
            $query->orderByDesc('es_sistema');
        }

        $columns = ['id', 'codigo', 'nombre'];
        if (Schema::hasColumn('unidades_medida', 'es_sistema')) {
            $columns[] = 'es_sistema';
        }
        if (Schema::hasColumn('unidades_medida', 'created_at')) {
            $columns[] = 'created_at';
        }

        return $query
            ->orderBy('nombre')
            ->get($columns)
            ->map(static function (UnidadMedida $u): array {
                $createdAt = $u->created_at;

                return [
                    'id' => (string) $u->id,
                    'codigo' => (string) $u->codigo,
                    'nombre' => (string) $u->nombre,
                    'es_sistema' => Schema::hasColumn('unidades_medida', 'es_sistema')
                        ? (bool) $u->es_sistema
                        : false,
                    'created_at' => $createdAt instanceof \DateTimeInterface
                        ? $createdAt->format(\DateTimeInterface::ATOM)
                        : null,
                ];
            })
            ->all();
    }

    private static function canQueryUnidadesMedidaTable(): bool
    {
        if (! Schema::hasTable('unidades_medida')) {
            return false;
        }

        foreach (['id', 'codigo', 'nombre', 'activo', 'deleted_at'] as $column) {
            if (! Schema::hasColumn('unidades_medida', $column)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array{id: string, codigo: string, nombre: string, es_sistema: bool, created_at: string|null}>
     */
    private static function fallbackFromLegacyProductos(): array
    {
        $byCodigo = [];

        foreach (self::CATALOGO_SISTEMA as $row) {
            $codigo = $row['codigo'];
            $byCodigo[$codigo] = [
                'id' => self::fallbackId($codigo),
                'codigo' => $codigo,
                'nombre' => $row['nombre'],
                'es_sistema' => true,
                'created_at' => null,
            ];
        }

        if (Schema::hasTable('productos') && Schema::hasColumn('productos', 'unidad')) {
            try {
                $usados = Producto::query()
                    ->select('unidad')
                    ->whereNotNull('unidad')
                    ->where('unidad', '!=', '')
                    ->distinct()
                    ->pluck('unidad');

                foreach ($usados as $codigo) {
                    $c = strtoupper(trim((string) $codigo));
                    if ($c === '' || isset($byCodigo[$c])) {
                        continue;
                    }

                    $byCodigo[$c] = [
                        'id' => self::fallbackId($c),
                        'codigo' => substr($c, 0, 20),
                        'nombre' => $c,
                        'es_sistema' => false,
                        'created_at' => null,
                    ];
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        $rows = array_values($byCodigo);
        usort($rows, static function (array $a, array $b): int {
            if ($a['es_sistema'] !== $b['es_sistema']) {
                return $a['es_sistema'] ? -1 : 1;
            }

            return strcasecmp($a['nombre'], $b['nombre']);
        });

        return $rows;
    }

    private static function fallbackId(string $codigo): string
    {
        return 'legacy-'.substr($codigo, 0, 36);
    }
}
