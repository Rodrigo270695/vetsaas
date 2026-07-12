<?php

namespace App\Services\Inventario;

use App\Models\CategoriaProducto;
use App\Models\Producto;
use App\Support\Inventario\UnidadMedidaOpciones;
use App\Support\Plan\PlanLimits;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

final class ProductoInventarioImportService
{
    public const MAX_ROWS = 500;

    /**
     * @return array{
     *     ok: bool,
     *     imported: int,
     *     failed: int,
     *     skipped: int,
     *     rows: list<array{row: int, nombre: string, status: string, message: string}>,
     *     error?: string
     * }
     */
    public function import(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            return [
                'ok' => false,
                'imported' => 0,
                'failed' => 0,
                'skipped' => 0,
                'rows' => [],
                'error' => 'El archivo debe ser .xlsx',
            ];
        }

        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            return [
                'ok' => false,
                'imported' => 0,
                'failed' => 0,
                'skipped' => 0,
                'rows' => [],
                'error' => 'No se pudo leer el archivo.',
            ];
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'imported' => 0,
                'failed' => 0,
                'skipped' => 0,
                'rows' => [],
                'error' => 'No se pudo abrir el Excel. Verifica que no esté dañado.',
            ];
        }

        $sheet = $spreadsheet->getSheetByName('Productos') ?? $spreadsheet->getSheet(0);
        $rawRows = $sheet->toArray(null, true, true, false);

        $headerIndex = null;
        $headers = [];
        foreach ($rawRows as $i => $row) {
            $normalized = array_map(fn ($cell) => $this->normalizeHeader((string) ($cell ?? '')), $row);
            if (in_array('nombre', $normalized, true) && in_array('unidad', $normalized, true)) {
                $headerIndex = $i;
                $headers = $normalized;
                break;
            }
        }

        if ($headerIndex === null) {
            $spreadsheet->disconnectWorksheets();

            return [
                'ok' => false,
                'imported' => 0,
                'failed' => 0,
                'skipped' => 0,
                'rows' => [],
                'error' => 'No se encontró la fila de encabezados (nombre*, unidad*, …) en la hoja Productos.',
            ];
        }

        $allowedUnidades = array_fill_keys(
            array_map('strtoupper', UnidadMedidaOpciones::allowedCodigos()),
            true,
        );

        $categoriasByNombre = CategoriaProducto::query()
            ->where('activo', true)
            ->get(['id', 'nombre'])
            ->mapWithKeys(fn (CategoriaProducto $c) => [mb_strtolower(trim((string) $c->nombre)) => (string) $c->id])
            ->all();

        $userId = Auth::id();
        $results = [];
        $imported = 0;
        $failed = 0;
        $skipped = 0;
        $processedDataRows = 0;
        $skusInFile = [];

        for ($i = $headerIndex + 1; $i < count($rawRows); $i++) {
            $excelRow = $i + 1;
            $cells = $rawRows[$i] ?? [];

            if ($this->rowIsEmpty($cells)) {
                continue;
            }

            $data = [];
            foreach ($headers as $colIndex => $header) {
                if ($header === '') {
                    continue;
                }
                $data[$header] = trim((string) ($cells[$colIndex] ?? ''));
            }

            $nombre = $data['nombre'] ?? '';
            if ($nombre === '' || $this->isExampleRow($nombre)) {
                $skipped++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombre !== '' ? $nombre : '—',
                    'status' => 'skipped',
                    'message' => $nombre === ''
                        ? 'Fila vacía (sin nombre).'
                        : 'Fila de ejemplo omitida.',
                ];
                continue;
            }

            $processedDataRows++;
            if ($processedDataRows > self::MAX_ROWS) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombre,
                    'status' => 'error',
                    'message' => 'Se alcanzó el máximo de '.self::MAX_ROWS.' filas por importación.',
                ];
                continue;
            }

            if (PlanLimits::wouldExceed('max_productos', adding: 1)) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombre,
                    'status' => 'error',
                    'message' => PlanLimits::message('max_productos'),
                ];
                continue;
            }

            $unidad = strtoupper($data['unidad'] ?? '');
            if ($unidad === '') {
                $unidad = 'UN';
            }
            $unidad = substr($unidad, 0, 20);

            if (! isset($allowedUnidades[$unidad])) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombre,
                    'status' => 'error',
                    'message' => "Unidad «{$unidad}» no válida. Usa un código de la hoja Unidades.",
                ];
                continue;
            }

            $categoriaNombre = $data['categoria'] ?? '';
            $categoriaId = null;
            if ($categoriaNombre !== '') {
                $key = mb_strtolower($categoriaNombre);
                if (! isset($categoriasByNombre[$key])) {
                    $failed++;
                    $results[] = [
                        'row' => $excelRow,
                        'nombre' => $nombre,
                        'status' => 'error',
                        'message' => "Categoría «{$categoriaNombre}» no encontrada. Revisa la hoja Categorias.",
                    ];
                    continue;
                }
                $categoriaId = $categoriasByNombre[$key];
            }

            $sku = ($data['sku'] ?? '') !== '' ? mb_substr($data['sku'], 0, 64) : null;
            if ($sku !== null) {
                $skuKey = mb_strtolower($sku);
                if (isset($skusInFile[$skuKey])) {
                    $failed++;
                    $results[] = [
                        'row' => $excelRow,
                        'nombre' => $nombre,
                        'status' => 'error',
                        'message' => "SKU «{$sku}» duplicado en el archivo (fila {$skusInFile[$skuKey]}).",
                    ];
                    continue;
                }
                if (Producto::query()->where('sku', $sku)->exists()) {
                    $failed++;
                    $results[] = [
                        'row' => $excelRow,
                        'nombre' => $nombre,
                        'status' => 'error',
                        'message' => "SKU «{$sku}» ya existe en el catálogo.",
                    ];
                    continue;
                }
                $skusInFile[$skuKey] = $excelRow;
            }

            $precioVenta = $this->parseDecimal($data['precio_venta'] ?? '');
            $precioCompra = $this->parseDecimal($data['precio_compra'] ?? '');
            $stockMinimo = $this->parseDecimal($data['stock_minimo'] ?? '');

            if ($precioVenta === false || $precioCompra === false || $stockMinimo === false) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombre,
                    'status' => 'error',
                    'message' => 'Precio o stock mínimo inválido (usa número ≥ 0).',
                ];
                continue;
            }

            $medicamento = $this->parseBool($data['medicamento'] ?? '', false);
            $activo = $this->parseBool($data['activo'] ?? '', true);
            $descripcion = ($data['descripcion'] ?? '') !== ''
                ? mb_substr($data['descripcion'], 0, 20000)
                : null;
            $codigoBarras = ($data['codigo_barras'] ?? '') !== ''
                ? mb_substr($data['codigo_barras'], 0, 64)
                : null;

            try {
                DB::transaction(function () use (
                    $nombre,
                    $unidad,
                    $medicamento,
                    $activo,
                    $categoriaId,
                    $sku,
                    $codigoBarras,
                    $precioVenta,
                    $precioCompra,
                    $stockMinimo,
                    $descripcion,
                    $userId,
                ): void {
                    Producto::query()->create([
                        'categoria_id' => $categoriaId,
                        'nombre' => mb_substr($nombre, 0, 255),
                        'slug' => $this->generarSlugUnico($nombre),
                        'descripcion' => $descripcion,
                        'sku' => $sku,
                        'codigo_barras' => $codigoBarras,
                        'unidad' => $unidad,
                        'precio_venta' => $precioVenta,
                        'precio_compra' => $precioCompra,
                        'stock_minimo' => $stockMinimo,
                        'medicamento' => $medicamento,
                        'activo' => $activo,
                        'created_by_id' => $userId,
                        'updated_by_id' => $userId,
                    ]);
                });

                $imported++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombre,
                    'status' => 'ok',
                    'message' => 'Producto creado.',
                ];
            } catch (Throwable $e) {
                report($e);
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombre,
                    'status' => 'error',
                    'message' => 'Error al guardar: '.$e->getMessage(),
                ];
            }
        }

        $spreadsheet->disconnectWorksheets();

        if ($imported === 0 && $failed === 0 && $skipped === 0) {
            return [
                'ok' => false,
                'imported' => 0,
                'failed' => 0,
                'skipped' => 0,
                'rows' => [],
                'error' => 'El archivo no tiene filas de productos para importar.',
            ];
        }

        return [
            'ok' => true,
            'imported' => $imported,
            'failed' => $failed,
            'skipped' => $skipped,
            'rows' => $results,
        ];
    }

    private function normalizeHeader(string $header): string
    {
        $h = mb_strtolower(trim($header));
        $h = preg_replace('/^\xEF\xBB\xBF/', '', $h) ?? $h;
        $h = str_replace(['*', ' '], ['', '_'], $h);
        $h = preg_replace('/_+/', '_', $h) ?? $h;

        $aliases = [
            'nombre_producto' => 'nombre',
            'unidad_de_medida' => 'unidad',
            'codigo_de_barras' => 'codigo_barras',
            'categoría' => 'categoria',
            'categoria_nombre' => 'categoria',
        ];

        return $aliases[$h] ?? $h;
    }

    /**
     * @param  list<mixed>  $cells
     */
    private function rowIsEmpty(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) ($cell ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function isExampleRow(string $nombre): bool
    {
        return str_starts_with(mb_strtolower(trim($nombre)), 'ejemplo ');
    }

    private function parseBool(string $value, bool $default): bool
    {
        $v = mb_strtolower(trim($value));
        if ($v === '') {
            return $default;
        }

        if (in_array($v, ['1', 'si', 'sí', 'yes', 'true', 'verdadero', 'x'], true)) {
            return true;
        }

        if (in_array($v, ['0', 'no', 'false', 'falso'], true)) {
            return false;
        }

        return $default;
    }

    /**
     * @return float|null|false null = vacío; false = inválido
     */
    private function parseDecimal(string $value): float|null|false
    {
        $v = trim($value);
        if ($v === '') {
            return null;
        }

        $v = str_replace([' ', ','], ['', '.'], $v);
        if (! is_numeric($v)) {
            return false;
        }

        $n = (float) $v;
        if ($n < 0) {
            return false;
        }

        return $n;
    }

    private function generarSlugUnico(string $nombre): ?string
    {
        $base = Str::slug($nombre);
        if ($base === '') {
            return null;
        }

        $base = mb_substr($base, 0, 150);
        $slug = $base;
        $i = 0;

        while (Producto::query()->where('slug', $slug)->exists()) {
            $i++;
            $slug = mb_substr($base.'-'.$i, 0, 160);
        }

        return $slug;
    }
}
