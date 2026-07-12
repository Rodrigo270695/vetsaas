<?php

namespace App\Exports;

use App\Models\Sede;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Plantilla XLSX para carga masiva de stock por sede.
 *
 * Hojas:
 * - Stock: sede* + identificador de producto + cantidad*
 * - Catalogos: SEDES (Valor en lista)
 * - Campos obligatorios: guía
 */
class StockInventarioImportTemplateXlsx
{
    /**
     * @var list<string>
     */
    public const STOCK_HEADERS = [
        'sede*',
        'sku',
        'nombre',
        'codigo_barras',
        'cantidad*',
    ];

    private const HEADER_ROW = 1;

    private const DATA_START_ROW = 2;

    private const DATA_END_ROW = 501;

    /** @var array{start: int, end: int} */
    private array $sedesRange = ['start' => 0, 'end' => -1];

    private string $ejemploSede = '';

    public function streamTo(string $output = 'php://output'): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('VetSaaS')
            ->setTitle('Plantilla importación de stock')
            ->setSubject('Carga masiva de existencias por sede');

        $catalogos = $spreadsheet->getActiveSheet();
        $catalogos->setTitle('Catalogos');
        $this->fillCatalogosSheet($spreadsheet, $catalogos);

        $stock = $spreadsheet->createSheet(0);
        $stock->setTitle('Stock');
        $this->fillStockSheet($stock);

        $this->buildCamposObligatoriosSheet($spreadsheet);

        $spreadsheet->setActiveSheetIndexByName('Stock');

        $writer = new Xlsx($spreadsheet);
        $writer->save($output);
        $spreadsheet->disconnectWorksheets();
    }

    private function fillStockSheet(Worksheet $sheet): void
    {
        $headers = self::STOCK_HEADERS;
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));

        foreach ($headers as $index => $label) {
            $col = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue("{$col}".self::HEADER_ROW, $label);
        }

        $sheet->getStyle('A'.self::HEADER_ROW.":{$lastCol}".self::HEADER_ROW)->applyFromArray([
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
        $sheet->getRowDimension(self::HEADER_ROW)->setRowHeight(22);

        $example = [
            $this->ejemploSede !== '' ? $this->ejemploSede : 'SEDE-001',
                        'AMOX-EJEMPLO',
            'Ejemplo amoxicilina — bórrala',
            '',
            '10',
        ];
        foreach ($example as $index => $value) {
            $col = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue("{$col}".self::DATA_START_ROW, $value);
        }

        $sheet->getStyle('A'.self::DATA_START_ROW.":{$lastCol}".self::DATA_END_ROW)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FBF7F0'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E5E0D8'],
                ],
            ],
        ]);

        if ($this->sedesRange['end'] >= $this->sedesRange['start'] && $this->sedesRange['start'] > 0) {
            $this->applyListValidation($sheet, 'A', 'SEDES_LISTA');
        }

        foreach (range(1, count($headers)) as $i) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A'.self::HEADER_ROW.":{$lastCol}".self::HEADER_ROW);
    }

    private function fillCatalogosSheet(Spreadsheet $spreadsheet, Worksheet $sheet): void
    {
        $sheet->getTabColor()->setRGB('1F6E4A');
        $sheet->getStyle('A:D')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FBF7F0'],
            ],
        ]);

        $tenantId = Auth::user()?->tenant_id;
        $sedesQuery = Sede::query()
            ->where('activa', true)
            ->whereNull('deleted_at')
            ->orderBy('nombre');

        if ($tenantId !== null) {
            $sedesQuery->where('tenant_id', $tenantId);
        }

        $sedes = $sedesQuery->get(['id', 'nombre', 'codigo']);
        $sedeRows = $sedes
            ->map(static fn (Sede $s): array => [
                'codigo' => (string) $s->codigo,
                'nombre' => (string) $s->nombre,
                'valor' => (string) $s->nombre.' · '.(string) $s->codigo,
            ])
            ->all();

        if ($sedeRows === []) {
            $sedeRows = [[
                'codigo' => '',
                'nombre' => '(Sin sedes activas — créalas en Configuración → Sedes)',
                'valor' => '',
            ]];
        } else {
            $this->ejemploSede = $sedeRows[0]['valor'];
        }

        $row = $this->writeCatalogBlock($sheet, 1, 'SEDES', $sedeRows);
        $start = $row - count($sedeRows);
        $end = $row - 1;
        $this->sedesRange = $sedes->isNotEmpty()
            ? ['start' => $start, 'end' => $end]
            : ['start' => 0, 'end' => -1];

        foreach (['A', 'B', 'C', 'D'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        if ($this->sedesRange['end'] >= $this->sedesRange['start'] && $this->sedesRange['start'] > 0) {
            $spreadsheet->addNamedRange(new NamedRange(
                'SEDES_LISTA',
                $sheet,
                '$D$'.$this->sedesRange['start'].':$D$'.$this->sedesRange['end'],
            ));
        }
    }

    /**
     * @param  list<array{codigo: string, nombre: string, valor: string}>  $rows
     */
    private function writeCatalogBlock(Worksheet $sheet, int $startRow, string $title, array $rows): int
    {
        $sheet->setCellValue("A{$startRow}", $title);
        $sheet->mergeCells("A{$startRow}:D{$startRow}");
        $sheet->getStyle("A{$startRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1F6E4A']],
        ]);

        $headerRow = $startRow + 1;
        $sheet->setCellValue("A{$headerRow}", 'Código');
        $sheet->setCellValue("B{$headerRow}", 'Nombre');
        $sheet->setCellValue("C{$headerRow}", 'Referencia');
        $sheet->setCellValue("D{$headerRow}", 'Valor en lista');
        $sheet->getStyle("A{$headerRow}:D{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F6E4A'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        $r = $headerRow + 1;
        foreach ($rows as $item) {
            $sheet->setCellValueExplicit("A{$r}", $item['codigo'], DataType::TYPE_STRING);
            $sheet->setCellValue("B{$r}", $item['nombre']);
            $sheet->setCellValue("C{$r}", '');
            $sheet->setCellValue("D{$r}", $item['valor']);
            $r++;
        }

        return $r;
    }

    private function applyListValidation(Worksheet $sheet, string $column, string $namedRange): void
    {
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(false);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle('Valor no válido');
        $validation->setError('Selecciona una sede de la lista (hoja Catalogos).');
        $validation->setPromptTitle('Seleccionar sede');
        $validation->setPrompt('Elige la sede donde aplica el stock.');
        $validation->setFormula1("={$namedRange}");

        $sheet->setDataValidation(
            "{$column}".self::DATA_START_ROW.":{$column}".self::DATA_END_ROW,
            $validation,
        );
    }

    private function buildCamposObligatoriosSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Campos obligatorios');

        $sheet->setCellValue('A1', 'Campos de la hoja Stock');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1F6E4A']],
        ]);

        $sheet->setCellValue(
            'A2',
            'Los campos con * son obligatorios. Identifica el producto con sku, nombre o codigo_barras (al menos uno). La sede se elige con la lista (Catalogos → Valor en lista).',
        );
        $sheet->mergeCells('A2:C2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['wrapText' => true],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(40);

        $sheet->fromArray(
            [
                ['Campo', 'Obligatorio', 'Cómo completarlo'],
                ['sede*', 'Sí', 'Lista: Catalogos → SEDES (Valor en lista) o código de sede'],
                ['sku', 'Condicional', 'Preferido para localizar el producto'],
                ['nombre', 'Condicional', 'Si no hay SKU ni código de barras'],
                ['codigo_barras', 'Condicional', 'Alternativa a sku / nombre'],
                ['cantidad*', 'Sí', 'Número ≥ 0 (hasta 3 decimales). Define el stock absoluto en esa sede'],
            ],
            null,
            'A4',
        );

        $sheet->getStyle('A4:C4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F6E4A'],
            ],
        ]);

        foreach (['A', 'B', 'C'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
