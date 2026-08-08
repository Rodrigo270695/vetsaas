<?php

declare(strict_types=1);

namespace App\Support\Integrations;

/**
 * Catálogo de endpoints ApiPerú para el explorador de plataforma.
 *
 * Paths relativos a {@see config('services.apiperu.base_url')}
 * (p. ej. https://apiperu.dev/api → /placa).
 *
 * @see https://docs.apiperu.dev/
 */
final class ApiPeruEndpointCatalog
{
    /**
     * @return list<array{
     *     id: string,
     *     label: string,
     *     description: string,
     *     endpoints: list<array{
     *         key: string,
     *         label: string,
     *         description: string,
     *         path: string,
     *         docs_url: string|null,
     *         fields: list<array{
     *             name: string,
     *             label: string,
     *             type: string,
     *             required: bool,
     *             placeholder: string|null,
     *             hint: string|null,
     *             max_length: int|null,
     *             pattern: string|null,
     *         }>
     *     }>
     * }>
     */
    public static function groups(): array
    {
        return [
            [
                'id' => 'identidad',
                'label' => 'Identidad',
                'description' => 'DNI, RUC y cruce DNI–RUC.',
                'endpoints' => [
                    self::endpoint(
                        key: 'dni',
                        label: 'Consulta DNI',
                        description: 'Nombres y apellidos desde padrón reducido SUNAT.',
                        path: '/dni',
                        docsUrl: 'https://docs.apiperu.dev/enpoints/consulta-dni',
                        fields: [
                            self::field('dni', 'DNI', 'text', true, '12345678', '8 dígitos', 8, '^\d{8}$'),
                        ],
                    ),
                    self::endpoint(
                        key: 'ruc',
                        label: 'Consulta RUC',
                        description: 'Datos generales del contribuyente en SUNAT.',
                        path: '/ruc',
                        docsUrl: 'https://docs.apiperu.dev/enpoints/consulta-ruc',
                        fields: [
                            self::field('ruc', 'RUC', 'text', true, '20100070970', '11 dígitos', 11, '^\d{11}$'),
                        ],
                    ),
                    self::endpoint(
                        key: 'ruc_sunat',
                        label: 'Consulta RUC SUNAT',
                        description: 'Consulta RUC con detalle ampliado de SUNAT.',
                        path: '/ruc_sunat',
                        docsUrl: 'https://docs.apiperu.dev/',
                        fields: [
                            self::field('ruc', 'RUC', 'text', true, '20100070970', '11 dígitos', 11, '^\d{11}$'),
                        ],
                    ),
                    self::endpoint(
                        key: 'dni_ruc',
                        label: 'Consulta DNI – RUC',
                        description: 'Verifica si un DNI tiene RUC asociado.',
                        path: '/dni_ruc',
                        docsUrl: 'https://docs.apiperu.dev/enpoints/consulta-dni-ruc',
                        fields: [
                            self::field('dni', 'DNI', 'text', true, '12345678', '8 dígitos', 8, '^\d{8}$'),
                        ],
                    ),
                ],
            ],
            [
                'id' => 'ruc_detalle',
                'label' => 'RUC · Detalle',
                'description' => 'Contacto, representantes, deudas, establecimientos y más.',
                'endpoints' => [
                    self::endpoint(
                        key: 'ruc_contacto',
                        label: 'RUC Contacto',
                        description: 'Datos de contacto del contribuyente.',
                        path: '/ruc_contacto',
                        docsUrl: 'https://docs.apiperu.dev/',
                        fields: [
                            self::field('ruc', 'RUC', 'text', true, '20100070970', '11 dígitos', 11, '^\d{11}$'),
                        ],
                    ),
                    self::endpoint(
                        key: 'ruc_ssco',
                        label: 'RUC SSCO',
                        description: 'Información SSCO asociada al RUC.',
                        path: '/ruc_ssco',
                        docsUrl: 'https://docs.apiperu.dev/',
                        fields: [
                            self::field('ruc', 'RUC', 'text', true, '20100070970', '11 dígitos', 11, '^\d{11}$'),
                        ],
                    ),
                    self::endpoint(
                        key: 'ruc_deuda_coactiva',
                        label: 'RUC Deuda coactiva',
                        description: 'Deudas coactivas reportadas para el RUC.',
                        path: '/ruc_deuda_coactiva',
                        docsUrl: 'https://docs.apiperu.dev/',
                        fields: [
                            self::field('ruc', 'RUC', 'text', true, '20100070970', '11 dígitos', 11, '^\d{11}$'),
                        ],
                    ),
                    self::endpoint(
                        key: 'ruc_representantes',
                        label: 'RUC Representantes',
                        description: 'Representantes legales del RUC.',
                        path: '/ruc_representantes',
                        docsUrl: 'https://docs.apiperu.dev/enpoints/consulta-ruc-representantes',
                        fields: [
                            self::field('ruc', 'RUC', 'text', true, '20100070970', '11 dígitos', 11, '^\d{11}$'),
                        ],
                    ),
                    self::endpoint(
                        key: 'ruc_establecimientos_anexos',
                        label: 'RUC Establecimientos anexos',
                        description: 'Locales / anexos registrados en SUNAT.',
                        path: '/ruc_establecimientos_anexos',
                        docsUrl: 'https://docs.apiperu.dev/enpoints/consulta-ruc-establecimientos',
                        fields: [
                            self::field('ruc', 'RUC', 'text', true, '20100070970', '11 dígitos', 11, '^\d{11}$'),
                        ],
                    ),
                    self::endpoint(
                        key: 'ruc_domicilio_fiscal',
                        label: 'RUC Domicilio fiscal',
                        description: 'Domicilio fiscal del contribuyente.',
                        path: '/ruc_domicilio_fiscal',
                        docsUrl: 'https://docs.apiperu.dev/',
                        fields: [
                            self::field('ruc', 'RUC', 'text', true, '20100070970', '11 dígitos', 11, '^\d{11}$'),
                        ],
                    ),
                    self::endpoint(
                        key: 'ruc_trabajadores',
                        label: 'RUC Trabajadores',
                        description: 'Información de trabajadores asociada al RUC.',
                        path: '/ruc_trabajadores',
                        docsUrl: 'https://docs.apiperu.dev/',
                        fields: [
                            self::field('ruc', 'RUC', 'text', true, '20100070970', '11 dígitos', 11, '^\d{11}$'),
                        ],
                    ),
                ],
            ],
            [
                'id' => 'finanzas',
                'label' => 'Finanzas',
                'description' => 'Tipo de cambio SBS y comisiones AFP.',
                'endpoints' => [
                    self::endpoint(
                        key: 'tipo_de_cambio',
                        label: 'Tipo de cambio',
                        description: 'Tipo de cambio oficial por fecha (USD / EUR).',
                        path: '/tipo_de_cambio',
                        docsUrl: 'https://docs.apiperu.dev/enpoints/consulta-tipo-de-cambio',
                        fields: [
                            self::field('fecha', 'Fecha', 'date', true, null, 'Formato AAAA-MM-DD', null, null),
                        ],
                    ),
                    self::endpoint(
                        key: 'comisiones_afp',
                        label: 'Comisiones AFP',
                        description: 'Comisiones de AFP vigentes.',
                        path: '/comisiones_afp',
                        docsUrl: 'https://docs.apiperu.dev/',
                        fields: [],
                    ),
                ],
            ],
            [
                'id' => 'comprobantes',
                'label' => 'Comprobantes (CPE)',
                'description' => 'Validación de comprobantes electrónicos SUNAT.',
                'endpoints' => [
                    self::endpoint(
                        key: 'cpe',
                        label: 'Consulta CPE',
                        description: 'Valida un comprobante electrónico individual.',
                        path: '/cpe',
                        docsUrl: 'https://docs.apiperu.dev/enpoints/consulta-cpe',
                        fields: [
                            self::field('ruc_emisor', 'RUC emisor', 'text', true, '20100070970', '11 dígitos', 11, '^\d{11}$'),
                            self::field('codigo_tipo_documento', 'Tipo CPE', 'text', true, '01', '01 Factura, 03 Boleta, 07 NC…', 2, null),
                            self::field('serie', 'Serie', 'text', true, 'F001', 'Ej. F001 / B001', 4, null),
                            self::field('numero', 'Número', 'text', true, '1', 'Correlativo sin ceros a la izquierda o con ellos', 8, null),
                            self::field('fecha_de_emision', 'Fecha emisión', 'date', true, null, 'AAAA-MM-DD', null, null),
                            self::field('monto', 'Monto total', 'text', true, '100.00', 'Total del CPE', null, null),
                        ],
                    ),
                    self::endpoint(
                        key: 'cpe_multiple',
                        label: 'Consulta CPE múltiple',
                        description: 'Valida varios CPE en una sola petición (JSON array en el campo comprobantes).',
                        path: '/cpe_multiple',
                        docsUrl: 'https://docs.apiperu.dev/enpoints/consulta-cpe-multiple',
                        fields: [
                            self::field(
                                'comprobantes',
                                'Comprobantes (JSON)',
                                'textarea',
                                true,
                                '[{"ruc_emisor":"20100070970","codigo_tipo_documento":"01","serie":"F001","numero":"1","fecha_de_emision":"2026-01-15","monto":"100.00"}]',
                                'Array JSON de comprobantes',
                                null,
                                null,
                            ),
                        ],
                    ),
                ],
            ],
            [
                'id' => 'vehiculos',
                'label' => 'Vehículos y MTC',
                'description' => 'Licencia de conducir y ficha técnica por placa.',
                'endpoints' => [
                    self::endpoint(
                        key: 'licencia',
                        label: 'Licencia de conducir',
                        description: 'Consulta licencia MTC.',
                        path: '/licencia',
                        docsUrl: 'https://docs.apiperu.dev/',
                        fields: [
                            self::field('dni', 'DNI', 'text', true, '12345678', '8 dígitos del titular', 8, '^\d{8}$'),
                        ],
                    ),
                    self::endpoint(
                        key: 'placa',
                        label: 'Consulta placa',
                        description: 'Ficha técnica del vehículo (marca, modelo, año, VIN…). Fuente Pacífico. Consume 2 consultas.',
                        path: '/placa',
                        docsUrl: 'https://docs.apiperu.dev/referencia/placa/',
                        fields: [
                            self::field('placa', 'Placa', 'text', true, 'ABC123', '6 a 7 caracteres alfanuméricos', 7, '^[A-Za-z0-9]{6,7}$'),
                        ],
                    ),
                ],
            ],
            [
                'id' => 'ubicacion',
                'label' => 'Ubicación',
                'description' => 'Ubigeos, puertos y aeropuertos.',
                'endpoints' => [
                    self::endpoint(
                        key: 'ubigeo',
                        label: 'Consulta ubigeos',
                        description: 'Búsqueda de ubigeo (departamento / provincia / distrito).',
                        path: '/ubigeo',
                        docsUrl: 'https://docs.apiperu.dev/',
                        fields: [
                            self::field('ubigeo', 'Código o nombre', 'text', false, '150101', 'Código de 6 dígitos o texto a buscar', 80, null),
                        ],
                    ),
                    self::endpoint(
                        key: 'puertos',
                        label: 'Consulta puertos',
                        description: 'Listado / búsqueda de puertos.',
                        path: '/puertos',
                        docsUrl: 'https://docs.apiperu.dev/',
                        fields: [
                            self::field('nombre', 'Nombre', 'text', false, 'Callao', 'Filtro opcional', 80, null),
                        ],
                    ),
                    self::endpoint(
                        key: 'aeropuertos',
                        label: 'Consulta aeropuertos',
                        description: 'Listado / búsqueda de aeropuertos.',
                        path: '/aeropuertos',
                        docsUrl: 'https://docs.apiperu.dev/',
                        fields: [
                            self::field('nombre', 'Nombre', 'text', false, 'Jorge Chávez', 'Filtro opcional', 80, null),
                        ],
                    ),
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{key: string, path: string, fields: list<array<string, mixed>>}>
     */
    public static function endpointsByKey(): array
    {
        $map = [];
        foreach (self::groups() as $group) {
            foreach ($group['endpoints'] as $endpoint) {
                $map[$endpoint['key']] = $endpoint;
            }
        }

        return $map;
    }

    public static function find(string $key): ?array
    {
        return self::endpointsByKey()[$key] ?? null;
    }

    /**
     * Perfiles UX: una consulta dispara varios endpoints relacionados.
     *
     * @return list<array{
     *     id: string,
     *     label: string,
     *     description: string,
     *     icon: string,
     *     primary_field: array{name: string, label: string, type: string, required: bool, placeholder: string|null, hint: string|null, max_length: int|null, pattern: string|null}|null,
     *     extra_fields: list<array{name: string, label: string, type: string, required: bool, placeholder: string|null, hint: string|null, max_length: int|null, pattern: string|null}>,
     *     endpoint_keys: list<string>,
     *     tab_labels: array<string, string>,
     * }>
     */
    public static function profiles(): array
    {
        return [
            [
                'id' => 'persona',
                'label' => 'Persona (DNI)',
                'description' => 'Con un DNI obtienes identidad, cruce DNI–RUC y licencia de conducir.',
                'icon' => 'id_card',
                'primary_field' => self::field('dni', 'DNI', 'text', true, '12345678', '8 dígitos', 8, '^\d{8}$'),
                'extra_fields' => [],
                'endpoint_keys' => ['dni', 'dni_ruc', 'licencia'],
                'tab_labels' => [
                    'dni' => 'Identidad',
                    'dni_ruc' => 'RUC vinculado',
                    'licencia' => 'Licencia MTC',
                ],
            ],
            [
                'id' => 'empresa',
                'label' => 'Empresa (RUC)',
                'description' => 'Un RUC abre ficha completa: general, contacto, representantes, anexos, deudas y más.',
                'icon' => 'building',
                'primary_field' => self::field('ruc', 'RUC', 'text', true, '20100070970', '11 dígitos', 11, '^\d{11}$'),
                'extra_fields' => [],
                'endpoint_keys' => [
                    'ruc',
                    'ruc_sunat',
                    'ruc_contacto',
                    'ruc_representantes',
                    'ruc_establecimientos_anexos',
                    'ruc_domicilio_fiscal',
                    'ruc_deuda_coactiva',
                    'ruc_ssco',
                    'ruc_trabajadores',
                ],
                'tab_labels' => [
                    'ruc' => 'General',
                    'ruc_sunat' => 'SUNAT',
                    'ruc_contacto' => 'Contacto',
                    'ruc_representantes' => 'Representantes',
                    'ruc_establecimientos_anexos' => 'Establecimientos',
                    'ruc_domicilio_fiscal' => 'Domicilio',
                    'ruc_deuda_coactiva' => 'Deuda coactiva',
                    'ruc_ssco' => 'SSCO',
                    'ruc_trabajadores' => 'Trabajadores',
                ],
            ],
            [
                'id' => 'finanzas',
                'label' => 'Finanzas',
                'description' => 'Tipo de cambio SBS (por fecha) y comisiones AFP vigentes en una sola vista.',
                'icon' => 'wallet',
                'primary_field' => self::field('fecha', 'Fecha tipo de cambio', 'date', true, null, 'Solo aplica al tipo de cambio. Las AFP no piden fecha.', null, null),
                'extra_fields' => [],
                'endpoint_keys' => ['tipo_de_cambio', 'comisiones_afp'],
                'tab_labels' => [
                    'tipo_de_cambio' => 'Tipo de cambio',
                    'comisiones_afp' => 'Comisiones AFP',
                ],
            ],
            [
                'id' => 'comprobante',
                'label' => 'Comprobante CPE',
                'description' => 'Valida un comprobante electrónico ante SUNAT.',
                'icon' => 'file',
                'primary_field' => null,
                'extra_fields' => [
                    self::field('ruc_emisor', 'RUC emisor', 'text', true, '20100070970', '11 dígitos', 11, '^\d{11}$'),
                    self::field('codigo_tipo_documento', 'Tipo CPE', 'text', true, '01', '01 Factura, 03 Boleta, 07 NC…', 2, null),
                    self::field('serie', 'Serie', 'text', true, 'F001', 'Ej. F001 / B001', 4, null),
                    self::field('numero', 'Número', 'text', true, '1', 'Correlativo', 8, null),
                    self::field('fecha_de_emision', 'Fecha emisión', 'date', true, null, 'AAAA-MM-DD', null, null),
                    self::field('monto', 'Monto total', 'text', true, '100.00', 'Total del CPE', 16, null),
                ],
                'endpoint_keys' => ['cpe'],
                'tab_labels' => [
                    'cpe' => 'Resultado CPE',
                ],
            ],
            [
                'id' => 'vehiculo',
                'label' => 'Vehículo (placa)',
                'description' => 'Ficha técnica por placa (marca, modelo, año, VIN). Consume 2 consultas del plan.',
                'icon' => 'car',
                'primary_field' => self::field('placa', 'Placa', 'text', true, 'ABC123', '6 a 7 caracteres', 7, '^[A-Za-z0-9]{6,7}$'),
                'extra_fields' => [],
                'endpoint_keys' => ['placa'],
                'tab_labels' => [
                    'placa' => 'Ficha técnica',
                ],
            ],
            [
                'id' => 'ubicacion',
                'label' => 'Ubicación',
                'description' => 'Ubigeo, puertos y aeropuertos con un término de búsqueda.',
                'icon' => 'map',
                'primary_field' => self::field('q', 'Búsqueda', 'text', false, 'Lima / Callao / 150101', 'Código o nombre (opcional)', 80, null),
                'extra_fields' => [],
                'endpoint_keys' => ['ubigeo', 'puertos', 'aeropuertos'],
                'tab_labels' => [
                    'ubigeo' => 'Ubigeos',
                    'puertos' => 'Puertos',
                    'aeropuertos' => 'Aeropuertos',
                ],
            ],
        ];
    }

    public static function findProfile(string $id): ?array
    {
        foreach (self::profiles() as $profile) {
            if ($profile['id'] === $id) {
                return $profile;
            }
        }

        return null;
    }

    /**
     * @param  list<array{name: string, label: string, type: string, required: bool, placeholder: string|null, hint: string|null, max_length: int|null, pattern: string|null}>  $fields
     * @return array{key: string, label: string, description: string, path: string, docs_url: string|null, fields: list<array<string, mixed>>}
     */
    private static function endpoint(
        string $key,
        string $label,
        string $description,
        string $path,
        ?string $docsUrl,
        array $fields,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'path' => $path,
            'docs_url' => $docsUrl,
            'fields' => $fields,
        ];
    }

    /**
     * @return array{name: string, label: string, type: string, required: bool, placeholder: string|null, hint: string|null, max_length: int|null, pattern: string|null}
     */
    private static function field(
        string $name,
        string $label,
        string $type,
        bool $required,
        ?string $placeholder,
        ?string $hint,
        ?int $maxLength,
        ?string $pattern,
    ): array {
        return [
            'name' => $name,
            'label' => $label,
            'type' => $type,
            'required' => $required,
            'placeholder' => $placeholder,
            'hint' => $hint,
            'max_length' => $maxLength,
            'pattern' => $pattern,
        ];
    }
}
