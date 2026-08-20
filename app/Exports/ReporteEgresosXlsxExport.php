<?php

declare(strict_types=1);

namespace App\Exports;

use Carbon\Carbon;
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
 * Export XLSX del reporte de egresos de caja.
 */
class ReporteEgresosXlsxExport
{
    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array{cantidad: int, monto: float}  $totales
     * @param  list<array{motivo: string, motivo_label: string, cantidad: int, monto: float}>  $porMotivo
     * @param  array{fecha_desde: string, fecha_hasta: string, periodo: string, sede_id: ?string, motivo: ?string}  $filtros
     */
    public function streamTo(
        array $items,
        array $totales,
        array $porMotivo,
        array $filtros,
        string $moneda,
        string $output = 'php://output',
    ): void {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('VetSaaS')
            ->setTitle('Egresos de caja')
            ->setSubject('Reporte de egresos de caja');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Egresos');

        $headers = [
            'Fecha',
            'Sede',
            'Motivo',
            'Monto',
            'Notas',
            'Sesión',
            'Registrado por',
        ];

        $columnCount = count($headers);
        $lastColumnLetter = Coordinate::stringFromColumnIndex($columnCount);

        $sheet->setCellValue('A1', 'Egresos de caja');
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
            sprintf(
                'Exportado el %s · Periodo %s → %s · %d egreso(s) · Total %s %s · Moneda %s',
                now()->format('d/m/Y H:i'),
                $filtros['fecha_desde'],
                $filtros['fecha_hasta'],
                (int) ($totales['cantidad'] ?? 0),
                $moneda,
                number_format((float) ($totales['monto'] ?? 0), 2, '.', ','),
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

        $row = $headerRow + 1;
        foreach ($items as $item) {
            $values = [
                $this->fechaDisplay($item['fecha'] ?? null),
                (string) ($item['sede_nombre'] ?? '—'),
                (string) ($item['motivo_label'] ?? $item['motivo'] ?? '—'),
                $this->money($item['monto'] ?? 0),
                (string) ($item['notas'] ?? '—'),
                $this->shortId((string) ($item['caja_sesion_id'] ?? '')),
                (string) ($item['registrado_por'] ?? '—'),
            ];

            foreach ($values as $index => $value) {
                $colLetter = Coordinate::stringFromColumnIndex($index + 1);
                $sheet->setCellValueExplicit("{$colLetter}{$row}", $value, DataType::TYPE_STRING);
            }
            $row++;
        }

        $lastDataRow = max($headerRow + 1, $row - 1);
        $this->styleTable($sheet, $lastColumnLetter, $headerRow, $lastDataRow, 'TablaEgresosCaja');
        $sheet->freezePane('A'.($headerRow + 1));

        foreach (range('A', $lastColumnLetter) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        if ($porMotivo !== []) {
            $resumen = $spreadsheet->createSheet();
            $resumen->setTitle('Por motivo');
            $resumenHeaders = ['Motivo', 'Cantidad', 'Monto'];
            $resumenLast = Coordinate::stringFromColumnIndex(count($resumenHeaders));
            $resumen->setCellValue('A1', 'Egresos por motivo');
            $resumen->mergeCells("A1:{$resumenLast}1");
            $resumen->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0E5236']],
            ]);
            $resumenHeaderRow = 3;
            foreach ($resumenHeaders as $index => $label) {
                $colLetter = Coordinate::stringFromColumnIndex($index + 1);
                $resumen->setCellValue("{$colLetter}{$resumenHeaderRow}", $label);
            }
            $r = $resumenHeaderRow + 1;
            foreach ($porMotivo as $slice) {
                $values = [
                    (string) ($slice['motivo_label'] ?? ''),
                    (string) (int) ($slice['cantidad'] ?? 0),
                    $this->money($slice['monto'] ?? 0),
                ];
                foreach ($values as $index => $value) {
                    $colLetter = Coordinate::stringFromColumnIndex($index + 1);
                    $resumen->setCellValueExplicit("{$colLetter}{$r}", $value, DataType::TYPE_STRING);
                }
                $r++;
            }
            $this->styleTable(
                $resumen,
                $resumenLast,
                $resumenHeaderRow,
                max($resumenHeaderRow + 1, $r - 1),
                'TablaEgresosMotivo',
            );
            foreach (range('A', $resumenLast) as $col) {
                $resumen->getColumnDimension($col)->setAutoSize(true);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        (new Xlsx($spreadsheet))->save($output);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    private function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value, 2, '.', ',');
    }

    private function fechaDisplay(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return '—';
        }

        try {
            return Carbon::parse($value)->timezone((string) config('app.timezone'))->format('d/m/Y H:i');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function shortId(string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            return '—';
        }

        return strlen($id) > 8 ? substr($id, 0, 8).'…' : $id;
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
