<?php

namespace App\Exports;

use App\Models\SubscriptionPayment;
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
 * Export XLSX para Cobros (Plataforma → subscription_payments).
 *
 * Hermano de los exports de Sedes/Roles/Usuarios/Tenants/Planes/Suscripciones.
 * Pensado para conciliación contable: incluye monto, IGV, total, pasarela
 * y número FEL.
 */
class SubscriptionPaymentsXlsxExport
{
    /**
     * @var array<int, array{label: string, value: \Closure(SubscriptionPayment): mixed}>
     */
    private array $columns;

    public function __construct()
    {
        $this->columns = [
            [
                'label' => 'Fecha pago',
                'value' => fn (SubscriptionPayment $p) => $p->pagado_at
                    ? $p->pagado_at->format('Y-m-d H:i')
                    : optional($p->created_at)->format('Y-m-d H:i'),
            ],
            [
                'label' => 'Tenant',
                'value' => fn (SubscriptionPayment $p) => (string) ($p->tenant?->razon_social ?? '—'),
            ],
            [
                'label' => 'Slug',
                'value' => fn (SubscriptionPayment $p) => (string) ($p->tenant?->slug ?? '—'),
            ],
            [
                'label' => 'Plan',
                'value' => fn (SubscriptionPayment $p) => (string) ($p->plan?->nombre ?? '—'),
            ],
            [
                'label' => 'Estado',
                'value' => fn (SubscriptionPayment $p) => match ($p->estado) {
                    'sin_cobro' => 'Sin cobro registrado',
                    default => ucfirst((string) $p->estado),
                },
            ],
            [
                'label' => 'Monto',
                'value' => fn (SubscriptionPayment $p) => number_format((float) $p->monto, 2, '.', ','),
            ],
            [
                'label' => 'IGV',
                'value' => fn (SubscriptionPayment $p) => number_format((float) $p->igv_monto, 2, '.', ','),
            ],
            [
                'label' => 'Descuento',
                'value' => fn (SubscriptionPayment $p) => number_format((float) $p->descuento_monto, 2, '.', ','),
            ],
            [
                'label' => 'Total',
                'value' => fn (SubscriptionPayment $p) => number_format((float) $p->total, 2, '.', ','),
            ],
            [
                'label' => 'Moneda',
                'value' => fn (SubscriptionPayment $p) => (string) $p->moneda,
            ],
            [
                'label' => 'Pasarela',
                'value' => fn (SubscriptionPayment $p) => (string) ($p->pasarela ?? '—'),
            ],
            [
                'label' => 'Transaction ID',
                'value' => fn (SubscriptionPayment $p) => (string) ($p->pasarela_transaction_id ?? '—'),
            ],
            [
                'label' => 'Periodo inicio',
                'value' => fn (SubscriptionPayment $p) => optional($p->periodo_inicio)->format('Y-m-d') ?? '—',
            ],
            [
                'label' => 'Periodo fin',
                'value' => fn (SubscriptionPayment $p) => optional($p->periodo_fin)->format('Y-m-d') ?? '—',
            ],
            [
                'label' => 'FEL emitida',
                'value' => fn (SubscriptionPayment $p) => $p->fel_emitido ? 'Sí' : 'No',
            ],
            [
                'label' => 'FEL número',
                'value' => fn (SubscriptionPayment $p) => (string) ($p->fel_numero ?? '—'),
            ],
            [
                'label' => 'Reembolsado el',
                'value' => fn (SubscriptionPayment $p) => optional($p->refunded_at)->format('Y-m-d H:i') ?? '—',
            ],
            [
                'label' => 'Motivo reembolso',
                'value' => fn (SubscriptionPayment $p) => (string) ($p->refund_reason ?? '—'),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function streamFromRows(array $rows, string $output = 'php://output'): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('VetSaaS')
            ->setTitle('Cobros')
            ->setSubject('Listado de cobros de suscripciones');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cobros');

        $columnCount = count($this->columns);
        $lastColumnLetter = Coordinate::stringFromColumnIndex($columnCount);

        $sheet->setCellValue('A1', 'Cobros');
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
                count($rows),
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
        $row = $dataStartRow;

        foreach ($this->columns as $index => $col) {
            $colLetter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue("{$colLetter}{$headerRow}", $col['label']);
        }

        foreach ($rows as $rowData) {
            $payment = $this->hydratePayment($rowData);

            foreach ($this->columns as $index => $col) {
                $colLetter = Coordinate::stringFromColumnIndex($index + 1);
                $value = ($col['value'])($payment);
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

    /**
     * @param  array<string, mixed>  $row
     */
    private function hydratePayment(array $row): SubscriptionPayment
    {
        $payment = new SubscriptionPayment();
        $payment->forceFill(collect($row)->except([
            'tenant',
            'plan',
            'subscription',
            'refundedBy',
            'has_payment_record',
        ])->all());

        if (isset($row['tenant']) && is_array($row['tenant'])) {
            $tenant = new \App\Models\Tenant($row['tenant']);
            $payment->setRelation('tenant', $tenant);
        }

        if (isset($row['plan']) && is_array($row['plan'])) {
            $plan = new \App\Models\Plan($row['plan']);
            $payment->setRelation('plan', $plan);
        }

        return $payment;
    }

    /**
     * @param  Builder<SubscriptionPayment>  $query  Idealmente eager-loadea tenant + plan.
     */
    public function streamTo(Builder $query, string $output = 'php://output'): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('VetSaaS')
            ->setTitle('Cobros')
            ->setSubject('Listado de cobros de suscripciones');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cobros');

        $columnCount = count($this->columns);
        $lastColumnLetter = Coordinate::stringFromColumnIndex($columnCount);

        $sheet->setCellValue('A1', 'Cobros');
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
        /** @var SubscriptionPayment $payment */
        foreach ($query->cursor() as $payment) {
            foreach ($this->columns as $index => $col) {
                $colLetter = Coordinate::stringFromColumnIndex($index + 1);
                $value = ($col['value'])($payment);
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
        $table = new Table($tableRange, 'TablaCobros');
        $table->setStyle(new TableStyle(TableStyle::TABLE_STYLE_MEDIUM2));
        $sheet->addTable($table);
    }
}
