<?php

namespace App\Exports;

use App\Models\Sede;
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
 * Generador del export XLSX para el módulo Sedes.
 *
 * Produce un archivo `.xlsx` con:
 *   - Bloque de metadatos (título + fecha de exportación + total).
 *   - Tabla nativa de Excel (`Table`) con estilo `MEDIUM 2`, filas
 *     alternadas, autofilter y headers en negrita.
 *   - Pane fija en la fila de encabezado para mantenerla visible.
 *   - Autosize por columna y formato amigable de booleanos / fechas.
 *
 * Está desacoplado del controller para que pueda probarse y reutilizarse
 * desde comandos artisan o jobs sin tener que tocar HTTP.
 */
class SedesXlsxExport
{
    /**
     * Definición de columnas: cabecera + closure que extrae el valor.
     *
     * @var array<int, array{label: string, value: \Closure(Sede): mixed}>
     */
    private array $columns;

    public function __construct()
    {
        $this->columns = [
            [
                'label' => 'Código',
                'value' => fn (Sede $s) => (string) $s->codigo,
            ],
            [
                'label' => 'Nombre',
                'value' => fn (Sede $s) => (string) $s->nombre,
            ],
            [
                'label' => 'Dirección',
                'value' => fn (Sede $s) => (string) ($s->direccion ?? ''),
            ],
            [
                'label' => 'Distrito',
                'value' => fn (Sede $s) => (string) ($s->distrito ?? ''),
            ],
            [
                'label' => 'Provincia',
                'value' => fn (Sede $s) => (string) ($s->provincia ?? ''),
            ],
            [
                'label' => 'Departamento',
                'value' => fn (Sede $s) => (string) ($s->departamento ?? ''),
            ],
            [
                'label' => 'Teléfono',
                'value' => fn (Sede $s) => (string) ($s->telefono ?? ''),
            ],
            [
                'label' => 'Email',
                'value' => fn (Sede $s) => (string) ($s->email ?? ''),
            ],
            [
                'label' => 'Estado',
                'value' => fn (Sede $s) => $s->activa ? 'Activa' : 'Inactiva',
            ],
            [
                'label' => 'Creada en',
                'value' => fn (Sede $s) => optional($s->created_at)->format('Y-m-d H:i'),
            ],
        ];
    }

    /**
     * Genera el archivo XLSX y lo escribe en el stream/handler dado
     * (`php://output` para descarga directa).
     *
     * @param  Builder<Sede>  $query  Query ya filtrada por el controller.
     */
    public function streamTo(Builder $query, string $output = 'php://output'): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('VetSaaS')
            ->setTitle('Sedes')
            ->setSubject('Listado de sedes');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sedes');

        $columnCount = count($this->columns);
        $lastColumnLetter = Coordinate::stringFromColumnIndex($columnCount);

        // ─── Bloque superior: título + metadatos ────────────────────────
        $sheet->setCellValue('A1', 'Sedes');
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

        // ─── Cabecera ───────────────────────────────────────────────────
        foreach ($this->columns as $index => $col) {
            $colLetter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue("{$colLetter}{$headerRow}", $col['label']);
        }

        // ─── Filas de datos (cursor para no cargar todo en memoria) ─────
        $row = $dataStartRow;
        /** @var Sede $sede */
        foreach ($query->cursor() as $sede) {
            foreach ($this->columns as $index => $col) {
                $colLetter = Coordinate::stringFromColumnIndex($index + 1);
                $value = ($col['value'])($sede);
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

        // Pane congelado: deja fijos los bloques superiores + cabecera.
        $sheet->freezePane('A'.($headerRow + 1));

        // Autosize cuando hay pocas columnas (es costoso para datasets grandes).
        foreach (range('A', $lastColumnLetter) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($output);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    /**
     * Aplica estilos al rango de la tabla:
     *   - Header con fondo verde corporativo y texto blanco.
     *   - Filas con bordes finos.
     *   - Tabla nativa de Excel para tener autofilter + alternancia visual.
     */
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
            $dataRange = "A".($headerRow + 1).":{$lastColumn}{$lastDataRow}";
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

        // Excel Table: aplica estilo nativo (alternancia + filtros + nombre).
        $tableRange = "A{$headerRow}:{$lastColumn}".max($headerRow + 1, $lastDataRow);
        $table = new Table($tableRange, 'TablaSedes');
        $table->setStyle(new TableStyle(TableStyle::TABLE_STYLE_MEDIUM2));
        $sheet->addTable($table);
    }
}
