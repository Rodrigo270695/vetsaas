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
 * Export XLSX del reporte de ventas por producto (misma familia visual que Sedes).
 */
class ReporteVentasProductosXlsxExport
{
    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array{unidades: float, ventas: int, ingresos: float, costo: float, utilidad: ?float, margen_pct: ?float, items_sin_costo: int}  $totales
     * @param  array{fecha_desde: string, fecha_hasta: string, periodo: string}  $filtros
     */
    public function streamTo(
        array $items,
        array $totales,
        array $filtros,
        string $moneda,
        string $output = 'php://output',
    ): void {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('VetSaaS')
            ->setTitle('Ventas por producto')
            ->setSubject('Reporte de ventas por producto');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Productos');

        $headers = [
            'Producto',
            'Categoría',
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

        $columnCount = count($headers);
        $lastColumnLetter = Coordinate::stringFromColumnIndex($columnCount);

        $sheet->setCellValue('A1', 'Ventas por producto');
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
                'Exportado el %s · Periodo %s → %s · %d productos · Unidades %s · Ingresos %s %s · Utilidad %s %s · Moneda %s',
                now()->format('d/m/Y H:i'),
                $filtros['fecha_desde'],
                $filtros['fecha_hasta'],
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

        $row = $headerRow + 1;
        foreach ($items as $item) {
            $values = [
                (string) ($item['nombre'] ?? ''),
                (string) ($item['categoria'] ?? '—'),
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
        $this->styleTable($sheet, $lastColumnLetter, $headerRow, $lastDataRow, 'TablaVentasProductos');
        $sheet->freezePane('A'.($headerRow + 1));

        foreach (range('A', $lastColumnLetter) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

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
