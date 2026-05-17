<?php

namespace App\Exports;

use App\Models\Tenant;
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
 * Export XLSX para Tenants (Plataforma). Hermano de los exports de
 * Sedes/Roles/Usuarios: mismo header verde, freeze pane, autofilter
 * y autosize por columna.
 */
class TenantsXlsxExport
{
    /**
     * @var array<int, array{label: string, value: \Closure(Tenant): mixed}>
     */
    private array $columns;

    public function __construct()
    {
        $this->columns = [
            [
                'label' => 'Slug',
                'value' => fn (Tenant $t) => (string) $t->slug,
            ],
            [
                'label' => 'Razón social',
                'value' => fn (Tenant $t) => (string) $t->razon_social,
            ],
            [
                'label' => 'Nombre comercial',
                'value' => fn (Tenant $t) => (string) ($t->nombre_comercial ?? ''),
            ],
            [
                'label' => 'RUC',
                'value' => fn (Tenant $t) => (string) ($t->ruc ?? ''),
            ],
            [
                'label' => 'Email admin',
                'value' => fn (Tenant $t) => (string) $t->email_admin,
            ],
            [
                'label' => 'Teléfono',
                'value' => fn (Tenant $t) => (string) ($t->telefono ?? ''),
            ],
            [
                'label' => 'Estado',
                'value' => fn (Tenant $t) => ucfirst((string) $t->estado),
            ],
            [
                'label' => 'Plan',
                'value' => fn (Tenant $t) => (string) ($t->activeSubscription()?->plan?->nombre ?? '—'),
            ],
            [
                'label' => 'Schema',
                'value' => fn (Tenant $t) => (string) $t->schema_name,
            ],
            [
                'label' => 'Trial termina',
                'value' => fn (Tenant $t) => optional($t->trial_ends_at)->format('Y-m-d H:i') ?? '—',
            ],
            [
                'label' => 'Onboarding',
                'value' => fn (Tenant $t) => $t->onboarding_completado ? 'Completo' : "Paso {$t->onboarding_paso}/5",
            ],
            [
                'label' => 'Creado en',
                'value' => fn (Tenant $t) => optional($t->created_at)->format('Y-m-d H:i'),
            ],
        ];
    }

    /**
     * @param  Builder<Tenant>  $query  Query ya filtrada por el controller.
     *                                   Idealmente eager-loadea `subscriptions.plan`.
     */
    public function streamTo(Builder $query, string $output = 'php://output'): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('VetSaaS')
            ->setTitle('Tenants')
            ->setSubject('Listado de tenants');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tenants');

        $columnCount = count($this->columns);
        $lastColumnLetter = Coordinate::stringFromColumnIndex($columnCount);

        $sheet->setCellValue('A1', 'Tenants');
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

        foreach ($this->columns as $index => $col) {
            $colLetter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue("{$colLetter}{$headerRow}", $col['label']);
        }

        $row = $dataStartRow;
        /** @var Tenant $tenant */
        foreach ($query->cursor() as $tenant) {
            foreach ($this->columns as $index => $col) {
                $colLetter = Coordinate::stringFromColumnIndex($index + 1);
                $value = ($col['value'])($tenant);
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

        $sheet->freezePane('A'.($headerRow + 1));

        foreach (range('A', $lastColumnLetter) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($output);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
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
        $table = new Table($tableRange, 'TablaTenants');
        $table->setStyle(new TableStyle(TableStyle::TABLE_STYLE_MEDIUM2));
        $sheet->addTable($table);
    }
}
