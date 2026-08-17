<?php

namespace App\Services\Clinica;

use App\Exports\VacunasAplicadasImportTemplateXlsx;
use App\Models\Paciente;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\User;
use App\Models\VacunaAplicada;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

final class VacunaAplicadaImportService
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

        $sheet = $spreadsheet->getSheetByName('Vacunaciones') ?? $spreadsheet->getSheet(0);
        $rawRows = $sheet->toArray(null, true, true, false);

        $headerIndex = null;
        $headers = [];
        foreach ($rawRows as $i => $row) {
            $normalized = array_map(fn ($cell) => $this->normalizeHeader((string) ($cell ?? '')), $row);
            if (
                in_array('paciente', $normalized, true)
                && in_array('nombre_vacuna', $normalized, true)
                && in_array('categoria', $normalized, true)
            ) {
                $headerIndex = $i;
                $headers = $normalized;
                break;
            }
        }

        if ($headerIndex === null) {
            $spreadsheet->disconnectWorksheets();

            return $this->fail('No se encontró la fila de encabezados (paciente*, nombre_vacuna*, categoria*, …).');
        }

        /** @var array<string, string> valor lista → paciente_id */
        $pacientesByValor = [];
        /** @var array<string, true> */
        $pacientesAmbiguos = [];

        $pacientes = Paciente::query()
            ->where('activo', true)
            ->with(['propietario:id,nombres,apellidos,razon_social,tipo_documento,numero_documento'])
            ->get(['id', 'nombre', 'propietario_id']);

        foreach ($pacientes as $p) {
            $valor = mb_strtolower(VacunasAplicadasImportTemplateXlsx::pacienteListaValor($p));
            if ($valor === '') {
                continue;
            }
            if (isset($pacientesByValor[$valor]) || isset($pacientesAmbiguos[$valor])) {
                unset($pacientesByValor[$valor]);
                $pacientesAmbiguos[$valor] = true;
                continue;
            }
            $pacientesByValor[$valor] = (string) $p->id;
        }

        $tenantId = tenant_id();

        /** @var array<string, string> sku lower → id */
        $productosBySku = Producto::query()
            ->where('activo', true)
            ->where('medicamento', true)
            ->whereNotNull('sku')
            ->get(['id', 'sku'])
            ->mapWithKeys(static function (Producto $pr): array {
                $sku = mb_strtolower(trim((string) $pr->sku));

                return $sku !== '' ? [$sku => (string) $pr->id] : [];
            })
            ->all();

        /** @var array<string, string> */
        $sedesByCodigo = Sede::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('activa', true)
            ->whereNull('deleted_at')
            ->get(['id', 'codigo'])
            ->mapWithKeys(static fn (Sede $s): array => [
                mb_strtolower(trim((string) $s->codigo)) => (string) $s->id,
            ])
            ->all();

        /** @var array<string, string> */
        $vetsByEmail = [];
        /** @var array<string, string> */
        $vetsByName = [];
        /** @var array<string, true> */
        $vetsNameAmbiguo = [];

        $users = User::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get(['id', 'name', 'email']);

        foreach ($users as $u) {
            $email = mb_strtolower(trim((string) ($u->email ?? '')));
            if ($email !== '') {
                $vetsByEmail[$email] = (string) $u->id;
            }
            $nameKey = mb_strtolower(trim((string) $u->name));
            if ($nameKey === '') {
                continue;
            }
            if (isset($vetsByName[$nameKey]) || isset($vetsNameAmbiguo[$nameKey])) {
                unset($vetsByName[$nameKey]);
                $vetsNameAmbiguo[$nameKey] = true;
                continue;
            }
            $vetsByName[$nameKey] = (string) $u->id;
        }

        $userId = Auth::id();
        $tz = (string) config('app.timezone', 'America/Lima');
        $results = [];
        $imported = 0;
        $failed = 0;
        $skipped = 0;
        $processed = 0;

        foreach ($rawRows as $i => $row) {
            if ($i <= $headerIndex) {
                continue;
            }

            $excelRow = $i + 1;
            $data = $this->mapRow($headers, $row);
            if ($this->rowIsEmpty($data)) {
                continue;
            }

            $processed++;
            if ($processed > self::MAX_ROWS) {
                $skipped++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => '—',
                    'status' => 'skipped',
                    'message' => 'Máximo '.self::MAX_ROWS.' filas por archivo.',
                ];
                break;
            }

            $pacienteRaw = trim((string) ($data['paciente'] ?? ''));
            $nombreVacuna = trim((string) ($data['nombre_vacuna'] ?? ''));
            $categoriaRaw = trim((string) ($data['categoria'] ?? ''));
            $aplicadaRaw = $data['aplicada_at'] ?? '';

            $label = $nombreVacuna !== '' ? $nombreVacuna : ($pacienteRaw !== '' ? $pacienteRaw : '—');

            if ($pacienteRaw === '' || str_starts_with(mb_strtolower($pacienteRaw), 'ejemplo')) {
                if ($this->looksLikeExample($data)) {
                    $skipped++;
                    $results[] = [
                        'row' => $excelRow,
                        'nombre' => $label,
                        'status' => 'skipped',
                        'message' => 'Fila de ejemplo omitida.',
                    ];
                    continue;
                }
            }

            if ($pacienteRaw === '') {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $label,
                    'status' => 'error',
                    'message' => 'Falta paciente*.',
                ];
                continue;
            }

            $pacienteKey = mb_strtolower($pacienteRaw);
            if (isset($pacientesAmbiguos[$pacienteKey])) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $label,
                    'status' => 'error',
                    'message' => 'Paciente ambiguo (varias mascotas con el mismo rótulo). Usa la lista de Catalogos.',
                ];
                continue;
            }

            $pacienteId = $pacientesByValor[$pacienteKey] ?? null;
            if ($pacienteId === null) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $label,
                    'status' => 'error',
                    'message' => 'Paciente no encontrado. Elige un valor de Catalogos → MASCOTAS.',
                ];
                continue;
            }

            if ($nombreVacuna === '') {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $label,
                    'status' => 'error',
                    'message' => 'Falta nombre_vacuna*.',
                ];
                continue;
            }

            $categoria = $this->parseCategoria($categoriaRaw);
            if ($categoria === null) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $label,
                    'status' => 'error',
                    'message' => 'categoria* inválida (vacuna | desparasitacion | otro).',
                ];
                continue;
            }

            $aplicadaAt = $this->parseDateTime($aplicadaRaw, $tz);
            if ($aplicadaAt === null) {
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $label,
                    'status' => 'error',
                    'message' => 'aplicada_at* inválida. Usa DD/MM/AAAA o DD/MM/AAAA HH:MM.',
                ];
                continue;
            }

            $proxima = null;
            $proximaRaw = $data['fecha_proxima'] ?? '';
            if (trim((string) $proximaRaw) !== '') {
                $proxima = $this->parseDateOnly($proximaRaw, $tz);
                if ($proxima === null) {
                    $failed++;
                    $results[] = [
                        'row' => $excelRow,
                        'nombre' => $label,
                        'status' => 'error',
                        'message' => 'fecha_proxima inválida. Usa DD/MM/AAAA.',
                    ];
                    continue;
                }
            }

            $dosis = null;
            $dosisRaw = trim((string) ($data['numero_dosis'] ?? ''));
            if ($dosisRaw !== '') {
                if (! ctype_digit($dosisRaw) || (int) $dosisRaw < 1 || (int) $dosisRaw > 99) {
                    $failed++;
                    $results[] = [
                        'row' => $excelRow,
                        'nombre' => $label,
                        'status' => 'error',
                        'message' => 'numero_dosis debe ser un entero entre 1 y 99.',
                    ];
                    continue;
                }
                $dosis = (int) $dosisRaw;
            }

            $productoId = null;
            $skuRaw = trim((string) ($data['producto_sku'] ?? ''));
            if ($skuRaw !== '') {
                $productoId = $productosBySku[mb_strtolower($skuRaw)] ?? null;
                if ($productoId === null) {
                    $failed++;
                    $results[] = [
                        'row' => $excelRow,
                        'nombre' => $label,
                        'status' => 'error',
                        'message' => "producto_sku «{$skuRaw}» no encontrado (medicamento activo).",
                    ];
                    continue;
                }
            }

            $sedeId = null;
            $sedeRaw = trim((string) ($data['sede_codigo'] ?? ''));
            if ($sedeRaw !== '') {
                $sedeId = $sedesByCodigo[mb_strtolower($sedeRaw)] ?? null;
                if ($sedeId === null) {
                    $failed++;
                    $results[] = [
                        'row' => $excelRow,
                        'nombre' => $label,
                        'status' => 'error',
                        'message' => "sede_codigo «{$sedeRaw}» no encontrado.",
                    ];
                    continue;
                }
            }

            $veterinarioId = null;
            $vetRaw = trim((string) ($data['veterinario'] ?? ''));
            if ($vetRaw !== '') {
                $vetKey = mb_strtolower($vetRaw);
                $veterinarioId = $vetsByEmail[$vetKey] ?? null;
                if ($veterinarioId === null && ! isset($vetsNameAmbiguo[$vetKey])) {
                    $veterinarioId = $vetsByName[$vetKey] ?? null;
                }
                if ($veterinarioId === null) {
                    $failed++;
                    $results[] = [
                        'row' => $excelRow,
                        'nombre' => $label,
                        'status' => 'error',
                        'message' => "veterinario «{$vetRaw}» no encontrado.",
                    ];
                    continue;
                }
            } elseif (is_string($userId) || is_int($userId)) {
                $veterinarioId = (string) $userId;
            }

            try {
                DB::transaction(function () use (
                    $pacienteId,
                    $nombreVacuna,
                    $categoria,
                    $aplicadaAt,
                    $proxima,
                    $dosis,
                    $data,
                    $productoId,
                    $sedeId,
                    $veterinarioId,
                    $userId,
                ): void {
                    VacunaAplicada::query()->create([
                        'paciente_id' => $pacienteId,
                        'nombre_vacuna' => Str::limit($nombreVacuna, 500, ''),
                        'categoria_registro' => $categoria,
                        'aplicada_at' => $aplicadaAt,
                        'fecha_proxima_sugerida' => $proxima,
                        'numero_dosis' => $dosis,
                        'lote' => $this->nullableStr($data['lote'] ?? '', 128),
                        'producto_id' => $productoId,
                        'sede_id' => $sedeId,
                        'veterinario_id' => $veterinarioId,
                        'esquema_antigenos' => $this->nullableStr($data['esquema_antigenos'] ?? '', 2000),
                        'notas' => $this->nullableStr($data['notas'] ?? '', 20000),
                        'created_by_id' => $userId,
                        'updated_by_id' => $userId,
                    ]);
                });

                // Sin descuento de stock: la carga masiva es histórica / administrativa.
                $imported++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $nombreVacuna,
                    'status' => 'ok',
                    'message' => 'Aplicación registrada.',
                ];
            } catch (Throwable $e) {
                report($e);
                $failed++;
                $results[] = [
                    'row' => $excelRow,
                    'nombre' => $label,
                    'status' => 'error',
                    'message' => 'Error al guardar: '.$e->getMessage(),
                ];
            }
        }

        $spreadsheet->disconnectWorksheets();

        if ($imported === 0 && $failed === 0 && $skipped === 0) {
            return $this->fail('El archivo no tiene filas de vacunaciones para importar.');
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
     * @param  list<string>  $headers
     * @param  list<mixed>  $row
     * @return array<string, mixed>
     */
    private function mapRow(array $headers, array $row): array
    {
        $out = [];
        foreach ($headers as $i => $key) {
            if ($key === '') {
                continue;
            }
            $out[$key] = $row[$i] ?? null;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function rowIsEmpty(array $data): bool
    {
        foreach ($data as $v) {
            if (trim((string) ($v ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function looksLikeExample(array $data): bool
    {
        $blob = mb_strtolower(implode(' ', array_map(
            static fn ($v) => (string) ($v ?? ''),
            $data,
        )));

        return str_contains($blob, 'ejemplo') || str_contains($blob, 'bórrala') || str_contains($blob, 'borrala');
    }

    private function parseCategoria(string $raw): ?string
    {
        $k = mb_strtolower(trim($raw));
        $k = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $k);

        return match ($k) {
            'vacuna', 'vacunas' => VacunaAplicada::CATEGORIA_VACUNA,
            'desparasitacion', 'desparasitación', 'antiparasitario', 'antiparasitarios' => VacunaAplicada::CATEGORIA_DESPARASITACION,
            'otro', 'otros' => VacunaAplicada::CATEGORIA_OTRO,
            default => null,
        };
    }

    private function parseDateTime(mixed $raw, string $tz): ?Carbon
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $raw))->timezone($tz);
            } catch (Throwable) {
                return null;
            }
        }

        $s = trim((string) $raw);
        $formats = [
            'd/m/Y H:i',
            'd/m/Y H:i:s',
            'd-m-Y H:i',
            'd/m/Y',
            'd-m-Y',
            'Y-m-d H:i',
            'Y-m-d H:i:s',
            'Y-m-d',
        ];

        foreach ($formats as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $s, $tz);
                if ($dt === false) {
                    continue;
                }
                if (! str_contains($fmt, 'H')) {
                    $dt->setTime(9, 0);
                }

                return $dt;
            } catch (Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($s, $tz);
        } catch (Throwable) {
            return null;
        }
    }

    private function parseDateOnly(mixed $raw, string $tz): ?Carbon
    {
        $dt = $this->parseDateTime($raw, $tz);

        return $dt?->copy()->startOfDay();
    }

    private function normalizeHeader(string $header): string
    {
        $h = mb_strtolower(trim($header));
        $h = str_replace(['*', '¿', '?'], '', $h);
        $h = preg_replace('/\s+/u', ' ', $h) ?? $h;

        if (str_starts_with($h, 'aplicada_at') || str_starts_with($h, 'aplicada at')) {
            return 'aplicada_at';
        }
        if (str_starts_with($h, 'fecha_proxima') || str_starts_with($h, 'fecha proxima')) {
            return 'fecha_proxima';
        }

        $h = explode('(', $h)[0];
        $h = trim(str_replace(' ', '_', trim($h)));

        return match ($h) {
            'paciente', 'mascota' => 'paciente',
            'nombre_vacuna', 'vacuna', 'producto' => 'nombre_vacuna',
            'categoria', 'categoria_registro', 'tipo' => 'categoria',
            'numero_dosis', 'dosis', 'n_dosis' => 'numero_dosis',
            'producto_sku', 'sku' => 'producto_sku',
            'sede_codigo', 'sede', 'codigo_sede' => 'sede_codigo',
            'veterinario', 'medico', 'email_veterinario' => 'veterinario',
            'esquema_antigenos', 'esquema', 'antigenos' => 'esquema_antigenos',
            'lote', 'notas' => $h,
            default => $h,
        };
    }

    private function nullableStr(mixed $value, int $max): ?string
    {
        $t = trim((string) ($value ?? ''));
        if ($t === '') {
            return null;
        }

        return mb_substr($t, 0, $max);
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
}
