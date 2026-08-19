<?php

declare(strict_types=1);

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Export XLSX del reporte de ventas por servicio.
 *
 * - tipo específico → 1 hoja de detalle
 * - todos / resumen → hoja Resumen + hojas Tratamientos / Vacunas / Grooming
 */
class ReporteVentasServiciosXlsxExport
{
    /** @var list<string> */
    private const DETAIL_HEADERS = [
        'Fecha',
        'Servicio',
        'Categoría',
        'Tipo',
        'Cantidad',
        'Ventas',
        'Precio unit.',
        'Costo unit.',
        'Ingreso',
        'Costo total',
        'Utilidad',
        'Margen %',
        'Con costo',
    ];

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array{unidades: float, ventas: int, ingresos: float, costo: float, utilidad: ?float, margen_pct: ?float, items_sin_costo: int}  $totales
     * @param  array{tratamiento: array, vacuna: array, grooming: array}  $resumen
     * @param  array{fecha_desde: string, fecha_hasta: string, periodo: string, tipo: string}  $filtros
     */
    public function streamTo(
        array $items,
        array $totales,
        array $resumen,
        array $filtros,
        string $moneda,
        bool $includeGrooming,
        string $output = 'php://output',
    ): void {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('VetSaaS')
            ->setTitle('Ventas por servicio')
            ->setSubject('Reporte de ventas por servicio');

        $tipo = (string) ($filtros['tipo'] ?? 'todos');
        $periodoLabel = sprintf('%s → %s', $filtros['fecha_desde'], $filtros['fecha_hasta']);

        if (in_array($tipo, ['tratamiento', 'vacuna', 'grooming'], true)) {
            $sheet = $spreadsheet->getActiveSheet();
            $title = match ($tipo) {
                'vacuna' => 'Vacunas',
                'grooming' => 'Grooming',
                default => 'Tratamientos',
            };
            $this->writeDetailSheet(
                $sheet,
                $title,
                "Ventas por servicio · {$title}",
                $periodoLabel,
                $moneda,
                $totales,
                $items,
                'TablaServiciosDetalle',
            );
        } else {
            $sheetResumen = $spreadsheet->getActiveSheet();
            $this->writeResumenSheet($sheetResumen, $periodoLabel, $moneda, $resumen, $includeGrooming);

            $porTipo = [
                'tratamiento' => [],
                'vacuna' => [],
                'grooming' => [],
            ];
            foreach ($items as $item) {
                $t = (string) ($item['tipo'] ?? '');
                if (isset($porTipo[$t])) {
                    $porTipo[$t][] = $item;
                }
            }

            $sheets = [
                ['title' => 'Tratamientos', 'tipo' => 'tratamiento', 'table' => 'TablaTratamientos'],
                ['title' => 'Vacunas', 'tipo' => 'vacuna', 'table' => 'TablaVacunas'],
            ];
            if ($includeGrooming) {
                $sheets[] = ['title' => 'Grooming', 'tipo' => 'grooming', 'table' => 'TablaGrooming'];
            }

            foreach ($sheets as $meta) {
                $sheet = $spreadsheet->createSheet();
                $sliceItems = $porTipo[$meta['tipo']];
                $sliceTotales = $this->totalesFromItems($sliceItems);
                $this->writeDetailSheet(
                    $sheet,
                    $meta['title'],
                    "Ventas por servicio · {$meta['title']}",
                    $periodoLabel,
                    $moneda,
                    $sliceTotales,
                    $sliceItems,
                    $meta['table'],
                );
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        (new Xlsx($spreadsheet))->save($output);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    /**
     * @param  array{tratamiento: array, vacuna: array, grooming: array}  $resumen
     */
    private function writeResumenSheet(
        Worksheet $sheet,
        string $periodoLabel,
        string $moneda,
        array $resumen,
        bool $includeGrooming,
    ): void {
        $headers = ['Tipo', 'Ítems', 'Unidades', 'Ventas', 'Ingresos', 'Costo', 'Utilidad', 'Margen %', 'Sin costo'];
        $lastColumnLetter = Coordinate::stringFromColumnIndex(count($headers));

        $sheet->setTitle('Resumen');
        $sheet->setCellValue('A1', 'Ventas por servicio · Resumen');
        $sheet->mergeCells("A1:{$lastColumnLetter}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '0E5236']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $sheet->setCellValue(
            'A2',
            sprintf('Exportado el %s · Periodo %s · Moneda %s', now()->format('d/m/Y H:i'), $periodoLabel, $moneda),
        );
        $sheet->mergeCells("A2:{$lastColumnLetter}2");
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '6B7280']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $headerRow = 4;
        foreach ($headers as $index => $label) {
            $colLetter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue("{$colLetter}{$headerRow}", $label);
        }

        $rows = [
            ['Tratamientos', $resumen['tratamiento'] ?? []],
            ['Vacunas', $resumen['vacuna'] ?? []],
        ];
        if ($includeGrooming) {
            $rows[] = ['Grooming', $resumen['grooming'] ?? []];
        }

        $row = $headerRow + 1;
        foreach ($rows as [$label, $slice]) {
            $values = [
                $label,
                (string) (int) ($slice['items'] ?? 0),
                $this->num($slice['unidades'] ?? 0),
                (string) (int) ($slice['ventas'] ?? 0),
                $this->money($slice['ingresos'] ?? 0),
                $this->money($slice['costo'] ?? 0),
                $this->money($slice['utilidad'] ?? null),
                $this->pct($slice['margen_pct'] ?? null),
                (string) (int) ($slice['items_sin_costo'] ?? 0),
            ];
            foreach ($values as $index => $value) {
                $colLetter = Coordinate::stringFromColumnIndex($index + 1);
                $sheet->setCellValueExplicit("{$colLetter}{$row}", $value, DataType::TYPE_STRING);
            }
            $row++;
        }

        $lastDataRow = max($headerRow + 1, $row - 1);
        $this->styleTable($sheet, $lastColumnLetter, $headerRow, $lastDataRow, 'TablaServiciosResumen');
        $sheet->freezePane('A'.($headerRow + 1));
        foreach (range('A', $lastColumnLetter) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * @param  array{unidades: float, ventas: int, ingresos: float, costo: float, utilidad: ?float, margen_pct: ?float, items_sin_costo: int}  $totales
     * @param  list<array<string, mixed>>  $items
     */
    private function writeDetailSheet(
        Worksheet $sheet,
        string $sheetTitle,
        string $title,
        string $periodoLabel,
        string $moneda,
        array $totales,
        array $items,
        string $tableName,
    ): void {
        $headers = self::DETAIL_HEADERS;
        $lastColumnLetter = Coordinate::stringFromColumnIndex(count($headers));

        $sheet->setTitle(mb_substr($sheetTitle, 0, 31));
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells("A1:{$lastColumnLetter}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '0E5236']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $utilidadTxt = $totales['utilidad'] === null
            ? '—'
            : number_format((float) $totales['utilidad'], 2, '.', ',');

        $sheet->setCellValue(
            'A2',
            sprintf(
                'Exportado el %s · Periodo %s · %d servicios · Unidades %s · Ingresos %s %s · Utilidad %s %s · Moneda %s',
                now()->format('d/m/Y H:i'),
                $periodoLabel,
                count($items),
                number_format((float) $totales['unidades'], 2, '.', ','),
                $moneda,
                number_format((float) $totales['ingresos'], 2, '.', ','),
                $moneda,
                $utilidadTxt,
                $moneda,
            ),
        );
        $sheet->mergeCells("A2:{$lastColumnLetter}2");
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '6B7280']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $headerRow = 4;
        foreach ($headers as $index => $label) {
            $colLetter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue("{$colLetter}{$headerRow}", $label);
        }

        $tipoLabels = [
            'tratamiento' => 'Tratamiento',
            'vacuna' => 'Vacuna',
            'grooming' => 'Grooming',
        ];

        $row = $headerRow + 1;
        foreach ($items as $item) {
            $tipo = (string) ($item['tipo'] ?? '');
            $values = [
                $this->fechaRango($item['fecha_primera'] ?? null, $item['fecha_ultima'] ?? null),
                (string) ($item['nombre'] ?? ''),
                (string) ($item['categoria'] ?? '—'),
                $tipoLabels[$tipo] ?? $tipo,
                $this->num($item['cantidad'] ?? 0),
                (string) (int) ($item['ventas'] ?? 0),
                $this->money($item['precio_unit'] ?? null),
                $this->money($item['costo_unit'] ?? null),
                $this->money($item['ingreso'] ?? 0),
                $this->money($item['costo'] ?? null),
                $this->money($item['utilidad'] ?? null),
                $this->pct($item['margen_pct'] ?? null),
                ! empty($item['tiene_costo']) ? 'Sí' : 'No',
            ];
            foreach ($values as $index => $value) {
                $colLetter = Coordinate::stringFromColumnIndex($index + 1);
                $sheet->setCellValueExplicit("{$colLetter}{$row}", $value, DataType::TYPE_STRING);
            }
            $row++;
        }

        $lastDataRow = max($headerRow + 1, $row - 1);
        $this->styleTable($sheet, $lastColumnLetter, $headerRow, $lastDataRow, $tableName);
        $sheet->freezePane('A'.($headerRow + 1));
        foreach (range('A', $lastColumnLetter) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{unidades: float, ventas: int, ingresos: float, costo: float, utilidad: ?float, margen_pct: ?float, items_sin_costo: int}
     */
    private function totalesFromItems(array $items): array
    {
        $unidades = 0.0;
        $ingresos = 0.0;
        $costo = 0.0;
        $utilidadAcum = 0.0;
        $conCosto = 0;
        $sinCosto = 0;
        $ventas = 0;

        foreach ($items as $item) {
            $unidades += (float) ($item['cantidad'] ?? 0);
            $ingresos += (float) ($item['ingreso'] ?? 0);
            $ventas += (int) ($item['ventas'] ?? 0);
            if (! empty($item['tiene_costo'])) {
                $costo += (float) ($item['costo'] ?? 0);
                $utilidadAcum += (float) ($item['utilidad'] ?? 0);
                $conCosto++;
            } else {
                $sinCosto++;
            }
        }

        $ingresos = round($ingresos, 2);
        $costo = round($costo, 2);
        $utilidad = $conCosto > 0 ? round($utilidadAcum, 2) : null;
        $margen = ($utilidad !== null && $ingresos > 0)
            ? round(max(-999.9, min(999.9, ($utilidad / $ingresos) * 100)), 1)
            : null;

        return [
            'unidades' => round($unidades, 2),
            'ventas' => $ventas,
            'ingresos' => $ingresos,
            'costo' => $costo,
            'utilidad' => $utilidad,
            'margen_pct' => $margen,
            'items_sin_costo' => $sinCosto,
        ];
    }

    private function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value, 2, '.', ',');
    }

    private function num(mixed $value): string
    {
        return number_format((float) $value, 2, '.', ',');
    }

    private function pct(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value, 1, '.', ',').'%';
    }

    private function fechaRango(mixed $primera, mixed $ultima): string
    {
        $a = is_string($primera) ? trim($primera) : '';
        $b = is_string($ultima) ? trim($ultima) : '';

        if ($a === '' && $b === '') {
            return '—';
        }

        $fmt = static function (string $iso): string {
            try {
                return \Carbon\Carbon::parse($iso)->format('d/m/Y');
            } catch (\Throwable) {
                return $iso;
            }
        };

        if ($a === '' || $b === '' || $a === $b) {
            return $fmt($a !== '' ? $a : $b);
        }

        return $fmt($a).' – '.$fmt($b);
    }

    private function styleTable(
        Worksheet $sheet,
        string $lastColumn,
        int $headerRow,
        int $lastDataRow,
        string $tableName,
    ): void {
        $headerRange = "A{$headerRow}:{$lastColumn}{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F6E4A'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '0E5236'],
                ],
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(28);

        if ($lastDataRow >= $headerRow + 1) {
            $dataRange = 'A'.($headerRow + 1).":{$lastColumn}{$lastDataRow}";
            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'E5E7EB'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => false,
                ],
            ]);
        }

        $tableRange = "A{$headerRow}:{$lastColumn}".max($headerRow + 1, $lastDataRow);
        $table = new Table($tableRange, $tableName);
        $table->setStyle(new TableStyle(TableStyle::TABLE_STYLE_MEDIUM2));
        $sheet->addTable($table);
    }
}
