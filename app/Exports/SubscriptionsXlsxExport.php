<?php

namespace App\Exports;

use App\Models\Subscription;
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
 * Export XLSX para Suscripciones (Plataforma). Hermano de los exports
 * de Sedes/Roles/Usuarios/Tenants/Planes.
 */
class SubscriptionsXlsxExport
{
    /**
     * @var array<int, array{label: string, value: \Closure(Subscription): mixed}>
     */
    private array $columns;

    public function __construct()
    {
        $this->columns = [
            [
                'label' => 'Tenant',
                'value' => fn (Subscription $s) => (string) ($s->tenant?->razon_social ?? '—'),
            ],
            [
                'label' => 'Slug',
                'value' => fn (Subscription $s) => (string) ($s->tenant?->slug ?? '—'),
            ],
            [
                'label' => 'Plan',
                'value' => fn (Subscription $s) => (string) ($s->plan?->nombre ?? '—'),
            ],
            [
                'label' => 'Código plan',
                'value' => fn (Subscription $s) => (string) ($s->plan?->codigo ?? '—'),
            ],
            [
                'label' => 'Estado',
                'value' => fn (Subscription $s) => ucfirst((string) $s->estado),
            ],
            [
                'label' => 'Ciclo',
                'value' => fn (Subscription $s) => ucfirst((string) $s->ciclo),
            ],
            [
                'label' => 'Precio pactado',
                'value' => fn (Subscription $s) => 'S/. '.number_format((float) $s->precio_pactado, 2, '.', ','),
            ],
            [
                'label' => 'Descuento %',
                'value' => fn (Subscription $s) => number_format((float) $s->descuento_pct, 2, '.', '').'%',
            ],
            [
                'label' => 'Trial termina',
                'value' => fn (Subscription $s) => optional($s->trial_ends_at)->format('Y-m-d') ?? '—',
            ],
            [
                'label' => 'Periodo actual',
                'value' => fn (Subscription $s) => $s->current_period_start && $s->current_period_end
                    ? $s->current_period_start->format('Y-m-d').' → '.$s->current_period_end->format('Y-m-d')
                    : '—',
            ],
            [
                'label' => 'Próximo cobro',
                'value' => fn (Subscription $s) => optional($s->proximo_cobro_at)->format('Y-m-d H:i') ?? '—',
            ],
            [
                'label' => 'Creado en',
                'value' => fn (Subscription $s) => optional($s->created_at)->format('Y-m-d H:i'),
            ],
        ];
    }

    /**
     * @param  Builder<Subscription>  $query  Idealmente eager-loadea tenant + plan.
     */
    public function streamTo(Builder $query, string $output = 'php://output'): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('VetSaaS')
            ->setTitle('Suscripciones')
            ->setSubject('Listado de suscripciones');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Suscripciones');

        $columnCount = count($this->columns);
        $lastColumnLetter = Coordinate::stringFromColumnIndex($columnCount);

        $sheet->setCellValue('A1', 'Suscripciones');
        $sheet->mergeCells("A1:{$lastColumnLetter}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '0E5236']],
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
            'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '6B7280']],
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
        /** @var Subscription $subscription */
        foreach ($query->cursor() as $subscription) {
            foreach ($this->columns as $index => $col) {
                $colLetter = Coordinate::stringFromColumnIndex($index + 1);
                $value = ($col['value'])($subscription);
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
        $table = new Table($tableRange, 'TablaSuscripciones');
        $table->setStyle(new TableStyle(TableStyle::TABLE_STYLE_MEDIUM2));
        $sheet->addTable($table);
    }
}
