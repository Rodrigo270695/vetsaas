<?php

namespace App\Services\Clinica;

use App\Models\Propietario;
use App\Support\Plan\PlanLimits;
use App\Support\PropietarioTipoDocumento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

final class PropietarioImportService
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
            return $this->fail('El archivo debe ser .xlsx');
        }

        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            return $this->fail('No se pudo leer el archivo.');
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (Throwable $e) {
            report($e);

            return $this->fail('No se pudo abrir el Excel. Verifica que no esté dañado.');
        }

        $sheet = $spreadsheet->getSheetByName('Propietarios') ?? $spreadsheet->getSheet(0);
        $rawRows = $sheet->toArray(null, true, true, false);

        $headerIndex = null;
        $headers = [];
        foreach ($rawRows as $i => $row) {
            $normalized = array_map(fn ($cell) => $this->normalizeHeader((string) ($cell ?? '')), $row);
            if (in_array('nombres', $normalized, true)) {
                $headerIndex = $i;
                $headers = $normalized;
                break;
            }
        }

        if ($headerIndex === null) {
            $spreadsheet->disconnectWorksheets();

            return $this->fail('No se encontró la fila de encabezados (nombres*, …) en la hoja Propietarios.');
        }

        $allowedTipos = array_fill_keys(PropietarioTipoDocumento::VALUES, true);
        $userId = Auth::id();
        $results = [];
        $imported = 0;
        $failed = 0;
        $skipped = 0;
        $processed = 0;
        /** @var array<string, int> */
        $docsInFile = [];

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

            $nombres = $data['nombres'] ?? '';
            if ($nombres === '' || $this->isExampleRow($nombres)) {
                $skipped++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombres !== '' ? $nombres : '—',
                    'status' => 'skipped',
                    'message' => $nombres === '' ? 'Fila vacía (sin nombres).' : 'Fila de ejemplo omitida.',
                ];
                continue;
            }

            $processed++;
            if ($processed > self::MAX_ROWS) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombres,
                    'status' => 'error',
                    'message' => 'Se superó el máximo de '.self::MAX_ROWS.' filas por archivo.',
                ];
                continue;
            }

            if (PlanLimits::wouldExceed('max_propietarios', adding: 1)) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombres,
                    'status' => 'error',
                    'message' => PlanLimits::message('max_propietarios'),
                ];
                continue;
            }

            $tipo = strtoupper(trim($data['tipo_documento'] ?? ''));
            $tipo = $tipo === '' ? null : $tipo;
            if ($tipo !== null && ! isset($allowedTipos[$tipo])) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombres,
                    'status' => 'error',
                    'message' => "Tipo de documento «{$tipo}» no válido.",
                ];
                continue;
            }

            $numero = trim($data['numero_documento'] ?? '');
            if ($numero === '') {
                $numero = null;
            } elseif ($tipo === 'DNI' || $tipo === 'RUC') {
                $numero = preg_replace('/\D+/', '', $numero) ?: null;
            }

            if ($numero !== null) {
                $docKey = ($tipo ?? '').'|'.$numero;
                if (isset($docsInFile[$docKey])) {
                    $failed++;
                    $results[] = [
                        'row' => $excelRow,
                        'nombre' => $nombres,
                        'status' => 'error',
                        'message' => "Documento duplicado en el archivo (fila {$docsInFile[$docKey]}).",
                    ];
                    continue;
                }
                $exists = Propietario::query()
                    ->whereRaw('COALESCE(UPPER(tipo_documento), \'\') = ?', [$tipo ?? ''])
                    ->where('numero_documento', $numero)
                    ->exists();
                if ($exists) {
                    $failed++;
                    $results[] = [
                        'row' => $excelRow,
                        'nombre' => $nombres,
                        'status' => 'error',
                        'message' => 'Ya existe un propietario con ese documento.',
                    ];
                    continue;
                }
                $docsInFile[$docKey] = $excelRow;
            }

            $email = trim($data['email'] ?? '');
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombres,
                    'status' => 'error',
                    'message' => 'Email inválido.',
                ];
                continue;
            }

            $activo = $this->parseBool($data['activo'] ?? '', true);

            try {
                DB::transaction(function () use ($data, $nombres, $tipo, $numero, $email, $activo, $userId): void {
                    Propietario::query()->create([
                        'nombres' => mb_substr($nombres, 0, 150),
                        'apellidos' => $this->nullableStr($data['apellidos'] ?? '', 150),
                        'razon_social' => $this->nullableStr($data['razon_social'] ?? '', 200),
                        'tipo_documento' => $tipo,
                        'numero_documento' => $numero,
                        'email' => $email !== '' ? mb_substr($email, 0, 150) : null,
                        'telefono' => $this->nullableStr($data['telefono'] ?? '', 20),
                        'telefono_alt' => $this->nullableStr($data['telefono_alt'] ?? '', 20),
                        'direccion' => $this->nullableStr($data['direccion'] ?? '', 255),
                        'notas' => $this->nullableStr($data['notas'] ?? '', 5000),
                        'activo' => $activo,
                        'created_by_id' => $userId,
                        'updated_by_id' => $userId,
                    ]);
                });
                $imported++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombres,
                    'status' => 'ok',
                    'message' => 'Propietario creado.',
                ];
            } catch (Throwable $e) {
                report($e);
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombres,
                    'status' => 'error',
                    'message' => 'Error al guardar: '.$e->getMessage(),
                ];
            }
        }

        $spreadsheet->disconnectWorksheets();

        if ($imported === 0 && $failed === 0 && $skipped === 0) {
            return $this->fail('El archivo no tiene filas de propietarios para importar.');
        }

        return [
            'ok' => true,
            'imported' => $imported,
            'failed' => $failed,
            'skipped' => $skipped,
            'rows' => $results,
        ];
    }

    /**
     * @return array{ok: false, imported: int, failed: int, skipped: int, rows: list<never>, error: string}
     */
    private function fail(string $error): array
    {
        return [
            'ok' => false,
            'imported' => 0,
            'failed' => 0,
            'skipped' => 0,
            'rows' => [],
            'error' => $error,
        ];
    }

    private function normalizeHeader(string $header): string
    {
        $h = mb_strtolower(trim($header));
        $h = preg_replace('/^\xEF\xBB\xBF/', '', $h) ?? $h;
        $h = str_replace(['*', ' '], ['', '_'], $h);
        $h = preg_replace('/_+/', '_', $h) ?? $h;

        return match ($h) {
            'nombre', 'nombre_completo' => 'nombres',
            'doc', 'documento', 'nro_documento', 'numero_doc' => 'numero_documento',
            'tipo_doc', 'tipo' => 'tipo_documento',
            'tel', 'celular' => 'telefono',
            default => $h,
        };
    }

    /** @param  list<mixed>  $cells */
    private function rowIsEmpty(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) ($cell ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function isExampleRow(string $nombres): bool
    {
        return str_starts_with(mb_strtolower(trim($nombres)), 'ejemplo');
    }

    private function parseBool(string $value, bool $default): bool
    {
        $v = mb_strtolower(trim($value));
        if ($v === '') {
            return $default;
        }
        if (in_array($v, ['si', 'sí', 'yes', 'true', '1', 'activo', 'activa'], true)) {
            return true;
        }
        if (in_array($v, ['no', 'false', '0', 'inactivo', 'inactiva'], true)) {
            return false;
        }

        return $default;
    }

    private function nullableStr(string $value, int $max): ?string
    {
        $v = trim($value);

        return $v === '' ? null : mb_substr($v, 0, $max);
    }
}
