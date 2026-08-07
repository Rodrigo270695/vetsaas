<?php

namespace App\Exports;

use App\Models\FelDocument;
use App\Models\FelSerie;
use App\Support\Fel\FelDocumentApisunatModeResolver;
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

/**
 * Export XLSX de comprobantes electrónicos (misma familia visual que {@see VentasXlsxExport}).
 *
 * Pensado para cruces contables: número CPE, serie/correlativo, receptor,
 * importes desagregados y la venta interna que originó el comprobante.
 */
class FelDocumentsXlsxExport
{
    /** @var array<int, string> Códigos SUNAT de tipo de documento del receptor. */
    private const TIPO_DOC_LABELS = [
        0 => 'Otros',
        1 => 'DNI',
        4 => 'Carné de extranjería',
        6 => 'RUC',
        7 => 'Pasaporte',
    ];

    private const METODO_PAGO_LABELS = [
        'efectivo' => 'Efectivo',
        'yape' => 'Yape',
        'plin' => 'Plin',
        'tarjeta' => 'Tarjeta',
        'transferencia' => 'Transferencia',
    ];

    /**
     * @var array<int, array{label: string, value: \Closure(FelDocument): mixed}>
     */
    private array $columns;

    public function __construct()
    {
        $this->columns = [
            [
                'label' => 'Número CPE',
                'value' => fn (FelDocument $d) => (string) $d->numero_completo,
            ],
            [
                'label' => 'Tipo',
                'value' => fn (FelDocument $d) => FelSerie::labelTipo((int) $d->tipo_comprobante),
            ],
            [
                'label' => 'Serie',
                'value' => fn (FelDocument $d) => (string) $d->serie,
            ],
            [
                'label' => 'Correlativo',
                'value' => fn (FelDocument $d) => (string) $d->correlativo,
            ],
            [
                'label' => 'Emisión',
                'value' => fn (FelDocument $d) => optional($d->emitido_at ?? $d->created_at)->format('Y-m-d H:i') ?? '',
            ],
            [
                'label' => 'Modo',
                'value' => fn (FelDocument $d) => match (FelDocumentApisunatModeResolver::resolve($d)) {
                    'produccion' => 'Producción',
                    'sandbox' => 'Prueba',
                    default => '',
                },
            ],
            [
                'label' => 'Estado',
                'value' => fn (FelDocument $d) => ucfirst((string) $d->estado),
            ],
            [
                'label' => 'Tipo doc. receptor',
                'value' => fn (FelDocument $d) => self::TIPO_DOC_LABELS[(int) $d->receptor_tipo_doc] ?? (string) $d->receptor_tipo_doc,
            ],
            [
                'label' => 'Doc. receptor',
                'value' => fn (FelDocument $d) => (string) $d->receptor_num_doc,
            ],
            [
                'label' => 'Cliente',
                'value' => fn (FelDocument $d) => (string) $d->receptor_nombre,
            ],
            [
                'label' => 'Subtotal',
                'value' => fn (FelDocument $d) => (string) $d->subtotal,
            ],
            [
                'label' => 'IGV',
                'value' => fn (FelDocument $d) => (string) $d->igv_monto,
            ],
            [
                'label' => 'Total',
                'value' => fn (FelDocument $d) => (string) $d->total,
            ],
            [
                'label' => 'Moneda',
                'value' => fn (FelDocument $d) => (string) ($d->moneda ?? ''),
            ],
            [
                'label' => 'Venta interna',
                'value' => fn (FelDocument $d) => (string) ($d->venta?->numero ?? ''),
            ],
            [
                'label' => 'Estado venta',
                'value' => fn (FelDocument $d) => (string) ($d->venta?->estado ?? ''),
            ],
            [
                'label' => 'Método pago',
                'value' => function (FelDocument $d): string {
                    $metodo = trim((string) ($d->venta?->metodo_pago ?? ''));

                    if ($metodo === '') {
                        return '';
                    }

                    return self::METODO_PAGO_LABELS[$metodo] ?? ucfirst($metodo);
                },
            ],
            [
                'label' => 'Sede',
                'value' => fn (FelDocument $d) => (string) ($d->venta?->sede?->nombre ?? ''),
            ],
            [
                'label' => 'Código sede',
                'value' => fn (FelDocument $d) => (string) ($d->venta?->sede?->codigo ?? ''),
            ],
            [
                'label' => 'Anulado',
                'value' => fn (FelDocument $d) => optional($d->anulado_at)->format('Y-m-d H:i') ?? '',
            ],
            [
                'label' => 'Observación SUNAT',
                'value' => fn (FelDocument $d) => (string) ($d->error_mensaje ?? ''),
            ],
        ];
    }

    /**
     * @param  Builder<FelDocument>  $query
     */
    public function streamTo(Builder $query, string $output = 'php://output'): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('VetSaaS')
            ->setTitle('Comprobantes emitidos')
            ->setSubject('Comprobantes electrónicos por rango de fechas');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Comprobantes');

        $columnCount = count($this->columns);
        $lastColumnLetter = Coordinate::stringFromColumnIndex($columnCount);

        $sheet->setCellValue('A1', 'Comprobantes emitidos');
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
        /** @var FelDocument $documento */
        foreach ($query->cursor() as $documento) {
            foreach ($this->columns as $index => $col) {
                $colLetter = Coordinate::stringFromColumnIndex($index + 1);
                $value = ($col['value'])($documento);
                $sheet->setCellValueExplicit(
                    "{$colLetter}{$row}",
                    $value,
                    DataType::TYPE_STRING,
                );
            }
            $row++;
        }

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
        $table = new Table($tableRange, 'TablaComprobantes');
        $table->setStyle(new TableStyle(TableStyle::TABLE_STYLE_MEDIUM2));
        $sheet->addTable($table);
    }
}
