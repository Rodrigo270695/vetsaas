<?php

namespace App\Exports;

use App\Models\Paciente;
use App\Models\Propietario;
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

class PropietariosXlsxExport
{
    /** @var list<array{label: string, value: \Closure(Propietario): mixed}> */
    private array $ownerColumns;

    /** @var list<array{label: string, value: \Closure(?Paciente): mixed}> */
    private array $petColumns;

    public function __construct(
        private readonly bool $includeMascotas = false,
    ) {
        $this->ownerColumns = [
            ['label' => 'Nombres', 'value' => fn (Propietario $p) => (string) $p->nombres],
            ['label' => 'Apellidos', 'value' => fn (Propietario $p) => (string) ($p->apellidos ?? '')],
            ['label' => 'Razón social', 'value' => fn (Propietario $p) => (string) ($p->razon_social ?? '')],
            ['label' => 'Doc.', 'value' => fn (Propietario $p) => trim((string) ($p->tipo_documento ?? '').' '.(string) ($p->numero_documento ?? ''))],
            ['label' => 'Email', 'value' => fn (Propietario $p) => (string) ($p->email ?? '')],
            ['label' => 'Teléfono', 'value' => fn (Propietario $p) => (string) ($p->telefono ?? '')],
            ['label' => 'Distrito', 'value' => fn (Propietario $p) => (string) ($p->distrito ?? '')],
            [
                'label' => $includeMascotas ? 'Estado dueño' : 'Estado',
                'value' => fn (Propietario $p) => $p->activo ? 'Activo' : 'Inactivo',
            ],
            ['label' => 'Registrado', 'value' => fn (Propietario $p) => optional($p->created_at)->format('Y-m-d H:i')],
        ];

        $this->petColumns = [
            ['label' => 'Mascota', 'value' => fn (?Paciente $m) => (string) ($m?->nombre ?? '')],
            ['label' => 'Especie', 'value' => fn (?Paciente $m) => (string) ($m?->especie ?? '')],
            ['label' => 'Raza', 'value' => fn (?Paciente $m) => (string) ($m?->raza ?? '')],
            ['label' => 'Sexo', 'value' => fn (?Paciente $m) => (string) ($m?->sexo ?? '')],
            ['label' => 'Fecha nacimiento', 'value' => fn (?Paciente $m) => $m?->fecha_nacimiento?->format('d/m/Y') ?? ''],
            ['label' => 'Peso (kg)', 'value' => fn (?Paciente $m) => (string) ($m?->peso_kg ?? '')],
            ['label' => 'Microchip', 'value' => fn (?Paciente $m) => (string) ($m?->microchip ?? '')],
            ['label' => 'Color', 'value' => fn (?Paciente $m) => (string) ($m?->color ?? '')],
            ['label' => 'Esterilizado', 'value' => function (?Paciente $m): string {
                if ($m === null || $m->esterilizado === null) {
                    return '';
                }

                return $m->esterilizado ? 'SI' : 'NO';
            }],
            ['label' => 'Estado mascota', 'value' => function (?Paciente $m): string {
                if ($m === null) {
                    return '';
                }

                return $m->activo ? 'Activo' : 'Inactivo';
            }],
        ];
    }

    /** @param  Builder<Propietario>  $query */
    public function streamTo(Builder $query, string $output = 'php://output'): void
    {
        if ($this->includeMascotas) {
            $query->with(['pacientes' => fn ($q) => $q->orderBy('nombre')]);
        }

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('VetSaaS')
            ->setTitle($this->includeMascotas ? 'Propietarios y mascotas' : 'Propietarios')
            ->setSubject(
                $this->includeMascotas
                    ? 'Listado de propietarios con mascotas'
                    : 'Listado de propietarios',
            );

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->includeMascotas ? 'Dueños y mascotas' : 'Propietarios');

        $columnCount = count($this->ownerColumns) + ($this->includeMascotas ? count($this->petColumns) : 0);
        $lastColumnLetter = Coordinate::stringFromColumnIndex($columnCount);

        $title = $this->includeMascotas ? 'Propietarios y mascotas' : 'Propietarios';
        $sheet->setCellValue('A1', $title);
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

        $colIndex = 1;
        foreach ($this->ownerColumns as $col) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue("{$colLetter}{$headerRow}", $col['label']);
            $colIndex++;
        }
        if ($this->includeMascotas) {
            foreach ($this->petColumns as $col) {
                $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                $sheet->setCellValue("{$colLetter}{$headerRow}", $col['label']);
                $colIndex++;
            }
        }

        $row = $dataStartRow;
        $rowsWritten = 0;

        $models = $this->includeMascotas
            ? $query->lazy(100)
            : $query->cursor();

        /** @var Propietario $model */
        foreach ($models as $model) {
            if ($this->includeMascotas) {
                $pets = $model->pacientes;
                if ($pets->isEmpty()) {
                    $this->writeRow($sheet, $row, $model, null);
                    $row++;
                    $rowsWritten++;
                } else {
                    foreach ($pets as $pet) {
                        $this->writeRow($sheet, $row, $model, $pet);
                        $row++;
                        $rowsWritten++;
                    }
                }
            } else {
                $this->writeRow($sheet, $row, $model, null);
                $row++;
                $rowsWritten++;
            }
        }

        $sheet->setCellValue(
            'A2',
            sprintf(
                'Exportado el %s · %d %s%s',
                now()->format('d/m/Y H:i'),
                $rowsWritten,
                $this->includeMascotas ? 'filas' : 'registros',
                $this->includeMascotas ? ' (una por mascota; dueño sin mascota = 1 fila)' : '',
            ),
        );

        $lastDataRow = max($dataStartRow, $row - 1);

        $this->styleTable($sheet, $lastColumnLetter, $headerRow, $lastDataRow);

        $sheet->freezePane('A'.($headerRow + 1));

        for ($i = 1; $i <= $columnCount; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($output);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    private function writeRow(
        Worksheet $sheet,
        int $row,
        Propietario $owner,
        ?Paciente $pet,
    ): void {
        $colIndex = 1;
        foreach ($this->ownerColumns as $col) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValueExplicit(
                "{$colLetter}{$row}",
                ($col['value'])($owner),
                DataType::TYPE_STRING,
            );
            $colIndex++;
        }

        if (! $this->includeMascotas) {
            return;
        }

        foreach ($this->petColumns as $col) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValueExplicit(
                "{$colLetter}{$row}",
                ($col['value'])($pet),
                DataType::TYPE_STRING,
            );
            $colIndex++;
        }
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
        $table = new Table($tableRange, 'TablaPropietarios');
        $table->setStyle(new TableStyle(TableStyle::TABLE_STYLE_MEDIUM2));
        $sheet->addTable($table);
    }
}
