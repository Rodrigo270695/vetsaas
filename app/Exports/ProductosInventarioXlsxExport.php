<?php

namespace App\Exports;

use App\Models\Producto;
use App\Models\ProductoLote;
use App\Services\Inventario\InventarioLoteService;
use Illuminate\Database\Eloquent\Builder;
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
 * Export XLSX del catálogo de productos de inventario.
 */
class ProductosInventarioXlsxExport
{
    /**
     * @var array<int, array{label: string, value: \Closure(Producto): mixed}>
     */
    private array $columns;

    public function __construct()
    {
        $this->columns = [
            [
                'label' => 'Nombre',
                'value' => fn (Producto $p) => (string) $p->nombre,
            ],
            [
                'label' => 'Categoría',
                'value' => fn (Producto $p) => (string) ($p->categoria?->nombre ?? ''),
            ],
            [
                'label' => 'SKU',
                'value' => fn (Producto $p) => (string) ($p->sku ?? ''),
            ],
            [
                'label' => 'Código de barras',
                'value' => fn (Producto $p) => (string) ($p->codigo_barras ?? ''),
            ],
            [
                'label' => 'Unidad',
                'value' => fn (Producto $p) => (string) ($p->unidad ?? ''),
            ],
            [
                'label' => 'Precio venta',
                'value' => fn (Producto $p) => $p->precio_venta !== null ? (string) $p->precio_venta : '',
            ],
            [
                'label' => 'Precio compra',
                'value' => fn (Producto $p) => $p->precio_compra !== null ? (string) $p->precio_compra : '',
            ],
            [
                'label' => 'Stock mínimo',
                'value' => fn (Producto $p) => $p->stock_minimo !== null ? (string) $p->stock_minimo : '',
            ],
            [
                'label' => 'Lote (próximo)',
                'value' => fn (Producto $p) => (string) ($p->getAttribute('lote_numero') ?? ''),
            ],
            [
                'label' => 'Vencimiento (próximo)',
                'value' => fn (Producto $p) => (string) ($p->getAttribute('lote_vencimiento') ?? ''),
            ],
            [
                'label' => 'Medicamento',
                'value' => fn (Producto $p) => $p->medicamento ? 'Sí' : 'No',
            ],
            [
                'label' => 'Estado',
                'value' => fn (Producto $p) => $p->activo ? 'Activo' : 'Inactivo',
            ],
            [
                'label' => 'Descripción',
                'value' => fn (Producto $p) => (string) ($p->descripcion ?? ''),
            ],
            [
                'label' => 'Creado',
                'value' => fn (Producto $p) => optional($p->created_at)->format('Y-m-d H:i') ?? '',
            ],
        ];
    }

    /**
     * @param  Builder<Producto>  $query
     */
    public function streamTo(Builder $query, string $output = 'php://output'): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('VetSaaS')
            ->setTitle('Productos')
            ->setSubject('Catálogo de productos de inventario');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Productos');

        $columnCount = count($this->columns);
        $lastColumnLetter = Coordinate::stringFromColumnIndex($columnCount);

        $sheet->setCellValue('A1', 'Productos de inventario');
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
        ]);

        $headerRow = 4;
        $dataStartRow = $headerRow + 1;

        foreach ($this->columns as $index => $col) {
            $colLetter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue("{$colLetter}{$headerRow}", $col['label']);
        }

        $row = $dataStartRow;
        $productos = $query->get();
        $this->appendLoteProximo($productos);

        /** @var Producto $producto */
        foreach ($productos as $producto) {
            foreach ($this->columns as $index => $col) {
                $colLetter = Coordinate::stringFromColumnIndex($index + 1);
                $sheet->setCellValueExplicit(
                    "{$colLetter}{$row}",
                    ($col['value'])($producto),
                    DataType::TYPE_STRING,
                );
            }
            $row++;
        }

        $lastDataRow = max($dataStartRow, $row - 1);
        $this->styleTable($sheet, $lastColumnLetter, $headerRow, $lastDataRow);
        $sheet->freezePane('A'.($headerRow + 1));

        foreach (range(1, $columnCount) as $i) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($output);
        $spreadsheet->disconnectWorksheets();
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
            ]);
        }

        $tableRange = "A{$headerRow}:{$lastColumn}".max($headerRow + 1, $lastDataRow);
        $table = new Table($tableRange, 'TablaProductos');
        $table->setStyle(new TableStyle(TableStyle::TABLE_STYLE_MEDIUM2));
        $sheet->addTable($table);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Producto>  $productos
     */
    private function appendLoteProximo($productos): void
    {
        if ($productos->isEmpty()) {
            return;
        }

        $ids = $productos->pluck('id')->all();
        $lotes = ProductoLote::query()
            ->whereIn('producto_id', $ids)
            ->where('cantidad', '>', 0)
            ->orderByRaw('fecha_vencimiento IS NULL')
            ->orderBy('fecha_vencimiento')
            ->orderBy('created_at')
            ->get(['producto_id', 'numero_lote', 'fecha_vencimiento']);

        $byProducto = $lotes->groupBy('producto_id');

        foreach ($productos as $producto) {
            /** @var ProductoLote|null $lote */
            $lote = $byProducto->get((string) $producto->id)?->first();
            $numero = $lote !== null ? (string) $lote->numero_lote : null;
            if ($numero === InventarioLoteService::LOTE_SIN_ESPECIFICAR) {
                $numero = null;
            }

            $producto->setAttribute('lote_numero', $numero);
            $producto->setAttribute(
                'lote_vencimiento',
                $lote?->fecha_vencimiento?->format('Y-m-d'),
            );
        }
    }
}
