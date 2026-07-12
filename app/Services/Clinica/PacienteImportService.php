<?php

namespace App\Services\Clinica;

use App\Models\Paciente;
use App\Models\Propietario;
use App\Support\Plan\PlanLimits;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

final class PacienteImportService
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

        $sheet = $spreadsheet->getSheetByName('Pacientes') ?? $spreadsheet->getSheet(0);
        $rawRows = $sheet->toArray(null, true, true, false);

        $headerIndex = null;
        $headers = [];
        foreach ($rawRows as $i => $row) {
            $normalized = array_map(fn ($cell) => $this->normalizeHeader((string) ($cell ?? '')), $row);
            if (in_array('nombre', $normalized, true) && (
                in_array('propietario_documento', $normalized, true)
                || in_array('propietario', $normalized, true)
            )) {
                $headerIndex = $i;
                $headers = $normalized;
                break;
            }
        }

        if ($headerIndex === null) {
            $spreadsheet->disconnectWorksheets();

            return $this->fail('No se encontró la fila de encabezados (nombre*, propietario_documento*, …).');
        }

        $fechaColIndex = null;
        foreach ($headers as $colIndex => $header) {
            if ($header === 'fecha_nacimiento') {
                $fechaColIndex = $colIndex;
                break;
            }
        }

        $propietarios = Propietario::query()
            ->whereNotNull('numero_documento')
            ->where('numero_documento', '!=', '')
            ->get(['id', 'tipo_documento', 'numero_documento']);

        /** @var array<string, string> */
        $byNumero = [];
        /** @var array<string, string> */
        $byTipoNumero = [];
        foreach ($propietarios as $p) {
            $num = mb_strtolower(trim((string) $p->numero_documento));
            $tipo = mb_strtoupper(trim((string) ($p->tipo_documento ?? '')));
            $byNumero[$num] = (string) $p->id;
            $byTipoNumero[mb_strtolower($tipo.' '.$num)] = (string) $p->id;
            $byTipoNumero[mb_strtolower($tipo.'|'.$num)] = (string) $p->id;
        }

        $userId = Auth::id();
        $results = [];
        $imported = 0;
        $failed = 0;
        $skipped = 0;
        $processed = 0;

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
                    'message' => $nombre === '' ? 'Fila vacía (sin nombre).' : 'Fila de ejemplo omitida.',
                ];
                continue;
            }

            $processed++;
            if ($processed > self::MAX_ROWS) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombre,
                    'status' => 'error',
                    'message' => 'Se superó el máximo de '.self::MAX_ROWS.' filas por archivo.',
                ];
                continue;
            }

            if (PlanLimits::wouldExceed('max_pacientes', adding: 1)) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombre,
                    'status' => 'error',
                    'message' => PlanLimits::message('max_pacientes'),
                ];
                continue;
            }

            $propRaw = $data['propietario_documento'] ?? ($data['propietario'] ?? '');
            if ($propRaw === '') {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombre,
                    'status' => 'error',
                    'message' => 'propietario_documento es obligatorio.',
                ];
                continue;
            }

            $propietarioId = $this->resolvePropietarioId($propRaw, $byNumero, $byTipoNumero);
            if ($propietarioId === null) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombre,
                    'status' => 'error',
                    'message' => 'Propietario no encontrado: «'.$propRaw.'».',
                ];
                continue;
            }

            $sexo = strtoupper(trim($data['sexo'] ?? ''));
            if ($sexo === '') {
                $sexo = null;
            } elseif (! in_array($sexo, ['M', 'H', 'U'], true)) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombre,
                    'status' => 'error',
                    'message' => 'Sexo inválido (usa M, H o U).',
                ];
                continue;
            }

            $pesoRaw = $data['peso_kg'] ?? '';
            $peso = null;
            if ($pesoRaw !== '') {
                $peso = $this->parseDecimal($pesoRaw);
                if ($peso === false || $peso === null || $peso > 999.99) {
                    $failed++;
                    $results[] = [
                        'row' => $excelRow,
                        'nombre' => $nombre,
                        'status' => 'error',
                        'message' => 'peso_kg inválido.',
                    ];
                    continue;
                }
            }

            $fechaRaw = $fechaColIndex !== null
                ? ($cells[$fechaColIndex] ?? null)
                : ($data['fecha_nacimiento'] ?? null);
            $fecha = null;
            if (! $this->isBlankDateValue($fechaRaw)) {
                $fecha = $this->parseDate($fechaRaw);
                if ($fecha === null) {
                    $failed++;
                    $results[] = [
                        'row' => $excelRow,
                        'nombre' => $nombre,
                        'status' => 'error',
                        'message' => 'fecha_nacimiento inválida. Usa DD/MM/AAAA (ej. 15/01/2022).',
                    ];
                    continue;
                }
            }

            $esterilizadoRaw = $data['esterilizado'] ?? '';
            $esterilizado = null;
            if ($esterilizadoRaw !== '') {
                $esterilizado = $this->parseBool($esterilizadoRaw, false);
            }

            $activo = $this->parseBool($data['activo'] ?? '', true);

            try {
                DB::transaction(function () use (
                    $nombre,
                    $propietarioId,
                    $data,
                    $sexo,
                    $peso,
                    $fecha,
                    $esterilizado,
                    $activo,
                    $userId,
                ): void {
                    Paciente::query()->create([
                        'propietario_id' => $propietarioId,
                        'nombre' => mb_substr($nombre, 0, 120),
                        'especie' => $this->nullableStr($data['especie'] ?? '', 80),
                        'raza' => $this->nullableStr($data['raza'] ?? '', 120),
                        'sexo' => $sexo,
                        'fecha_nacimiento' => $fecha,
                        'peso_kg' => $peso,
                        'microchip' => $this->nullableStr($data['microchip'] ?? '', 64),
                        'color' => $this->nullableStr($data['color'] ?? '', 80),
                        'esterilizado' => $esterilizado,
                        'notas' => $this->nullableStr($data['notas'] ?? '', 5000),
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
                    'message' => 'Paciente creado.',
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
            return $this->fail('El archivo no tiene filas de pacientes para importar.');
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
     * @param  array<string, string>  $byNumero
     * @param  array<string, string>  $byTipoNumero
     */
    private function resolvePropietarioId(string $raw, array $byNumero, array $byTipoNumero): ?string
    {
        $key = mb_strtolower(trim($raw));
        if ($key === '') {
            return null;
        }

        if (isset($byTipoNumero[$key])) {
            return $byTipoNumero[$key];
        }

        if (preg_match('/^(dni|ruc|ce|pas|otr)\s*[|:\-]?\s*(.+)$/iu', $key, $m) === 1) {
            $combo = mb_strtoupper($m[1]).' '.trim($m[2]);
            $comboKey = mb_strtolower($combo);
            if (isset($byTipoNumero[$comboKey])) {
                return $byTipoNumero[$comboKey];
            }
            $num = preg_replace('/\D+/', '', $m[2]) ?: trim($m[2]);

            return $byNumero[mb_strtolower($num)] ?? null;
        }

        $soloNum = preg_replace('/\D+/', '', $key) ?: $key;

        return $byNumero[mb_strtolower($soloNum)] ?? $byNumero[$key] ?? null;
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
        // Quitar notas de formato en el encabezado: «fecha_nacimiento (DD/MM/AAAA)»
        $h = preg_replace('/\s*\([^)]*\)\s*/', '', $h) ?? $h;
        $h = str_replace(['*', ' '], ['', '_'], $h);
        $h = preg_replace('/_+/', '_', $h) ?? $h;
        $h = trim($h, '_');

        return match ($h) {
            'propietario', 'documento_propietario', 'doc_propietario', 'titular' => 'propietario_documento',
            'fecha_nac', 'nacimiento', 'fecha_nacimiento_dd_mm_aaaa' => 'fecha_nacimiento',
            'peso', 'peso_kg' => 'peso_kg',
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

    private function isExampleRow(string $nombre): bool
    {
        return str_starts_with(mb_strtolower(trim($nombre)), 'ejemplo');
    }

    private function parseBool(string $value, bool $default): bool
    {
        $v = mb_strtolower(trim($value));
        if ($v === '') {
            return $default;
        }
        if (in_array($v, ['si', 'sí', 'yes', 'true', '1'], true)) {
            return true;
        }
        if (in_array($v, ['no', 'false', '0'], true)) {
            return false;
        }

        return $default;
    }

    private function parseDecimal(string $value): float|null|false
    {
        $v = trim(str_replace([' ', ','], ['', '.'], $value));
        if ($v === '') {
            return null;
        }
        if (! is_numeric($v)) {
            return false;
        }
        $n = (float) $v;

        return $n < 0 ? false : $n;
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

    private function parseDate(mixed $value): ?string
    {
        if ($this->isBlankDateValue($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^\d+(\.\d+)?$/', trim($value)) === 1)) {
            $serial = (float) $value;
            if ($serial >= 18000 && $serial <= 73000) {
                try {
                    return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($serial)->format('Y-m-d');
                } catch (Throwable) {
                }
            }
        }

        $v = trim((string) $value);
        if (preg_match('/^(\d{1,4}[\/\-.]\d{1,2}[\/\-.]\d{1,4})/', $v, $onlyDate) === 1) {
            $v = $onlyDate[1];
        }

        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $v, $m) === 1) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];
            if ($month > 12 && $day <= 12) {
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

        return null;
    }

    private function nullableStr(string $value, int $max): ?string
    {
        $v = trim($value);

        return $v === '' ? null : mb_substr($v, 0, $max);
    }
}
