<?php

namespace App\Exports;

use App\Models\Paciente;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\User;
use App\Models\VacunaAplicada;
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
 * Plantilla de carga masiva de vacunaciones / aplicaciones.
 * Catálogo MASCOTAS: lista desplegable armada desde pacientes activos del tenant.
 */
class VacunasAplicadasImportTemplateXlsx
{
    /** @var list<string> */
    public const HEADERS = [
        'paciente*',
        'nombre_vacuna*',
        'categoria*',
        'aplicada_at* (DD/MM/AAAA o DD/MM/AAAA HH:MM)',
        'fecha_proxima (DD/MM/AAAA)',
        'numero_dosis',
        'lote',
        'producto_sku',
        'sede_codigo',
        'veterinario',
        'esquema_antigenos',
        'notas',
    ];

    private const HEADER_ROW = 1;

    private const DATA_START_ROW = 2;

    private const DATA_END_ROW = 501;

    /** @var array{mascotas: array{start: int, end: int}, categorias: array{start: int, end: int}, productos: array{start: int, end: int}, sedes: array{start: int, end: int}, veterinarios: array{start: int, end: int}} */
    private array $catalogRanges = [
        'mascotas' => ['start' => 0, 'end' => -1],
        'categorias' => ['start' => 0, 'end' => -1],
        'productos' => ['start' => 0, 'end' => -1],
        'sedes' => ['start' => 0, 'end' => -1],
        'veterinarios' => ['start' => 0, 'end' => -1],
    ];

    private string $ejemploPaciente = '';

    private string $ejemploProductoSku = '';

    private string $ejemploSede = '';

    private string $ejemploVeterinario = '';

    public function streamTo(string $output = 'php://output'): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('VetSaaS')
            ->setTitle('Plantilla importación de vacunaciones')
            ->setSubject('Carga masiva de vacunas y aplicaciones clínicas');

        $catalogos = $spreadsheet->getActiveSheet();
        $catalogos->setTitle('Catalogos');
        $this->fillCatalogosSheet($spreadsheet, $catalogos);

        $sheet = $spreadsheet->createSheet(0);
        $sheet->setTitle('Vacunaciones');
        $this->fillDataSheet($sheet);

        $this->buildGuideSheet($spreadsheet);
        $spreadsheet->setActiveSheetIndexByName('Vacunaciones');

        (new Xlsx($spreadsheet))->save($output);
        $spreadsheet->disconnectWorksheets();
    }

    /**
     * Valor único para lista / match de importación.
     */
    public static function pacienteListaValor(Paciente $p): string
    {
        $nombre = trim((string) $p->nombre);
        $owner = $p->propietario;
        $titular = $owner !== null ? trim($owner->displayName()) : '';
        $num = trim((string) ($owner?->numero_documento ?? ''));
        $tipo = trim((string) ($owner?->tipo_documento ?? ''));

        if ($num !== '') {
            $doc = trim(($tipo !== '' ? $tipo.' ' : '').$num);

            return $nombre.' · '.$doc;
        }

        if ($titular !== '') {
            return $nombre.' · '.$titular;
        }

        return $nombre;
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
            $this->ejemploPaciente !== '' ? $this->ejemploPaciente : 'Firulais · DNI 12345678',
            'Sextuple',
            'vacuna',
            '10/08/2026 10:30',
            '10/09/2026',
            '1',
            'LOTE-001',
            $this->ejemploProductoSku,
            $this->ejemploSede,
            $this->ejemploVeterinario,
            'Moquillo, Parvo, Hepatitis',
            'Fila de ejemplo — bórrala',
        ];

        foreach ($example as $i => $value) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            // Fechas / lote / sku / dosis como texto
            if (in_array($i, [3, 4, 5, 6, 7], true)) {
                $sheet->setCellValueExplicit("{$col}".self::DATA_START_ROW, (string) $value, DataType::TYPE_STRING);
            } else {
                $sheet->setCellValue("{$col}".self::DATA_START_ROW, $value);
            }
        }

        foreach (['D', 'E', 'F', 'G', 'H'] as $textCol) {
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

        if ($this->catalogRanges['mascotas']['start'] > 0) {
            $this->applyListValidation($sheet, 'A', 'MASCOTAS_LISTA');
        }
        $this->applyListValidation($sheet, 'C', 'CATEGORIAS_LISTA');
        if ($this->catalogRanges['productos']['start'] > 0) {
            $this->applyListValidation($sheet, 'H', 'PRODUCTOS_LISTA', true, true);
        }
        if ($this->catalogRanges['sedes']['start'] > 0) {
            $this->applyListValidation($sheet, 'I', 'SEDES_LISTA', true);
        }
        if ($this->catalogRanges['veterinarios']['start'] > 0) {
            $this->applyListValidation($sheet, 'J', 'VETERINARIOS_LISTA', true, true);
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

        $mascotaRows = Paciente::query()
            ->where('activo', true)
            ->with(['propietario:id,nombres,apellidos,razon_social,tipo_documento,numero_documento'])
            ->orderBy('nombre')
            ->limit(800)
            ->get(['id', 'nombre', 'propietario_id', 'especie', 'microchip'])
            ->map(function (Paciente $p): array {
                $valor = self::pacienteListaValor($p);
                $owner = $p->propietario;
                $titular = $owner !== null ? $owner->displayName() : '—';
                $especie = trim((string) ($p->especie ?? ''));

                return [
                    'codigo' => trim((string) ($p->microchip ?? '')) !== ''
                        ? (string) $p->microchip
                        : mb_substr((string) $p->id, 0, 8),
                    'nombre' => trim((string) $p->nombre).($especie !== '' ? " ({$especie})" : ''),
                    'referencia' => $titular,
                    'valor' => $valor,
                ];
            })
            ->all();

        if ($mascotaRows === []) {
            $mascotaRows = [[
                'codigo' => '',
                'nombre' => '(Sin mascotas activas — créalas primero)',
                'referencia' => '',
                'valor' => '',
            ]];
        } else {
            $this->ejemploPaciente = $mascotaRows[0]['valor'];
        }

        $row = $this->writeBlock($sheet, 1, 'MASCOTAS', $mascotaRows);
        $this->catalogRanges['mascotas'] = $mascotaRows[0]['valor'] !== ''
            ? ['start' => $row - count($mascotaRows), 'end' => $row - 1]
            : ['start' => 0, 'end' => -1];

        $categorias = [
            ['codigo' => 'vacuna', 'nombre' => 'Vacuna', 'valor' => VacunaAplicada::CATEGORIA_VACUNA],
            ['codigo' => 'desparasitacion', 'nombre' => 'Antiparasitario', 'valor' => VacunaAplicada::CATEGORIA_DESPARASITACION],
            ['codigo' => 'otro', 'nombre' => 'Otro', 'valor' => VacunaAplicada::CATEGORIA_OTRO],
        ];
        $row = $this->writeBlock($sheet, $row + 2, 'CATEGORIAS', $categorias);
        $this->catalogRanges['categorias'] = ['start' => $row - count($categorias), 'end' => $row - 1];

        $productoRows = Producto::query()
            ->where('activo', true)
            ->where('medicamento', true)
            ->orderBy('nombre')
            ->limit(400)
            ->get(['sku', 'nombre'])
            ->map(static function (Producto $pr): array {
                $sku = trim((string) ($pr->sku ?? ''));

                return [
                    'codigo' => $sku !== '' ? $sku : 'SIN-SKU',
                    'nombre' => (string) $pr->nombre,
                    'referencia' => 'Inventario (medicamento)',
                    'valor' => $sku !== '' ? $sku : (string) $pr->nombre,
                ];
            })
            ->filter(static fn (array $r): bool => $r['valor'] !== '')
            ->values()
            ->all();

        if ($productoRows === []) {
            $productoRows = [[
                'codigo' => '',
                'nombre' => '(Sin productos medicamento — opcional)',
                'referencia' => '',
                'valor' => '',
            ]];
        } else {
            $this->ejemploProductoSku = $productoRows[0]['valor'];
        }

        $row = $this->writeBlock($sheet, $row + 2, 'PRODUCTOS', $productoRows);
        $this->catalogRanges['productos'] = $productoRows[0]['valor'] !== ''
            ? ['start' => $row - count($productoRows), 'end' => $row - 1]
            : ['start' => 0, 'end' => -1];

        $tenantId = tenant_id();
        $sedeRows = Sede::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('activa', true)
            ->whereNull('deleted_at')
            ->orderBy('codigo')
            ->get(['codigo', 'nombre'])
            ->map(static fn (Sede $s): array => [
                'codigo' => (string) $s->codigo,
                'nombre' => (string) $s->nombre,
                'referencia' => 'Sede activa',
                'valor' => (string) $s->codigo,
            ])
            ->all();

        if ($sedeRows === []) {
            $sedeRows = [[
                'codigo' => '',
                'nombre' => '(Sin sedes activas — opcional)',
                'referencia' => '',
                'valor' => '',
            ]];
        } else {
            $this->ejemploSede = $sedeRows[0]['valor'];
        }

        $row = $this->writeBlock($sheet, $row + 2, 'SEDES', $sedeRows);
        $this->catalogRanges['sedes'] = $sedeRows[0]['valor'] !== ''
            ? ['start' => $row - count($sedeRows), 'end' => $row - 1]
            : ['start' => 0, 'end' => -1];

        $vetRows = User::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->limit(200)
            ->get(['name', 'email'])
            ->map(static function (User $u): array {
                $email = trim((string) ($u->email ?? ''));
                $name = trim((string) $u->name);

                return [
                    'codigo' => $email !== '' ? $email : $name,
                    'nombre' => $name,
                    'referencia' => $email,
                    'valor' => $email !== '' ? $email : $name,
                ];
            })
            ->filter(static fn (array $r): bool => $r['valor'] !== '')
            ->values()
            ->all();

        if ($vetRows === []) {
            $vetRows = [[
                'codigo' => '',
                'nombre' => '(Sin usuarios — opcional)',
                'referencia' => '',
                'valor' => '',
            ]];
        } else {
            $this->ejemploVeterinario = $vetRows[0]['valor'];
        }

        $row = $this->writeBlock($sheet, $row + 2, 'VETERINARIOS', $vetRows);
        $this->catalogRanges['veterinarios'] = $vetRows[0]['valor'] !== ''
            ? ['start' => $row - count($vetRows), 'end' => $row - 1]
            : ['start' => 0, 'end' => -1];

        foreach (['A', 'B', 'C', 'D'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $this->named($spreadsheet, $sheet, 'MASCOTAS_LISTA', 'mascotas');
        $this->named($spreadsheet, $sheet, 'CATEGORIAS_LISTA', 'categorias');
        $this->named($spreadsheet, $sheet, 'PRODUCTOS_LISTA', 'productos');
        $this->named($spreadsheet, $sheet, 'SEDES_LISTA', 'sedes');
        $this->named($spreadsheet, $sheet, 'VETERINARIOS_LISTA', 'veterinarios');
    }

    private function named(Spreadsheet $spreadsheet, Worksheet $sheet, string $name, string $key): void
    {
        $r = $this->catalogRanges[$key];
        if ($r['end'] >= $r['start'] && $r['start'] > 0) {
            $spreadsheet->addNamedRange(new NamedRange($name, $sheet, '$D$'.$r['start'].':$D$'.$r['end']));
        }
    }

    /**
     * @param  list<array{codigo: string, nombre: string, referencia?: string, valor: string}>  $rows
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
            $sheet->setCellValue("C{$r}", $item['referencia'] ?? '');
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
        $sheet->setCellValue('A1', 'Carga masiva de vacunaciones');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '1F6E4A']],
        ]);

        $lines = [
            ['', ''],
            ['Regla', 'Una fila = una aplicación (vacuna / antiparasitario / otro) a una mascota existente.'],
            ['Obligatorios', 'paciente*, nombre_vacuna*, categoria*, aplicada_at*'],
            ['Paciente', 'Elige de Catalogos → MASCOTAS (formato: Nombre · DNI/titular). No crea mascotas nuevas.'],
            ['Categoría', 'vacuna | desparasitacion | otro'],
            ['Fecha', 'DD/MM/AAAA o DD/MM/AAAA HH:MM. Si no pones hora, se usa 09:00.'],
            ['Producto / sede', 'Opcionales. Si ambos vienen, se vincula inventario pero esta carga NO descuenta stock.'],
            ['Veterinario', 'Email o nombre (lista sugerida). Vacío = quien importa.'],
            ['Ejemplos', 'Borra la fila de ejemplo antes de importar.'],
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
        $sheet->getColumnDimension('B')->setWidth(95);
    }
}
