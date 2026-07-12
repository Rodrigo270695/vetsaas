<?php

namespace App\Exports;

use App\Support\PropietarioTipoDocumento;
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

class PropietariosImportTemplateXlsx
{
    /** @var list<string> */
    public const HEADERS = [
        'nombres*',
        'activo*',
        'tipo_documento',
        'numero_documento',
        'apellidos',
        'razon_social',
        'email',
        'telefono',
        'telefono_alt',
        'direccion',
        'notas',
    ];

    private const HEADER_ROW = 1;

    private const DATA_START_ROW = 2;

    private const DATA_END_ROW = 501;

    /** @var array{tipos: array{start: int, end: int}, si_no: array{start: int, end: int}} */
    private array $catalogRanges = [
        'tipos' => ['start' => 0, 'end' => -1],
        'si_no' => ['start' => 0, 'end' => -1],
    ];

    public function streamTo(string $output = 'php://output'): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('VetSaaS')
            ->setTitle('Plantilla importación de propietarios')
            ->setSubject('Carga masiva de propietarios');

        $catalogos = $spreadsheet->getActiveSheet();
        $catalogos->setTitle('Catalogos');
        $this->fillCatalogosSheet($spreadsheet, $catalogos);

        $sheet = $spreadsheet->createSheet(0);
        $sheet->setTitle('Propietarios');
        $this->fillDataSheet($sheet);

        $this->buildGuideSheet($spreadsheet);
        $spreadsheet->setActiveSheetIndexByName('Propietarios');

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
            'Ejemplo Juan',
            'SI',
            'DNI',
            '12345678',
            'Pérez',
            '',
            'ejemplo@correo.com',
            '999999999',
            '',
            'Av. Ejemplo 123',
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

        $this->applyListValidation($sheet, 'B', 'SI_NO_LISTA');
        if ($this->catalogRanges['tipos']['start'] > 0) {
            $this->applyListValidation($sheet, 'C', 'TIPOS_DOC_LISTA', true);
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
        $tipoRows = array_map(
            static fn (string $t): array => ['codigo' => $t, 'nombre' => $t, 'valor' => $t],
            PropietarioTipoDocumento::VALUES,
        );
        $row = $this->writeBlock($sheet, 1, 'TIPOS_DOCUMENTO', $tipoRows);
        $this->catalogRanges['tipos'] = ['start' => $row - count($tipoRows), 'end' => $row - 1];

        $siNo = [
            ['codigo' => 'SI', 'nombre' => 'Sí', 'valor' => 'SI'],
            ['codigo' => 'NO', 'nombre' => 'No', 'valor' => 'NO'],
        ];
        $row = $this->writeBlock($sheet, $row + 2, 'SI_NO', $siNo);
        $this->catalogRanges['si_no'] = ['start' => $row - count($siNo), 'end' => $row - 1];

        foreach (['A', 'B', 'C', 'D'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $t = $this->catalogRanges['tipos'];
        $spreadsheet->addNamedRange(new NamedRange('TIPOS_DOC_LISTA', $sheet, '$D$'.$t['start'].':$D$'.$t['end']));
        $s = $this->catalogRanges['si_no'];
        $spreadsheet->addNamedRange(new NamedRange('SI_NO_LISTA', $sheet, '$D$'.$s['start'].':$D$'.$s['end']));
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
        $sheet->setCellValue('A1', 'Campos de la hoja Propietarios');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1F6E4A']],
        ]);
        $sheet->fromArray(
            [
                ['Campo', 'Obligatorio', 'Cómo completarlo'],
                ['nombres*', 'Sí', 'Texto'],
                ['activo*', 'Sí', 'Lista SI / NO'],
                ['tipo_documento', 'No', 'Lista: DNI, RUC, CE, PAS, OTR'],
                ['numero_documento', 'No', 'Único junto con el tipo'],
                ['apellidos', 'No', 'Texto'],
                ['razon_social', 'No', 'Texto'],
                ['email', 'No', 'Email válido'],
                ['telefono', 'No', 'Texto'],
                ['telefono_alt', 'No', 'Texto'],
                ['direccion', 'No', 'Texto'],
                ['notas', 'No', 'Texto'],
            ],
            null,
            'A3',
        );
        $sheet->getStyle('A3:C3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F6E4A']],
        ]);
        foreach (['A', 'B', 'C'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
