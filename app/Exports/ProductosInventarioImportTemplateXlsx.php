<?php

namespace App\Exports;

use App\Models\CategoriaProducto;
use App\Models\Sede;
use App\Support\Inventario\UnidadMedidaOpciones;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
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
 * Plantilla XLSX para carga masiva de productos.
 *
 * Hojas:
 * - Productos: captura con listas desplegables en foráneas
 * - Catalogos: UNIDADES / CATEGORIAS / SEDES / SI_NO
 * - Campos obligatorios: guía de columnas
 */
class ProductosInventarioImportTemplateXlsx
{
    /**
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
        'sede',
        'cantidad_inicial',
        'numero_lote',
        'fecha_vencimiento',
    ];

    private const HEADER_ROW = 1;

    private const DATA_START_ROW = 2;

    private const DATA_END_ROW = 201;

    /** @var array{unidades: array{start: int, end: int}, categorias: array{start: int, end: int}, sedes: array{start: int, end: int}, si_no: array{start: int, end: int}} */
    private array $catalogRanges = [
        'unidades' => ['start' => 0, 'end' => -1],
        'categorias' => ['start' => 0, 'end' => -1],
        'sedes' => ['start' => 0, 'end' => -1],
        'si_no' => ['start' => 0, 'end' => -1],
    ];

    private string $ejemploUnidad = 'UN - Unidad';

    private string $ejemploSede = '';

    public function streamTo(string $output = 'php://output'): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('VetSaaS')
            ->setTitle('Plantilla importación de productos')
            ->setSubject('Carga masiva de productos de inventario');

        // Catalogos primero (rangos nombrados para las listas).
        $catalogos = $spreadsheet->getActiveSheet();
        $catalogos->setTitle('Catalogos');
        $this->fillCatalogosSheet($spreadsheet, $catalogos);

        $productos = $spreadsheet->createSheet(0);
        $productos->setTitle('Productos');
        $this->fillProductosSheet($productos);

        $this->buildCamposObligatoriosSheet($spreadsheet);

        $spreadsheet->setActiveSheetIndexByName('Productos');

        $writer = new Xlsx($spreadsheet);
        $writer->save($output);
        $spreadsheet->disconnectWorksheets();
    }

    private function fillProductosSheet(Worksheet $sheet): void
    {
        $headers = self::PRODUCTO_HEADERS;
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
            'Ejemplo amoxicilina',
            $this->ejemploUnidad,
            'SI',
            'SI',
            '',
            'AMOX-500',
            '',
            '25.50',
            '12.00',
            '5',
            'Fila de ejemplo — bórrala o cámbiala',
            $this->ejemploSede,
            '10',
            'LOTE-EJEMPLO',
            '2027-12-31',
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

        // B = unidad*, C = medicamento*, D = activo*, E = categoria, L = sede
        $this->applyListValidation($sheet, 'B', 'UNIDADES_LISTA');
        $this->applyListValidation($sheet, 'C', 'SI_NO_LISTA');
        $this->applyListValidation($sheet, 'D', 'SI_NO_LISTA');
        if ($this->catalogRanges['categorias']['end'] >= $this->catalogRanges['categorias']['start']
            && $this->catalogRanges['categorias']['start'] > 0) {
            $this->applyListValidation($sheet, 'E', 'CATEGORIAS_LISTA', allowBlank: true);
        }
        if ($this->catalogRanges['sedes']['end'] >= $this->catalogRanges['sedes']['start']
            && $this->catalogRanges['sedes']['start'] > 0) {
            $this->applyListValidation($sheet, 'L', 'SEDES_LISTA', allowBlank: true);
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

        $row = 1;
        $unidades = UnidadMedidaOpciones::forProductoForm();
        $unidadRows = array_map(
            static fn (array $u): array => [
                'codigo' => (string) $u['codigo'],
                'nombre' => (string) $u['nombre'],
                'valor' => (string) $u['codigo'].' - '.(string) $u['nombre'],
            ],
            $unidades,
        );
        if ($unidadRows !== []) {
            $this->ejemploUnidad = $unidadRows[0]['valor'];
        }
        $row = $this->writeCatalogBlock($sheet, $row, 'UNIDADES', $unidadRows);
        $this->catalogRanges['unidades'] = [
            'start' => $row - count($unidadRows),
            'end' => $row - 1,
        ];

        $row += 2;

        $query = CategoriaProducto::query()->where('activo', true);
        if (Schema::hasColumn('categorias_productos', 'orden')) {
            $query->orderBy('orden');
        }
        $categorias = $query->orderBy('nombre')->get(['id', 'nombre']);
        $categoriaRows = $categorias
            ->map(static fn (CategoriaProducto $c): array => [
                'codigo' => (string) $c->id,
                'nombre' => (string) $c->nombre,
                'valor' => (string) $c->nombre,
            ])
            ->all();

        if ($categoriaRows === []) {
            $categoriaRows = [[
                'codigo' => '',
                'nombre' => '(Sin categorías — créalas en Inventario → Categorías)',
                'valor' => '',
            ]];
        }

        $row = $this->writeCatalogBlock($sheet, $row, 'CATEGORIAS', $categoriaRows);
        $catStart = $row - count($categoriaRows);
        $catEnd = $row - 1;
        $this->catalogRanges['categorias'] = $categorias->isNotEmpty()
            ? ['start' => $catStart, 'end' => $catEnd]
            : ['start' => 0, 'end' => -1];

        $row += 2;

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

        $row = $this->writeCatalogBlock($sheet, $row, 'SEDES', $sedeRows);
        $sedeStart = $row - count($sedeRows);
        $sedeEnd = $row - 1;
        $this->catalogRanges['sedes'] = $sedes->isNotEmpty()
            ? ['start' => $sedeStart, 'end' => $sedeEnd]
            : ['start' => 0, 'end' => -1];

        $row += 2;

        $siNoRows = [
            ['codigo' => 'SI', 'nombre' => 'Sí', 'valor' => 'SI'],
            ['codigo' => 'NO', 'nombre' => 'No', 'valor' => 'NO'],
        ];
        $row = $this->writeCatalogBlock($sheet, $row, 'SI_NO', $siNoRows);
        $this->catalogRanges['si_no'] = [
            'start' => $row - count($siNoRows),
            'end' => $row - 1,
        ];

        foreach (['A', 'B', 'C', 'D'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $this->registerNamedRanges($spreadsheet, $sheet);
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
            $sheet->setCellValueExplicit(
                "A{$r}",
                $item['codigo'],
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING,
            );
            $sheet->setCellValue("B{$r}", $item['nombre']);
            $sheet->setCellValue("C{$r}", '');
            $sheet->setCellValue("D{$r}", $item['valor']);
            $r++;
        }

        return $r;
    }

    private function registerNamedRanges(Spreadsheet $spreadsheet, Worksheet $catalogos): void
    {
        $u = $this->catalogRanges['unidades'];
        if ($u['end'] >= $u['start'] && $u['start'] > 0) {
            $spreadsheet->addNamedRange(new NamedRange(
                'UNIDADES_LISTA',
                $catalogos,
                '$D$'.$u['start'].':$D$'.$u['end'],
            ));
        }

        $c = $this->catalogRanges['categorias'];
        if ($c['end'] >= $c['start'] && $c['start'] > 0) {
            $spreadsheet->addNamedRange(new NamedRange(
                'CATEGORIAS_LISTA',
                $catalogos,
                '$D$'.$c['start'].':$D$'.$c['end'],
            ));
        }

        $sede = $this->catalogRanges['sedes'];
        if ($sede['end'] >= $sede['start'] && $sede['start'] > 0) {
            $spreadsheet->addNamedRange(new NamedRange(
                'SEDES_LISTA',
                $catalogos,
                '$D$'.$sede['start'].':$D$'.$sede['end'],
            ));
        }

        $s = $this->catalogRanges['si_no'];
        if ($s['end'] >= $s['start'] && $s['start'] > 0) {
            $spreadsheet->addNamedRange(new NamedRange(
                'SI_NO_LISTA',
                $catalogos,
                '$D$'.$s['start'].':$D$'.$s['end'],
            ));
        }
    }

    private function applyListValidation(
        Worksheet $sheet,
        string $column,
        string $namedRange,
        bool $allowBlank = false,
    ): void {
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank($allowBlank);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle('Valor no válido');
        $validation->setError('Selecciona un valor de la lista (hoja Catalogos).');
        $validation->setPromptTitle('Seleccionar');
        $validation->setPrompt('Elige un valor del catálogo.');
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

        $sheet->setCellValue('A1', 'Campos de la hoja Productos');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1F6E4A']],
        ]);

        $sheet->setCellValue(
            'A2',
            'Los campos con * son obligatorios. Stock inicial es opcional: si pones sede y cantidad_inicial se crea entrada con lote/vencimiento. Las foráneas se eligen con la lista (Catalogos → Valor en lista).',
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
                ['nombre*', 'Sí', 'Texto libre'],
                ['unidad*', 'Sí', 'Lista: Catalogos → UNIDADES (Valor en lista)'],
                ['medicamento*', 'Sí', 'Lista: SI / NO'],
                ['activo*', 'Sí', 'Lista: SI / NO'],
                ['categoria', 'No', 'Lista: Catalogos → CATEGORIAS (Valor en lista)'],
                ['sku', 'No', 'Único en el catálogo'],
                ['codigo_barras', 'No', 'Texto'],
                ['precio_venta', 'No', 'Número ≥ 0'],
                ['precio_compra', 'No', 'Número ≥ 0'],
                ['stock_minimo', 'No', 'Número ≥ 0'],
                ['descripcion', 'No', 'Texto'],
                ['sede', 'Condicional', 'Lista SEDES. Requerida si hay cantidad_inicial'],
                ['cantidad_inicial', 'Condicional', 'Número > 0. Requiere sede'],
                ['numero_lote', 'No', 'Texto (opcional con stock inicial)'],
                ['fecha_vencimiento', 'No', 'YYYY-MM-DD o DD/MM/YYYY'],
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
