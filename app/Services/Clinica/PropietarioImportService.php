<?php

namespace App\Services\Clinica;

use App\Models\Paciente;
use App\Models\Propietario;
use App\Support\Plan\PlanLimits;
use App\Support\PropietarioTipoDocumento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Import unificado: dueño + mascota en la misma fila.
 * Filas con el mismo documento reutilizan el propietario.
 */
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

        $sheet = $spreadsheet->getSheetByName('Importacion')
            ?? $spreadsheet->getSheetByName('Propietarios')
            ?? $spreadsheet->getSheet(0);
        $rawRows = $sheet->toArray(null, true, true, false);

        $headerIndex = null;
        $headers = [];
        foreach ($rawRows as $i => $row) {
            $normalized = array_map(fn ($cell) => $this->normalizeHeader((string) ($cell ?? '')), $row);
            if (in_array('nombres', $normalized, true) && in_array('paciente_nombre', $normalized, true)) {
                $headerIndex = $i;
                $headers = $normalized;
                break;
            }
        }

        if ($headerIndex === null) {
            $spreadsheet->disconnectWorksheets();

            return $this->fail('No se encontró la fila de encabezados (nombres*, paciente_nombre*, …). Descarga la plantilla actualizada.');
        }

        $fechaColIndex = null;
        foreach ($headers as $colIndex => $header) {
            if ($header === 'fecha_nacimiento') {
                $fechaColIndex = $colIndex;
                break;
            }
        }

        $allowedTipos = array_fill_keys(PropietarioTipoDocumento::VALUES, true);
        $userId = Auth::id();
        $results = [];
        $imported = 0;
        $failed = 0;
        $skipped = 0;
        $processed = 0;

        /** @var array<string, string> docKey → propietario_id (creados o reutilizados en este archivo) */
        $ownersInFile = [];

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
            $pacienteNombre = $data['paciente_nombre'] ?? '';

            if (
                ($nombres !== '' && $this->isExampleRow($nombres))
                || ($pacienteNombre !== '' && $this->isExampleRow($pacienteNombre))
            ) {
                $skipped++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $pacienteNombre !== '' ? $pacienteNombre : ($nombres !== '' ? $nombres : '—'),
                    'status' => 'skipped',
                    'message' => 'Fila de ejemplo omitida.',
                ];
                continue;
            }

            if ($nombres === '' && $pacienteNombre === '') {
                $skipped++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => '—',
                    'status' => 'skipped',
                    'message' => 'Fila vacía.',
                ];
                continue;
            }

            $processed++;
            if ($processed > self::MAX_ROWS) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $pacienteNombre !== '' ? $pacienteNombre : $nombres,
                    'status' => 'error',
                    'message' => 'Se superó el máximo de '.self::MAX_ROWS.' filas por archivo.',
                ];
                continue;
            }

            if ($nombres === '') {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $pacienteNombre,
                    'status' => 'error',
                    'message' => 'nombres (dueño) es obligatorio.',
                ];
                continue;
            }

            if ($pacienteNombre === '') {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombres,
                    'status' => 'error',
                    'message' => 'paciente_nombre es obligatorio.',
                ];
                continue;
            }

            $tipo = strtoupper(trim($data['tipo_documento'] ?? ''));
            $tipo = $tipo === '' ? null : $tipo;
            if ($tipo !== null && ! isset($allowedTipos[$tipo])) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $pacienteNombre,
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

            $email = trim($data['email'] ?? '');
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $pacienteNombre,
                    'status' => 'error',
                    'message' => 'Email inválido.',
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
                    'nombre' => $pacienteNombre,
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
                        'nombre' => $pacienteNombre,
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
                        'nombre' => $pacienteNombre,
                        'status' => 'error',
                        'message' => 'fecha_nacimiento inválida. Usa DD/MM/AAAA.',
                    ];
                    continue;
                }
            }

            $esterilizadoRaw = $data['esterilizado'] ?? '';
            $esterilizado = $esterilizadoRaw !== '' ? $this->parseBool($esterilizadoRaw, false) : null;
            $activo = $this->parseBool($data['activo'] ?? '', true);

            $docKey = $numero !== null
                ? ($tipo ?? '').'|'.$numero
                : 'name:'.$this->normalizePersonName(trim($nombres.' '.($data['apellidos'] ?? '')));

            try {
                $message = DB::transaction(function () use (
                    $data,
                    $nombres,
                    $pacienteNombre,
                    $tipo,
                    $numero,
                    $email,
                    $sexo,
                    $peso,
                    $fecha,
                    $esterilizado,
                    $activo,
                    $userId,
                    $docKey,
                    &$ownersInFile,
                ): string {
                    $propietarioId = $ownersInFile[$docKey] ?? null;
                    $ownerCreated = false;

                    if ($propietarioId === null && $numero !== null) {
                        $existing = Propietario::query()
                            ->whereRaw('COALESCE(UPPER(tipo_documento), \'\') = ?', [$tipo ?? ''])
                            ->where('numero_documento', $numero)
                            ->first(['id']);
                        if ($existing !== null) {
                            $propietarioId = (string) $existing->id;
                            $ownersInFile[$docKey] = $propietarioId;
                        }
                    }

                    if ($propietarioId === null) {
                        if (PlanLimits::wouldExceed('max_propietarios', adding: 1)) {
                            throw new \RuntimeException(PlanLimits::message('max_propietarios'));
                        }

                        $owner = Propietario::query()->create([
                            'nombres' => mb_substr($nombres, 0, 150),
                            'apellidos' => $this->nullableStr($data['apellidos'] ?? '', 150),
                            'razon_social' => $this->nullableStr($data['razon_social'] ?? '', 200),
                            'tipo_documento' => $tipo,
                            'numero_documento' => $numero,
                            'email' => $email !== '' ? mb_substr($email, 0, 150) : null,
                            'telefono' => $this->nullableStr($data['telefono'] ?? '', 20),
                            'telefono_alt' => $this->nullableStr($data['telefono_alt'] ?? '', 20),
                            'direccion' => $this->nullableStr($data['direccion'] ?? '', 255),
                            'notas' => $this->nullableStr(
                                $data['notas_propietario'] ?? ($data['notas'] ?? ''),
                                5000,
                            ),
                            'activo' => $activo,
                            'created_by_id' => $userId,
                            'updated_by_id' => $userId,
                        ]);
                        $propietarioId = (string) $owner->id;
                        $ownersInFile[$docKey] = $propietarioId;
                        $ownerCreated = true;
                    }

                    if (PlanLimits::wouldExceed('max_pacientes', adding: 1)) {
                        throw new \RuntimeException(PlanLimits::message('max_pacientes'));
                    }

                    Paciente::query()->create([
                        'propietario_id' => $propietarioId,
                        'nombre' => mb_substr($pacienteNombre, 0, 120),
                        'especie' => $this->nullableStr($data['especie'] ?? '', 80),
                        'raza' => $this->nullableStr($data['raza'] ?? '', 120),
                        'sexo' => $sexo,
                        'fecha_nacimiento' => $fecha,
                        'peso_kg' => $peso,
                        'microchip' => $this->nullableStr($data['microchip'] ?? '', 64),
                        'color' => $this->nullableStr($data['color'] ?? '', 80),
                        'esterilizado' => $esterilizado,
                        'notas' => $this->nullableStr($data['notas_paciente'] ?? '', 5000),
                        'activo' => $activo,
                        'created_by_id' => $userId,
                        'updated_by_id' => $userId,
                    ]);

                    return $ownerCreated
                        ? 'Dueño y mascota creados.'
                        : 'Mascota creada (dueño reutilizado).';
                });

                $imported++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $pacienteNombre.' · '.$nombres,
                    'status' => 'ok',
                    'message' => $message,
                ];
            } catch (Throwable $e) {
                if (! $e instanceof \RuntimeException) {
                    report($e);
                }
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $pacienteNombre.' · '.$nombres,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        $spreadsheet->disconnectWorksheets();

        if ($imported === 0 && $failed === 0 && $skipped === 0) {
            return $this->fail('El archivo no tiene filas para importar.');
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
        $h = preg_replace('/\s*\([^)]*\)\s*/', '', $h) ?? $h;
        $h = str_replace(['*', ' '], ['', '_'], $h);
        $h = preg_replace('/_+/', '_', $h) ?? $h;
        $h = trim($h, '_');

        return match ($h) {
            'nombre', 'nombre_completo' => 'nombres',
            'doc', 'documento', 'nro_documento', 'numero_doc' => 'numero_documento',
            'tipo_doc', 'tipo' => 'tipo_documento',
            'tel', 'celular' => 'telefono',
            'mascota', 'nombre_mascota', 'paciente', 'nombre_paciente' => 'paciente_nombre',
            'notas_dueño', 'notas_dueno', 'notas_titular' => 'notas_propietario',
            'fecha_nac', 'nacimiento' => 'fecha_nacimiento',
            'peso' => 'peso_kg',
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

    private function isExampleRow(string $value): bool
    {
        return str_starts_with(mb_strtolower(trim($value)), 'ejemplo');
    }

    private function normalizePersonName(string $name): string
    {
        $n = mb_strtolower(trim($name));

        return preg_replace('/\s+/u', ' ', $n) ?? $n;
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
        if (is_numeric($value)) {
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value);

                return $dt->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $raw, $m) === 1) {
            $d = (int) $m[1];
            $mo = (int) $m[2];
            $y = (int) $m[3];
            if (! checkdate($mo, $d, $y)) {
                return null;
            }

            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m) === 1) {
            $y = (int) $m[1];
            $mo = (int) $m[2];
            $d = (int) $m[3];
            if (! checkdate($mo, $d, $y)) {
                return null;
            }

            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }

        return null;
    }

    private function nullableStr(string $value, int $max): ?string
    {
        $v = trim($value);

        return $v === '' ? null : mb_substr($v, 0, $max);
    }
}
