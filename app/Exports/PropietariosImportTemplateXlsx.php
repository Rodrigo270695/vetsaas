<?php

namespace App\Exports;

use App\Support\Pacientes\PacienteEspecieRazaCatalogo;
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

/**
 * Plantilla unificada: una fila = una mascota.
 * Si un dueño tiene varias mascotas, se repiten sus datos en varias filas.
 */
class PropietariosImportTemplateXlsx
{
    /** @var list<string> */
    public const HEADERS = [
        'nombres*',
        'apellidos',
        'tipo_documento',
        'numero_documento',
        'razon_social',
        'email',
        'telefono',
        'telefono_alt',
        'direccion',
        'notas_propietario',
        'paciente_nombre*',
        'especie',
        'raza',
        'sexo',
        'fecha_nacimiento (DD/MM/AAAA)',
        'peso_kg',
        'microchip',
        'color',
        'esterilizado',
        'activo*',
        'notas_paciente',
    ];

    private const HEADER_ROW = 1;

    private const DATA_START_ROW = 2;

    private const DATA_END_ROW = 501;

    /** @var array{tipos: array{start: int, end: int}, si_no: array{start: int, end: int}, especies: array{start: int, end: int}, razas: array{start: int, end: int}, sexos: array{start: int, end: int}} */
    private array $catalogRanges = [
        'tipos' => ['start' => 0, 'end' => -1],
        'si_no' => ['start' => 0, 'end' => -1],
        'especies' => ['start' => 0, 'end' => -1],
        'razas' => ['start' => 0, 'end' => -1],
        'sexos' => ['start' => 0, 'end' => -1],
    ];

    public function streamTo(string $output = 'php://output'): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('VetSaaS')
            ->setTitle('Plantilla importación dueños y mascotas')
            ->setSubject('Carga masiva unificada de propietarios y pacientes');

        $catalogos = $spreadsheet->getActiveSheet();
        $catalogos->setTitle('Catalogos');
        $this->fillCatalogosSheet($spreadsheet, $catalogos);

        $sheet = $spreadsheet->createSheet(0);
        $sheet->setTitle('Importacion');
        $this->fillDataSheet($sheet);

        $this->buildGuideSheet($spreadsheet);
        $spreadsheet->setActiveSheetIndexByName('Importacion');

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

        $examples = [
            [
                'Ejemplo Ana',
                'Pérez',
                'DNI',
                '012345678',
                '',
                'ejemplo@correo.com',
                '0999999999',
                '',
                'Av. Ejemplo 123',
                '',
                'Max',
                'Perro',
                'Mestizo',
                'M',
                '15/01/2022',
                '12.5',
                '',
                'Marrón',
                'NO',
                'SI',
                'Primera mascota — fila de ejemplo, bórrala',
            ],
            [
                'Ejemplo Ana',
                'Pérez',
                'DNI',
                '012345678',
                '',
                'ejemplo@correo.com',
                '0999999999',
                '',
                'Av. Ejemplo 123',
                '',
                'Luna',
                'Gato',
                'Europeo común',
                'H',
                '20/03/2021',
                '4.2',
                '',
                'Gris',
                'SI',
                'SI',
                'Misma dueña, otra mascota — bórrala',
            ],
        ];

        foreach ($examples as $rowOffset => $example) {
            $excelRow = self::DATA_START_ROW + $rowOffset;
            foreach ($example as $i => $value) {
                $col = Coordinate::stringFromColumnIndex($i + 1);
                // Texto forzado: documento (D), teléfonos (G/H), fecha (O), microchip (Q)
                if (in_array($i, [3, 6, 7, 14, 16], true)) {
                    $sheet->setCellValueExplicit("{$col}{$excelRow}", $value, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue("{$col}{$excelRow}", $value);
                }
            }
        }

        // Evitar que Excel quite ceros a la izquierda en documento / teléfonos / microchip / fecha
        foreach (['D', 'G', 'H', 'O', 'Q'] as $textCol) {
            $sheet->getStyle("{$textCol}".self::DATA_START_ROW.":{$textCol}".self::DATA_END_ROW)
                ->getNumberFormat()
                ->setFormatCode('@');
        }

        $sheet->getStyle('A'.self::DATA_START_ROW.":{$lastCol}".self::DATA_END_ROW)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FBF7F0']],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E0D8']],
            ],
        ]);

        if ($this->catalogRanges['tipos']['start'] > 0) {
            $this->applyListValidation($sheet, 'C', 'TIPOS_DOC_LISTA', true);
        }
        // Especie y raza: lista sugerida, pero se permiten valores nuevos
        if ($this->catalogRanges['especies']['start'] > 0) {
            $this->applyListValidation($sheet, 'L', 'ESPECIES_LISTA', true, allowOtherValues: true);
        }
        if ($this->catalogRanges['razas']['start'] > 0) {
            $this->applyListValidation($sheet, 'M', 'RAZAS_LISTA', true, allowOtherValues: true);
        }
        $this->applyListValidation($sheet, 'N', 'SEXOS_LISTA', true);
        $this->applyListValidation($sheet, 'S', 'SI_NO_LISTA', true);
        $this->applyListValidation($sheet, 'T', 'SI_NO_LISTA');

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
            ['codigo' => 'U', 'nombre' => 'Desconocido', 'valor' => 'U'],
        ];
        $row = $this->writeBlock($sheet, $row + 2, 'SEXOS', $sexos);
        $this->catalogRanges['sexos'] = ['start' => $row - count($sexos), 'end' => $row - 1];

        foreach (['A', 'B', 'C', 'D'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $t = $this->catalogRanges['tipos'];
        $spreadsheet->addNamedRange(new NamedRange('TIPOS_DOC_LISTA', $sheet, '$D$'.$t['start'].':$D$'.$t['end']));
        $s = $this->catalogRanges['si_no'];
        $spreadsheet->addNamedRange(new NamedRange('SI_NO_LISTA', $sheet, '$D$'.$s['start'].':$D$'.$s['end']));
        $e = $this->catalogRanges['especies'];
        if ($e['start'] > 0) {
            $spreadsheet->addNamedRange(new NamedRange('ESPECIES_LISTA', $sheet, '$D$'.$e['start'].':$D$'.$e['end']));
        }
        $r = $this->catalogRanges['razas'];
        if ($r['start'] > 0) {
            $spreadsheet->addNamedRange(new NamedRange('RAZAS_LISTA', $sheet, '$D$'.$r['start'].':$D$'.$r['end']));
        }
        $x = $this->catalogRanges['sexos'];
        $spreadsheet->addNamedRange(new NamedRange('SEXOS_LISTA', $sheet, '$D$'.$x['start'].':$D$'.$x['end']));
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

    private function applyListValidation(
        Worksheet $sheet,
        string $column,
        string $namedRange,
        bool $allowBlank = false,
        bool $allowOtherValues = false,
    ): void {
        $v = new DataValidation();
        $v->setType(DataValidation::TYPE_LIST);
        $v->setErrorStyle(
            $allowOtherValues
                ? DataValidation::STYLE_INFORMATION
                : DataValidation::STYLE_STOP,
        );
        $v->setAllowBlank($allowBlank);
        $v->setShowDropDown(true);
        $v->setShowErrorMessage(! $allowOtherValues);
        if (! $allowOtherValues) {
            $v->setErrorTitle('Valor no válido');
            $v->setError('Selecciona un valor de la lista (hoja Catalogos).');
        }
        $v->setFormula1("={$namedRange}");
        $sheet->setDataValidation("{$column}".self::DATA_START_ROW.":{$column}".self::DATA_END_ROW, $v);
    }

    private function buildGuideSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Campos obligatorios');
        $sheet->setCellValue('A1', 'Carga unificada: dueño + mascota');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '1F6E4A']],
        ]);

        $lines = [
            ['', ''],
            ['Regla', 'Una fila = una mascota. Si un dueño tiene 2 mascotas, usa 2 filas con el mismo documento.'],
            ['Obligatorios', 'nombres*, paciente_nombre*, activo*'],
            ['Dueño existente', 'Si el documento ya existe en VetSaaS, se reutiliza y solo se crea la mascota.'],
            ['Dueño nuevo', 'Se crea el propietario y la mascota en la misma fila.'],
            ['Documento', 'tipo_documento + numero_documento (recomendado). Escribe el número como texto para no perder ceros al inicio.'],
            ['Sexo', 'M = macho, H = hembra, U = desconocido'],
            ['Fecha', 'DD/MM/AAAA (ej. 15/01/2022). Déjala como texto.'],
            ['Especie / raza', 'Puedes escribir valores nuevos (no hace falta que estén en Catalogos); quedarán en el sistema.'],
            ['Ejemplos', 'Borra las filas que empiezan con «Ejemplo» antes de importar.'],
            ['Máximo', '500 filas de datos por archivo.'],
        ];

        $r = 3;
        foreach ($lines as [$k, $v]) {
            $sheet->setCellValue("A{$r}", $k);
            $sheet->setCellValue("B{$r}", $v);
            $sheet->getStyle("A{$r}")->getFont()->setBold(true);
            $r++;
        }

        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(90);
    }
}
