<?php

namespace App\Exports;

use App\Models\AuditLog;
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

class AuditLogsXlsxExport
{
    /** @var array<int, array{label: string, value: \Closure(AuditLog): mixed}> */
    private array $columns;

    public function __construct()
    {
        $this->columns = [
            ['label' => 'Fecha', 'value' => fn (AuditLog $l) => optional($l->created_at)->format('Y-m-d H:i:s')],
            ['label' => 'Usuario', 'value' => fn (AuditLog $l) => (string) ($l->usuario_nombre ?? '')],
            ['label' => 'Email', 'value' => fn (AuditLog $l) => (string) ($l->usuario_email ?? '')],
            ['label' => 'Acción', 'value' => fn (AuditLog $l) => (string) $l->accion],
            ['label' => 'Módulo', 'value' => fn (AuditLog $l) => (string) $l->modulo],
            ['label' => 'Registro', 'value' => fn (AuditLog $l) => (string) ($l->registro_label ?? $l->registro_id ?? '')],
            ['label' => 'IP', 'value' => fn (AuditLog $l) => (string) ($l->ip_address ?? '')],
        ];
    }

    /** @param  Builder<AuditLog>  $query */
    public function streamTo(Builder $query, string $output = 'php://output'): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('VetSaaS')
            ->setTitle('Registro de actividad')
            ->setSubject('Auditoría del tenant');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Actividad');

        $columnCount = count($this->columns);
        $lastColumnLetter = Coordinate::stringFromColumnIndex($columnCount);

        foreach ($this->columns as $index => $column) {
            $cell = Coordinate::stringFromColumnIndex($index + 1).'1';
            $sheet->setCellValue($cell, $column['label']);
        }

        $this->styleHeaderRow($sheet, $lastColumnLetter);

        $row = 2;
        $query->chunk(500, function ($logs) use ($sheet, &$row): void {
            foreach ($logs as $log) {
                foreach ($this->columns as $index => $column) {
                    $cell = Coordinate::stringFromColumnIndex($index + 1).$row;
                    $sheet->setCellValue($cell, $column['value']($log));
                }
                $row++;
            }
        });

        $lastDataRow = max(1, $row - 1);
        $tableRange = 'A1:'.$lastColumnLetter.$lastDataRow;
        $table = new Table($tableRange, 'AuditLogs');
        $table->setShowHeaderRow(true);
        $table->setStyle((new TableStyle)->setTheme(TableStyle::TABLE_STYLE_MEDIUM2));
        $sheet->addTable($table);

        for ($i = 1; $i <= $columnCount; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($output);
    }

    private function styleHeaderRow(Worksheet $sheet, string $lastColumnLetter): void
    {
        $range = 'A1:'.$lastColumnLetter.'1';
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);
    }
}
