<?php

namespace App\Services\Inventario;

use App\Models\CategoriaProducto;
use App\Models\Producto;
use App\Models\Sede;
use App\Services\Inventario\InventarioLoteService;
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

        $fechaColIndex = null;
        foreach ($headers as $colIndex => $header) {
            if ($header === 'fecha_vencimiento' || $header === 'vencimiento') {
                $fechaColIndex = $colIndex;
                break;
            }
        }

        $allowedUnidades = array_fill_keys(
            array_map('strtoupper', UnidadMedidaOpciones::allowedCodigos()),
            true,
        );

        $categorias = CategoriaProducto::query()
            ->where('activo', true)
            ->get(['id', 'nombre']);

        $categoriasByNombre = $categorias
            ->mapWithKeys(fn (CategoriaProducto $c) => [mb_strtolower(trim((string) $c->nombre)) => (string) $c->id])
            ->all();

        $categoriasById = $categorias
            ->mapWithKeys(fn (CategoriaProducto $c) => [mb_strtolower((string) $c->id) => (string) $c->id])
            ->all();

        $tenantId = Auth::user()?->tenant_id;
        $sedesQuery = Sede::query()
            ->where('activa', true)
            ->whereNull('deleted_at');
        if ($tenantId !== null) {
            $sedesQuery->where('tenant_id', $tenantId);
        }
        $sedes = $sedesQuery->get(['id', 'nombre', 'codigo']);
        $sedesByCodigo = [];
        $sedesByNombre = [];
        $sedesByValor = [];
        foreach ($sedes as $sede) {
            $id = (string) $sede->id;
            $codigo = mb_strtolower(trim((string) $sede->codigo));
            $nombreSede = mb_strtolower(trim((string) $sede->nombre));
            if ($codigo !== '') {
                $sedesByCodigo[$codigo] = $id;
            }
            if ($nombreSede !== '') {
                $sedesByNombre[$nombreSede] = $id;
            }
            $sedesByValor[$nombreSede.' · '.$codigo] = $id;
            $sedesByValor[$nombreSede.' - '.$codigo] = $id;
        }

        $loteService = app(InventarioLoteService::class);
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

            $unidadRaw = $data['unidad'] ?? '';
            $unidad = $this->extractCatalogCode($unidadRaw);
            if ($unidad === '') {
                $unidad = 'UN';
            }
            $unidad = substr(strtoupper($unidad), 0, 20);

            if (! isset($allowedUnidades[$unidad])) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombre,
                    'status' => 'error',
                    'message' => "Unidad «{$unidadRaw}» no válida. Usa la lista de Catalogos → UNIDADES.",
                ];
                continue;
            }

            $categoriaRaw = $data['categoria'] ?? '';
            $categoriaId = null;
            if ($categoriaRaw !== '') {
                $categoriaNombre = $this->extractCatalogLabel($categoriaRaw);
                $key = mb_strtolower($categoriaNombre);
                if (! isset($categoriasByNombre[$key]) && isset($categoriasById[mb_strtolower($categoriaNombre)])) {
                    $categoriaId = $categoriasById[mb_strtolower($categoriaNombre)];
                } elseif (isset($categoriasByNombre[$key])) {
                    $categoriaId = $categoriasByNombre[$key];
                } else {
                    $failed++;
                    $results[] = [
                        'row' => $excelRow,
                        'nombre' => $nombre,
                        'status' => 'error',
                        'message' => "Categoría «{$categoriaRaw}» no encontrada. Usa la lista de Catalogos → CATEGORIAS.",
                    ];
                    continue;
                }
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

            $sedeRaw = $data['sede'] ?? '';
            $cantidadInicialRaw = $data['cantidad_inicial'] ?? ($data['cantidad'] ?? '');
            $numeroLote = ($data['numero_lote'] ?? ($data['lote'] ?? '')) !== ''
                ? mb_substr((string) ($data['numero_lote'] ?? $data['lote']), 0, 128)
                : null;
            // Valor crudo: Excel suele devolver fechas como serial numérico, no como texto.
            $fechaVencimientoRaw = $fechaColIndex !== null
                ? ($cells[$fechaColIndex] ?? null)
                : ($data['fecha_vencimiento'] ?? ($data['vencimiento'] ?? null));

            $sedeId = null;
            $cantidadInicial = null;
            if ($sedeRaw !== '' || $cantidadInicialRaw !== '') {
                if ($sedeRaw === '' || $cantidadInicialRaw === '') {
                    $failed++;
                    $results[] = [
                        'row' => $excelRow,
                        'nombre' => $nombre,
                        'status' => 'error',
                        'message' => 'Stock inicial requiere sede y cantidad_inicial juntos.',
                    ];
                    continue;
                }

                $sedeId = $this->resolveSedeId($sedeRaw, $sedesByCodigo, $sedesByNombre, $sedesByValor);
                if ($sedeId === null) {
                    $failed++;
                    $results[] = [
                        'row' => $excelRow,
                        'nombre' => $nombre,
                        'status' => 'error',
                        'message' => 'Sede no encontrada o inactiva: «'.$sedeRaw.'».',
                    ];
                    continue;
                }

                $cantidadInicial = $this->parseDecimal($cantidadInicialRaw);
                if ($cantidadInicial === false || $cantidadInicial === null || (float) $cantidadInicial <= 0) {
                    $failed++;
                    $results[] = [
                        'row' => $excelRow,
                        'nombre' => $nombre,
                        'status' => 'error',
                        'message' => 'cantidad_inicial debe ser un número mayor que 0.',
                    ];
                    continue;
                }
            }

            $fechaVencimiento = null;
            if (! $this->isBlankDateValue($fechaVencimientoRaw)) {
                $fechaVencimiento = $this->parseDate($fechaVencimientoRaw);
                if ($fechaVencimiento === null) {
                    $failed++;
                    $results[] = [
                        'row' => $excelRow,
                        'nombre' => $nombre,
                        'status' => 'error',
                        'message' => 'fecha_vencimiento inválida. Usa DD/MM/AAAA (ej. 31/12/2027).',
                    ];
                    continue;
                }
            }

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
                    $sedeId,
                    $cantidadInicial,
                    $numeroLote,
                    $fechaVencimiento,
                    $loteService,
                ): void {
                    $producto = Producto::query()->create([
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

                    if ($sedeId !== null && $cantidadInicial !== null) {
                        $loteService->registrarEntrada(
                            (string) $producto->id,
                            $sedeId,
                            (string) $cantidadInicial,
                            $numeroLote,
                            $fechaVencimiento,
                            'Stock inicial (importación masiva)',
                            $userId !== null ? (string) $userId : null,
                        );
                    }
                });

                $imported++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombre,
                    'status' => 'ok',
                    'message' => $sedeId !== null
                        ? 'Producto creado con stock inicial.'
                        : 'Producto creado.',
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
            'lote' => 'numero_lote',
            'vencimiento' => 'fecha_vencimiento',
            'cantidad' => 'cantidad_inicial',
            'stock_inicial' => 'cantidad_inicial',
        ];

        return $aliases[$h] ?? $h;
    }

    /**
     * @param  array<string, string>  $byCodigo
     * @param  array<string, string>  $byNombre
     * @param  array<string, string>  $byValor
     */
    private function resolveSedeId(string $raw, array $byCodigo, array $byNombre, array $byValor): ?string
    {
        $key = mb_strtolower(trim($raw));
        if ($key === '') {
            return null;
        }

        if (isset($byValor[$key])) {
            return $byValor[$key];
        }
        if (isset($byCodigo[$key])) {
            return $byCodigo[$key];
        }
        if (isset($byNombre[$key])) {
            return $byNombre[$key];
        }

        if (preg_match('/^(.+?)\s*[·\-–]\s*(.+)$/u', $key, $m) === 1) {
            $nombrePart = trim($m[1]);
            $codigoPart = trim($m[2]);
            $combo = $nombrePart.' · '.$codigoPart;
            if (isset($byValor[$combo])) {
                return $byValor[$combo];
            }
            if (isset($byCodigo[$codigoPart])) {
                return $byCodigo[$codigoPart];
            }
        }

        return null;
    }

    private function isBlankDateValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        return false;
    }

    /**
     * Normaliza fechas desde Excel (texto DD/MM/AAAA, YYYY-MM-DD o serial de Excel).
     */
    private function parseDate(mixed $value): ?string
    {
        if ($this->isBlankDateValue($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        // Serial de Excel (número de días desde 1899-12-30).
        if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^\d+(\.\d+)?$/', trim($value)) === 1)) {
            $serial = (float) $value;
            // Rango razonable ~1950–2100 (seriales ~18000–73000).
            if ($serial >= 18000 && $serial <= 73000) {
                try {
                    return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($serial)->format('Y-m-d');
                } catch (Throwable) {
                    // sigue con parseo textual
                }
            }
        }

        $v = trim((string) $value);
        // Quitar hora si Excel la añadió: "31/12/2027 0:00:00"
        if (preg_match('/^(\d{1,4}[\/\-.]\d{1,2}[\/\-.]\d{1,4})/', $v, $onlyDate) === 1) {
            $v = $onlyDate[1];
        }

        // Preferido: DD/MM/AAAA
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $v, $m) === 1) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];
            if ($day > 12 && $month <= 12) {
                // Claramente día/mes
            } elseif ($month > 12 && $day <= 12) {
                // Intercambiar si viniera MM/DD por error de locale
                [$day, $month] = [$month, $day];
            }
            if (! checkdate($month, $day, $year)) {
                return null;
            }

            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) === 1) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            $day = (int) $m[3];
            if (! checkdate($month, $day, $year)) {
                return null;
            }

            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        // No usar Carbon::parse con números sueltos (genera años inválidos → overflow en Postgres).
        return null;
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

    /**
     * Extrae el código de un «Valor en lista» tipo "CAJA - Caja" o "SI".
     */
    private function extractCatalogCode(string $value): string
    {
        $v = trim($value);
        if ($v === '') {
            return '';
        }

        if (preg_match('/^(.+?)\s+-\s+.+$/u', $v, $m) === 1) {
            return trim($m[1]);
        }

        return $v;
    }

    /**
     * Extrae la etiqueta visible (nombre) de un valor de lista.
     * Para categorías el valor es solo el nombre; si viniera "id - nombre", usa el nombre.
     */
    private function extractCatalogLabel(string $value): string
    {
        $v = trim($value);
        if ($v === '') {
            return '';
        }

        if (preg_match('/^.+?\s+-\s+(.+)$/u', $v, $m) === 1) {
            return trim($m[1]);
        }

        return $v;
    }

    private function parseBool(string $value, bool $default): bool
    {
        $code = mb_strtolower($this->extractCatalogCode($value));
        if ($code === '') {
            return $default;
        }

        if (in_array($code, ['1', 'si', 'sí', 'yes', 'true', 'verdadero', 'x'], true)) {
            return true;
        }

        if (in_array($code, ['0', 'no', 'false', 'falso'], true)) {
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
