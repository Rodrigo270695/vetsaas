<?php

namespace App\Exports;

use App\Models\MovimientoInventario;
use App\Support\Inventario\MovimientoNotasVista;
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
 * Generador del export XLSX del kardex (movimientos de inventario).
 *
 * Misma familia visual que {@see SedesXlsxExport}: tabla Excel nativa,
 * cabecera corporativa y streaming vía `streamTo`.
 */
class MovimientosInventarioXlsxExport
{
    /**
     * @var array<int, array{label: string, value: \Closure(MovimientoInventario): mixed}>
     */
    private array $columns;

    public function __construct()
    {
        $this->columns = [
            [
                'label' => 'Fecha y hora',
                'value' => fn (MovimientoInventario $m) => optional($m->created_at)->format('Y-m-d H:i') ?? '',
            ],
            [
                'label' => 'Sede',
                'value' => fn (MovimientoInventario $m) => (string) ($m->sede?->nombre ?? ''),
            ],
            [
                'label' => 'Código sede',
                'value' => fn (MovimientoInventario $m) => (string) ($m->sede?->codigo ?? ''),
            ],
            [
                'label' => 'Producto',
                'value' => fn (MovimientoInventario $m) => (string) ($m->producto?->nombre ?? ''),
            ],
            [
                'label' => 'SKU',
                'value' => fn (MovimientoInventario $m) => (string) ($m->producto?->sku ?? ''),
            ],
            [
                'label' => 'Tipo',
                'value' => fn (MovimientoInventario $m) => match ($m->tipo) {
                    MovimientoInventario::TIPO_ENTRADA => 'Entrada',
                    MovimientoInventario::TIPO_SALIDA => 'Salida',
                    MovimientoInventario::TIPO_MERMA => 'Merma',
                    MovimientoInventario::TIPO_AJUSTE => 'Ajuste',
                    default => (string) $m->tipo,
                },
            ],
            [
                'label' => 'Cambio',
                'value' => fn (MovimientoInventario $m) => (string) $m->delta,
            ],
            [
                'label' => 'Stock antes',
                'value' => fn (MovimientoInventario $m) => (string) $m->stock_anterior,
            ],
            [
                'label' => 'Stock después',
                'value' => fn (MovimientoInventario $m) => (string) $m->stock_despues,
            ],
            [
                'label' => 'Usuario',
                'value' => fn (MovimientoInventario $m) => (string) ($m->creadoPor?->name ?? ''),
            ],
            [
                'label' => 'Notas',
                'value' => fn (MovimientoInventario $m) => MovimientoNotasVista::fromModel($m),
            ],
        ];
    }

    /**
     * @param  Builder<MovimientoInventario>  $query
     */
    public function streamTo(Builder $query, string $output = 'php://output'): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('VetSaaS')
            ->setTitle('Movimientos de inventario')
            ->setSubject('Kardex por sede');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Movimientos');

        $columnCount = count($this->columns);
        $lastColumnLetter = Coordinate::stringFromColumnIndex($columnCount);

        $sheet->setCellValue('A1', 'Movimientos de inventario');
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
        /** @var MovimientoInventario $mov */
        foreach ($query->cursor() as $mov) {
            foreach ($this->columns as $index => $col) {
                $colLetter = Coordinate::stringFromColumnIndex($index + 1);
                $value = ($col['value'])($mov);
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
        $table = new Table($tableRange, 'TablaMovimientosInventario');
        $table->setStyle(new TableStyle(TableStyle::TABLE_STYLE_MEDIUM2));
        $sheet->addTable($table);
    }
}
