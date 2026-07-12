<?php

namespace App\Exports;

use App\Models\CategoriaProducto;
use App\Support\Inventario\UnidadMedidaOpciones;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Plantilla XLSX para carga masiva de productos.
 *
 * Hojas:
 * - Productos: columnas a rellenar (* = obligatorio)
 * - Categorias: listado de referencia del tenant
 * - Unidades: códigos de unidad de medida válidos
 */
class ProductosInventarioImportTemplateXlsx
{
    /**
     * Encabezados de la hoja Productos (tal cual se esperan al importar).
     *
     * @var list<string>
     */
    public const PRODUCTO_HEADERS = [
        'nombre*',
        'unidad*',
        'medicamento*',
        'activo*',
        'categoria',
        'sku',
        'codigo_barras',
        'precio_venta',
        'precio_compra',
        'stock_minimo',
        'descripcion',
    ];

    public function streamTo(string $output = 'php://output'): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('VetSaaS')
            ->setTitle('Plantilla importación de productos')
            ->setSubject('Carga masiva de productos de inventario');

        $this->buildProductosSheet($spreadsheet);
        $this->buildCategoriasSheet($spreadsheet);
        $this->buildUnidadesSheet($spreadsheet);

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $writer->save($output);
        $spreadsheet->disconnectWorksheets();
    }

    private function buildProductosSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Productos');

        $headers = self::PRODUCTO_HEADERS;
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));

        $sheet->setCellValue('A1', 'Plantilla de productos — rellena filas debajo de los encabezados');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0E5236']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->setCellValue(
            'A2',
            'Los campos con * son obligatorios. unidad* debe coincidir con un código de la hoja «Unidades». categoria (opcional) debe coincidir con un nombre de la hoja «Categorias». medicamento*/activo*: usa SI o NO.',
        );
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '6B7280']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(36);

        $headerRow = 4;
        foreach ($headers as $index => $label) {
            $col = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue("{$col}{$headerRow}", $label);
        }

        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0E5236'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '0A3D29'],
                ],
            ],
        ]);

        // Fila de ejemplo (el import la omite si nombre* = "Ejemplo amoxicilina").
        $example = [
            'Ejemplo amoxicilina',
            'CAJA',
            'SI',
            'SI',
            '',
            'AMOX-500',
            '',
            '25.50',
            '12.00',
            '5',
            'Antibiótico — fila de ejemplo, bórrala o cámbiala',
        ];
        $exampleRow = $headerRow + 1;
        foreach ($example as $index => $value) {
            $col = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue("{$col}{$exampleRow}", $value);
        }

        // Filas vacías extra para que la tabla Excel tenga espacio al rellenar.
        $tableEndRow = $exampleRow + 24;
        $table = new Table("A{$headerRow}:{$lastCol}{$tableEndRow}", 'TablaProductos');
        $style = new TableStyle();
        $style->setTheme(TableStyle::TABLE_STYLE_MEDIUM2);
        $style->setShowRowStripes(true);
        $table->setStyle($style);
        $sheet->addTable($table);

        foreach (range(1, count($headers)) as $i) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
        $sheet->freezePane('A5');
    }

    private function buildCategoriasSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Categorias');

        $sheet->setCellValue('A1', 'Categorías activas de la clínica (referencia)');
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '0E5236']],
        ]);

        $sheet->setCellValue('A2', 'Copia el nombre exacto en la columna «categoria» de la hoja Productos.');
        $sheet->mergeCells('A2:B2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '6B7280']],
        ]);

        $sheet->setCellValue('A4', 'nombre');
        $sheet->setCellValue('B4', 'id');
        $sheet->getStyle('A4:B4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0E5236'],
            ],
        ]);

        $query = CategoriaProducto::query()->where('activo', true);
        if (Schema::hasColumn('categorias_productos', 'orden')) {
            $query->orderBy('orden');
        }
        $categorias = $query->orderBy('nombre')->get(['id', 'nombre']);

        $row = 5;
        foreach ($categorias as $cat) {
            $sheet->setCellValue("A{$row}", (string) $cat->nombre);
            $sheet->setCellValue("B{$row}", (string) $cat->id);
            $row++;
        }

        if ($categorias->isEmpty()) {
            $sheet->setCellValue('A5', '(Sin categorías activas — deja «categoria» vacío o créalas antes)');
        }

        $endRow = max(5, $row - 1);
        $table = new Table("A4:B{$endRow}", 'TablaCategorias');
        $style = new TableStyle();
        $style->setTheme(TableStyle::TABLE_STYLE_MEDIUM2);
        $style->setShowRowStripes(true);
        $table->setStyle($style);
        $sheet->addTable($table);

        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->freezePane('A5');
    }

    private function buildUnidadesSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Unidades');

        $sheet->setCellValue('A1', 'Unidades de medida válidas (referencia)');
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '0E5236']],
        ]);

        $sheet->setCellValue('A2', 'Usa el «codigo» en la columna unidad* de la hoja Productos (ej. UN, CAJA, ML).');
        $sheet->mergeCells('A2:B2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '6B7280']],
        ]);

        $sheet->setCellValue('A4', 'codigo');
        $sheet->setCellValue('B4', 'nombre');
        $sheet->getStyle('A4:B4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0E5236'],
            ],
        ]);

        $unidades = UnidadMedidaOpciones::forProductoForm();
        $row = 5;
        foreach ($unidades as $u) {
            $sheet->setCellValue("A{$row}", (string) $u['codigo']);
            $sheet->setCellValue("B{$row}", (string) $u['nombre']);
            $row++;
        }

        $endRow = max(5, $row - 1);
        $table = new Table("A4:B{$endRow}", 'TablaUnidades');
        $style = new TableStyle();
        $style->setTheme(TableStyle::TABLE_STYLE_MEDIUM2);
        $style->setShowRowStripes(true);
        $table->setStyle($style);
        $sheet->addTable($table);

        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->freezePane('A5');
    }
}
