<?php

namespace App\Exports;

use App\Models\Compra;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Export XLSX del listado de compras de inventario (misma familia visual que {@see MovimientosInventarioXlsxExport}).
 */
class ComprasInventarioXlsxExport
{
    /**
     * @var array<int, array{label: string, value: \Closure(Compra): mixed}>
     */
    private array $columns;

    public function __construct()
    {
        $this->columns = [
            [
                'label' => 'Fecha documento',
                'value' => fn (Compra $c) => optional($c->fecha_documento)->format('Y-m-d') ?? '',
            ],
            [
                'label' => 'Sede',
                'value' => fn (Compra $c) => (string) ($c->sede?->nombre ?? ''),
            ],
            [
                'label' => 'Código sede',
                'value' => fn (Compra $c) => (string) ($c->sede?->codigo ?? ''),
            ],
            [
                'label' => 'Serie',
                'value' => fn (Compra $c) => (string) ($c->serie ?? ''),
            ],
            [
                'label' => 'Número documento',
                'value' => fn (Compra $c) => (string) ($c->numero_documento ?? ''),
            ],
            [
                'label' => 'Proveedor RUC',
                'value' => fn (Compra $c) => (string) ($c->proveedor?->ruc ?? ''),
            ],
            [
                'label' => 'Proveedor',
                'value' => fn (Compra $c) => (string) ($c->proveedor?->razon_social ?? ''),
            ],
            [
                'label' => 'Líneas',
                'value' => fn (Compra $c) => (string) (int) ($c->lineas_count ?? 0),
            ],
            [
                'label' => 'Moneda',
                'value' => fn (Compra $c) => (string) ($c->moneda ?? ''),
            ],
            [
                'label' => 'Total',
                'value' => fn (Compra $c) => $c->total !== null ? (string) $c->total : '',
            ],
            [
                'label' => 'Notas',
                'value' => fn (Compra $c) => (string) ($c->notas ?? ''),
            ],
            [
                'label' => 'Factura adjunta',
                'value' => fn (Compra $c) => $c->factura_path !== null && $c->factura_path !== '' ? 'Sí' : 'No',
            ],
            [
                'label' => 'Registrado',
                'value' => fn (Compra $c) => optional($c->created_at)->format('Y-m-d H:i') ?? '',
            ],
            [
                'label' => 'Usuario',
                'value' => fn (Compra $c) => (string) ($c->creadoPor?->name ?? ''),
            ],
        ];
    }

    /**
     * @param  Builder<Compra>  $query
     */
    public function streamTo(Builder $query, string $output = 'php://output'): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('VetSaaS')
            ->setTitle('Compras de inventario')
            ->setSubject('Compras por sede y rango de fechas');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Compras');

        $columnCount = count($this->columns);
        $lastColumnLetter = Coordinate::stringFromColumnIndex($columnCount);

        $sheet->setCellValue('A1', 'Compras de inventario');
        $sheet->mergeCells("A1:{$lastColumnLetter}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => '0E5236'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $sheet->setCellValue(
            'A2',
            sprintf(
                'Exportado el %s · %d registros',
                now()->format('d/m/Y H:i'),
                $query->toBase()->getCountForPagination(),
            ),
        );
        $sheet->mergeCells("A2:{$lastColumnLetter}2");
        $sheet->getStyle('A2')->applyFromArray([
            'font' => [
                'italic' => true,
                'size' => 10,
                'color' => ['rgb' => '6B7280'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $headerRow = 4;
        $dataStartRow = $headerRow + 1;

        foreach ($this->columns as $index => $col) {
            $colLetter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue("{$colLetter}{$headerRow}", $col['label']);
        }

        $row = $dataStartRow;
        /** @var Compra $compra */
        foreach ($query->cursor() as $compra) {
            foreach ($this->columns as $index => $col) {
                $colLetter = Coordinate::stringFromColumnIndex($index + 1);
                $value = ($col['value'])($compra);
                $sheet->setCellValueExplicit(
                    "{$colLetter}{$row}",
                    $value,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING,
                );
            }
            $row++;
        }

        $lastDataRow = max($dataStartRow, $row - 1);

        $this->styleTable($sheet, $lastColumnLetter, $headerRow, $lastDataRow);

        $sheet->freezePane('A'.($headerRow + 1));

        foreach (range('A', $lastColumnLetter) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($output);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    private function styleTable(
        Worksheet $sheet,
        string $lastColumn,
        int $headerRow,
        int $lastDataRow,
    ): void {
        $headerRange = "A{$headerRow}:{$lastColumn}{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
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
        $table = new Table($tableRange, 'TablaComprasInventario');
        $table->setStyle(new TableStyle(TableStyle::TABLE_STYLE_MEDIUM2));
        $sheet->addTable($table);
    }
}
