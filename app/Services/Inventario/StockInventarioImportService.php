<?php

namespace App\Services\Inventario;

use App\Models\Producto;
use App\Models\Sede;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

final class StockInventarioImportService
{
    public const MAX_ROWS = 500;

    public function __construct(
        private readonly InventarioLoteService $lotes,
    ) {}

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

        $sheet = $spreadsheet->getSheetByName('Stock') ?? $spreadsheet->getSheet(0);
        $rawRows = $sheet->toArray(null, true, true, false);

        $headerIndex = null;
        $headers = [];
        foreach ($rawRows as $i => $row) {
            $normalized = array_map(fn ($cell) => $this->normalizeHeader((string) ($cell ?? '')), $row);
            if (in_array('sede', $normalized, true) && in_array('cantidad', $normalized, true)) {
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
                'error' => 'No se encontró la fila de encabezados (sede*, cantidad*, …) en la hoja Stock.',
            ];
        }

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
            $nombre = mb_strtolower(trim((string) $sede->nombre));
            $valor = $nombre.' · '.$codigo;
            if ($codigo !== '') {
                $sedesByCodigo[$codigo] = $id;
            }
            if ($nombre !== '') {
                $sedesByNombre[$nombre] = $id;
            }
            $sedesByValor[$valor] = $id;
            $sedesByValor[$nombre.' - '.$codigo] = $id;
        }

        $userId = Auth::id() !== null ? (string) Auth::id() : null;
        $results = [];
        $imported = 0;
        $failed = 0;
        $skipped = 0;
        $processedDataRows = 0;
        /** @var array<string, true> */
        $seenKeys = [];

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

            $sedeRaw = $data['sede'] ?? '';
            $sku = $data['sku'] ?? '';
            $nombre = $data['nombre'] ?? '';
            $codigoBarras = $data['codigo_barras'] ?? ($data['codigobarras'] ?? '');
            $cantidadRaw = $data['cantidad'] ?? '';
            $label = $sku !== '' ? $sku : ($nombre !== '' ? $nombre : ($codigoBarras !== '' ? $codigoBarras : '—'));

            if ($this->isExampleRow($sku, $nombre)) {
                $skipped++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $label,
                    'status' => 'skipped',
                    'message' => 'Fila de ejemplo omitida.',
                ];
                continue;
            }

            $processedDataRows++;
            if ($processedDataRows > self::MAX_ROWS) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $label,
                    'status' => 'error',
                    'message' => 'Se superó el máximo de '.self::MAX_ROWS.' filas por archivo.',
                ];
                continue;
            }

            if ($sedeRaw === '') {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $label,
                    'status' => 'error',
                    'message' => 'La sede es obligatoria.',
                ];
                continue;
            }

            $sedeId = $this->resolveSedeId($sedeRaw, $sedesByCodigo, $sedesByNombre, $sedesByValor);
            if ($sedeId === null) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $label,
                    'status' => 'error',
                    'message' => 'Sede no encontrada o inactiva: «'.$sedeRaw.'».',
                ];
                continue;
            }

            if ($sku === '' && $nombre === '' && $codigoBarras === '') {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $label,
                    'status' => 'error',
                    'message' => 'Indica sku, nombre o codigo_barras para localizar el producto.',
                ];
                continue;
            }

            $producto = $this->resolveProducto($sku, $nombre, $codigoBarras);
            if ($producto === null) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $label,
                    'status' => 'error',
                    'message' => 'Producto no encontrado en el catálogo.',
                ];
                continue;
            }

            if ($cantidadRaw === '' || ! is_numeric(str_replace(',', '.', $cantidadRaw))) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => (string) $producto->nombre,
                    'status' => 'error',
                    'message' => 'Cantidad inválida (debe ser un número ≥ 0).',
                ];
                continue;
            }

            $cantidad = round((float) str_replace(',', '.', $cantidadRaw), 3);
            if ($cantidad < 0) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => (string) $producto->nombre,
                    'status' => 'error',
                    'message' => 'La cantidad no puede ser negativa.',
                ];
                continue;
            }

            $dupKey = $producto->id.'|'.$sedeId;
            if (isset($seenKeys[$dupKey])) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => (string) $producto->nombre,
                    'status' => 'error',
                    'message' => 'Producto + sede duplicados en el archivo.',
                ];
                continue;
            }
            $seenKeys[$dupKey] = true;

            try {
                $this->applyStock((string) $producto->id, $sedeId, $cantidad, $userId);
                $imported++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => (string) $producto->nombre,
                    'status' => 'ok',
                    'message' => 'Stock actualizado a '.$cantidad.'.',
                ];
            } catch (ValidationException $e) {
                $failed++;
                $msg = collect($e->errors())->flatten()->first() ?? 'No se pudo actualizar el stock.';
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => (string) $producto->nombre,
                    'status' => 'error',
                    'message' => (string) $msg,
                ];
            } catch (Throwable $e) {
                report($e);
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => (string) $producto->nombre,
                    'status' => 'error',
                    'message' => 'Error al guardar: '.$e->getMessage(),
                ];
            }
        }

        $spreadsheet->disconnectWorksheets();

        if ($processedDataRows === 0 && $imported === 0 && $failed === 0) {
            return [
                'ok' => false,
                'imported' => 0,
                'failed' => 0,
                'skipped' => $skipped,
                'rows' => $results,
                'error' => 'No hay filas de datos para importar.',
            ];
        }

        return [
            'ok' => $failed === 0,
            'imported' => $imported,
            'failed' => $failed,
            'skipped' => $skipped,
            'rows' => $results,
        ];
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

        // Acepta "nombre · codigo" o "nombre - codigo" aunque el espaciado varíe.
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

    private function resolveProducto(string $sku, string $nombre, string $codigoBarras): ?Producto
    {
        if ($sku !== '') {
            $bySku = Producto::query()
                ->whereRaw('LOWER(sku) = ?', [mb_strtolower($sku)])
                ->first();
            if ($bySku !== null) {
                return $bySku;
            }
        }

        if ($codigoBarras !== '') {
            $byBarcode = Producto::query()
                ->whereRaw('LOWER(codigo_barras) = ?', [mb_strtolower($codigoBarras)])
                ->first();
            if ($byBarcode !== null) {
                return $byBarcode;
            }
        }

        if ($nombre !== '') {
            return Producto::query()
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
                ->first();
        }

        return null;
    }

    private function applyStock(string $productoId, string $sedeId, float $cantidad, ?string $userId): void
    {
        $this->lotes->ajustarACantidad(
            $productoId,
            $sedeId,
            (string) $cantidad,
            'Importación masiva de stock',
            $userId,
        );
    }

    private function normalizeHeader(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\*+$/u', '', $value) ?? $value;
        $value = str_replace([' ', '-'], '_', $value);
        $value = preg_replace('/_+/', '_', $value) ?? $value;

        return match ($value) {
            'codigo_de_barras', 'cod_barras', 'barcode' => 'codigo_barras',
            'codigo_sede', 'sede_codigo' => 'sede',
            'qty', 'cantidad_stock', 'stock' => 'cantidad',
            default => $value,
        };
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

    private function isExampleRow(string $sku, string $nombre): bool
    {
        $haystack = mb_strtolower($sku.' '.$nombre);

        return str_contains($haystack, 'ejemplo')
            || str_contains($haystack, 'example')
            || str_contains($haystack, 'fila de ejemplo');
    }
}
