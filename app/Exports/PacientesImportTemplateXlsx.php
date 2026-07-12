<?php

namespace App\Exports;

use App\Models\Propietario;
use App\Support\Pacientes\PacienteEspecieRazaCatalogo;
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

class PacientesImportTemplateXlsx
{
    /** @var list<string> */
    public const HEADERS = [
        'nombre*',
        'propietario_documento*',
        'activo*',
        'especie',
        'raza',
        'sexo',
        'fecha_nacimiento',
        'peso_kg',
        'microchip',
        'color',
        'esterilizado',
        'notas',
    ];

    private const HEADER_ROW = 1;

    private const DATA_START_ROW = 2;

    private const DATA_END_ROW = 501;

    /** @var array{especies: array{start: int, end: int}, razas: array{start: int, end: int}, sexos: array{start: int, end: int}, si_no: array{start: int, end: int}, propietarios: array{start: int, end: int}} */
    private array $catalogRanges = [
        'especies' => ['start' => 0, 'end' => -1],
        'razas' => ['start' => 0, 'end' => -1],
        'sexos' => ['start' => 0, 'end' => -1],
        'si_no' => ['start' => 0, 'end' => -1],
        'propietarios' => ['start' => 0, 'end' => -1],
    ];

    private string $ejemploDoc = '';

    public function streamTo(string $output = 'php://output'): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('VetSaaS')
            ->setTitle('Plantilla importación de pacientes')
            ->setSubject('Carga masiva de mascotas');

        $catalogos = $spreadsheet->getActiveSheet();
        $catalogos->setTitle('Catalogos');
        $this->fillCatalogosSheet($spreadsheet, $catalogos);

        $sheet = $spreadsheet->createSheet(0);
        $sheet->setTitle('Pacientes');
        $this->fillDataSheet($sheet);

        $this->buildGuideSheet($spreadsheet);
        $spreadsheet->setActiveSheetIndexByName('Pacientes');

        (new Xlsx($spreadsheet))->save($output);
        $spreadsheet->disconnectWorksheets();
    }

    private function fillDataSheet(Worksheet $sheet): void
    {
        $headers = self::HEADERS;
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));

        foreach ($headers as $i => $label) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).self::HEADER_ROW, $label);
        }

        $sheet->getStyle('A'.self::HEADER_ROW.":{$lastCol}".self::HEADER_ROW)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F6E4A']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '0E5236']],
            ],
        ]);

        $example = [
            'Ejemplo Firulais',
            $this->ejemploDoc !== '' ? $this->ejemploDoc : 'DNI 12345678',
            'SI',
            'Perro',
            'Mestizo',
            'M',
            '2022-01-15',
            '12.5',
            '',
            'Marrón',
            'NO',
            'Fila de ejemplo — bórrala',
        ];
        foreach ($example as $i => $value) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).self::DATA_START_ROW, $value);
        }

        $sheet->getStyle('A'.self::DATA_START_ROW.":{$lastCol}".self::DATA_END_ROW)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FBF7F0']],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E0D8']],
            ],
        ]);

        $this->applyListValidation($sheet, 'C', 'SI_NO_LISTA');
        if ($this->catalogRanges['propietarios']['start'] > 0) {
            $this->applyListValidation($sheet, 'B', 'PROPIETARIOS_LISTA');
        }
        if ($this->catalogRanges['especies']['start'] > 0) {
            $this->applyListValidation($sheet, 'D', 'ESPECIES_LISTA', true);
        }
        if ($this->catalogRanges['razas']['start'] > 0) {
            $this->applyListValidation($sheet, 'E', 'RAZAS_LISTA', true);
        }
        $this->applyListValidation($sheet, 'F', 'SEXOS_LISTA', true);
        $this->applyListValidation($sheet, 'K', 'SI_NO_LISTA', true);

        foreach (range(1, count($headers)) as $i) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A'.self::HEADER_ROW.":{$lastCol}".self::HEADER_ROW);
    }

    private function fillCatalogosSheet(Spreadsheet $spreadsheet, Worksheet $sheet): void
    {
        $sheet->getTabColor()->setRGB('1F6E4A');

        $propRows = Propietario::query()
            ->where('activo', true)
            ->whereNotNull('numero_documento')
            ->where('numero_documento', '!=', '')
            ->orderBy('nombres')
            ->limit(400)
            ->get(['tipo_documento', 'numero_documento', 'nombres', 'apellidos', 'razon_social'])
            ->map(function (Propietario $p): array {
                $doc = trim(($p->tipo_documento ? $p->tipo_documento.' ' : '').(string) $p->numero_documento);
                $nombre = $p->displayName();

                return [
                    'codigo' => (string) $p->numero_documento,
                    'nombre' => $nombre,
                    'valor' => $doc,
                ];
            })
            ->all();

        if ($propRows === []) {
            $propRows = [[
                'codigo' => '',
                'nombre' => '(Sin propietarios con documento — impórtalos primero)',
                'valor' => '',
            ]];
        } else {
            $this->ejemploDoc = $propRows[0]['valor'];
        }

        $row = $this->writeBlock($sheet, 1, 'PROPIETARIOS', $propRows);
        $this->catalogRanges['propietarios'] = $propRows[0]['valor'] !== ''
            ? ['start' => $row - count($propRows), 'end' => $row - 1]
            : ['start' => 0, 'end' => -1];

        $especies = array_map(
            static fn (string $e): array => ['codigo' => $e, 'nombre' => $e, 'valor' => $e],
            PacienteEspecieRazaCatalogo::especies(),
        );
        $row = $this->writeBlock($sheet, $row + 2, 'ESPECIES', $especies);
        $this->catalogRanges['especies'] = ['start' => $row - count($especies), 'end' => $row - 1];

        $razas = array_map(
            static fn (string $r): array => ['codigo' => $r, 'nombre' => $r, 'valor' => $r],
            PacienteEspecieRazaCatalogo::razas(),
        );
        $row = $this->writeBlock($sheet, $row + 2, 'RAZAS', $razas);
        $this->catalogRanges['razas'] = ['start' => $row - count($razas), 'end' => $row - 1];

        $sexos = [
            ['codigo' => 'M', 'nombre' => 'Macho', 'valor' => 'M'],
            ['codigo' => 'H', 'nombre' => 'Hembra', 'valor' => 'H'],
            ['codigo' => 'U', 'nombre' => 'Indefinido', 'valor' => 'U'],
        ];
        $row = $this->writeBlock($sheet, $row + 2, 'SEXOS', $sexos);
        $this->catalogRanges['sexos'] = ['start' => $row - count($sexos), 'end' => $row - 1];

        $siNo = [
            ['codigo' => 'SI', 'nombre' => 'Sí', 'valor' => 'SI'],
            ['codigo' => 'NO', 'nombre' => 'No', 'valor' => 'NO'],
        ];
        $row = $this->writeBlock($sheet, $row + 2, 'SI_NO', $siNo);
        $this->catalogRanges['si_no'] = ['start' => $row - count($siNo), 'end' => $row - 1];

        foreach (['A', 'B', 'C', 'D'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $this->named($spreadsheet, $sheet, 'PROPIETARIOS_LISTA', 'propietarios');
        $this->named($spreadsheet, $sheet, 'ESPECIES_LISTA', 'especies');
        $this->named($spreadsheet, $sheet, 'RAZAS_LISTA', 'razas');
        $this->named($spreadsheet, $sheet, 'SEXOS_LISTA', 'sexos');
        $this->named($spreadsheet, $sheet, 'SI_NO_LISTA', 'si_no');
    }

    private function named(Spreadsheet $spreadsheet, Worksheet $sheet, string $name, string $key): void
    {
        $r = $this->catalogRanges[$key];
        if ($r['end'] >= $r['start'] && $r['start'] > 0) {
            $spreadsheet->addNamedRange(new NamedRange($name, $sheet, '$D$'.$r['start'].':$D$'.$r['end']));
        }
    }

    /**
     * @param  list<array{codigo: string, nombre: string, valor: string}>  $rows
     */
    private function writeBlock(Worksheet $sheet, int $startRow, string $title, array $rows): int
    {
        $sheet->setCellValue("A{$startRow}", $title);
        $sheet->mergeCells("A{$startRow}:D{$startRow}");
        $sheet->getStyle("A{$startRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1F6E4A']],
        ]);
        $h = $startRow + 1;
        $sheet->fromArray([['Código', 'Nombre', 'Referencia', 'Valor en lista']], null, "A{$h}");
        $sheet->getStyle("A{$h}:D{$h}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F6E4A']],
        ]);
        $r = $h + 1;
        foreach ($rows as $item) {
            $sheet->setCellValueExplicit("A{$r}", $item['codigo'], DataType::TYPE_STRING);
            $sheet->setCellValue("B{$r}", $item['nombre']);
            $sheet->setCellValue("D{$r}", $item['valor']);
            $r++;
        }

        return $r;
    }

    private function applyListValidation(Worksheet $sheet, string $column, string $namedRange, bool $allowBlank = false): void
    {
        $v = new DataValidation();
        $v->setType(DataValidation::TYPE_LIST);
        $v->setErrorStyle(DataValidation::STYLE_STOP);
        $v->setAllowBlank($allowBlank);
        $v->setShowDropDown(true);
        $v->setShowErrorMessage(true);
        $v->setErrorTitle('Valor no válido');
        $v->setError('Selecciona un valor de la lista (hoja Catalogos).');
        $v->setFormula1("={$namedRange}");
        $sheet->setDataValidation("{$column}".self::DATA_START_ROW.":{$column}".self::DATA_END_ROW, $v);
    }

    private function buildGuideSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Campos obligatorios');
        $sheet->setCellValue('A1', 'Campos de la hoja Pacientes');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1F6E4A']],
        ]);
        $sheet->setCellValue(
            'A2',
            'propietario_documento* debe coincidir con un titular existente (lista Catalogos → PROPIETARIOS). Formato: «DNI 12345678» o solo el número.',
        );
        $sheet->mergeCells('A2:C2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '6B7280']],
            'alignment' => ['wrapText' => true],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(36);
        $sheet->fromArray(
            [
                ['Campo', 'Obligatorio', 'Cómo completarlo'],
                ['nombre*', 'Sí', 'Nombre de la mascota'],
                ['propietario_documento*', 'Sí', 'Lista PROPIETARIOS o número de documento del titular'],
                ['activo*', 'Sí', 'Lista SI / NO'],
                ['especie', 'No', 'Lista o texto libre'],
                ['raza', 'No', 'Lista o texto libre'],
                ['sexo', 'No', 'M / H / U'],
                ['fecha_nacimiento', 'No', 'YYYY-MM-DD o DD/MM/YYYY'],
                ['peso_kg', 'No', 'Número ≥ 0'],
                ['microchip', 'No', 'Texto'],
                ['color', 'No', 'Texto'],
                ['esterilizado', 'No', 'SI / NO / vacío'],
                ['notas', 'No', 'Texto'],
            ],
            null,
            'A4',
        );
        $sheet->getStyle('A4:C4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F6E4A']],
        ]);
        foreach (['A', 'B', 'C'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
