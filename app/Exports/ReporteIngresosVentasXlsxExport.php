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
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class ReporteIngresosVentasXlsxExport
{
    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array{ventas: int, ingresos: float}  $totales
     * @param  array<string, mixed>  $filtros
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
            ->setTitle('Ingresos de ventas')
            ->setSubject('Reporte de ingresos por comprobante y método de pago');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ingresos');

        $headers = ['Fecha', 'Número', 'Comprobante', 'Tipo', 'Cliente', 'Método de pago', 'Total', 'FEL'];
        $lastColumnLetter = Coordinate::stringFromColumnIndex(count($headers));

        $sheet->setCellValue('A1', 'Ingresos de ventas');
        $sheet->mergeCells("A1:{$lastColumnLetter}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '0E5236']],
        ]);

        $sheet->setCellValue(
            'A2',
            sprintf(
                'Exportado el %s · Periodo %s → %s · %d ventas · Ingresos %s %s · Tipos %s · Métodos %s',
                now()->format('d/m/Y H:i'),
                $filtros['fecha_desde'] ?? '',
                $filtros['fecha_hasta'] ?? '',
                (int) $totales['ventas'],
                $moneda,
                number_format((float) $totales['ingresos'], 2, '.', ','),
                implode(', ', is_array($filtros['tipos'] ?? null) ? $filtros['tipos'] : []),
                implode(', ', is_array($filtros['metodos'] ?? null) ? $filtros['metodos'] : []),
            ),
        );
        $sheet->mergeCells("A2:{$lastColumnLetter}2");
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '6B7280']],
        ]);

        $headerRow = 4;
        foreach ($headers as $index => $label) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).$headerRow, $label);
        }

        $row = $headerRow + 1;
        foreach ($items as $item) {
            $values = [
                $this->fecha($item['fecha'] ?? null),
                (string) ($item['numero'] ?? ''),
                (string) ($item['comprobante'] ?? ''),
                (string) ($item['tipo'] ?? ''),
                (string) ($item['cliente'] ?? ''),
                (string) ($item['metodos_label'] ?? ''),
                number_format((float) ($item['total'] ?? 0), 2, '.', ','),
                (string) ($item['fel_estado'] ?? ''),
            ];
            foreach ($values as $index => $value) {
                $sheet->setCellValueExplicit(
                    Coordinate::stringFromColumnIndex($index + 1).$row,
                    $value,
                    DataType::TYPE_STRING,
                );
            }
            $row++;
        }

        $lastDataRow = max($headerRow + 1, $row - 1);
        $headerRange = "A{$headerRow}:{$lastColumnLetter}{$headerRow}";
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

        $table = new Table("A{$headerRow}:{$lastColumnLetter}".$lastDataRow, 'TablaIngresosVentas');
        $table->setStyle(new TableStyle(TableStyle::TABLE_STYLE_MEDIUM2));
        $sheet->addTable($table);
        $sheet->freezePane('A'.($headerRow + 1));

        foreach (range('A', $lastColumnLetter) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        (new Xlsx($spreadsheet))->save($output);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    private function fecha(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return '—';
        }

        try {
            return Carbon::parse($value)->format('d/m/Y H:i');
        } catch (Throwable) {
            return $value;
        }
    }
}
