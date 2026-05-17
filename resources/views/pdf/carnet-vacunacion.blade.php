@php
    /** @var string $clinicNombre */
    /** @var string|null $logoDataUri */
    /** @var string $colorPrimario */
    /** @var string $colorSecundario */
    /** @var string|null $clinicEmail */
    /** @var string|null $clinicTelefono */
    /** @var string|null $clinicWeb */
    /** @var string|null $clinicDireccion */
    /** @var \App\Models\Paciente $paciente */
    /** @var string $propietarioNombre */
    /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $vacunas */
    /** @var string $generadoEn */
    /** @var int $vacunasCount */
    $sexo = $paciente->sexo ? strtolower((string) $paciente->sexo) : null;
    $sexoTxt = match ($sexo) {
        'm' => __('carnet_vacunacion.sex_m'),
        'h' => __('carnet_vacunacion.sex_h'),
        'u' => __('carnet_vacunacion.sex_u'),
        default => $paciente->sexo ? (string) $paciente->sexo : '—',
    };
    $especieRaza = collect([$paciente->especie, $paciente->raza])->filter()->implode(' · ') ?: '—';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('carnet_vacunacion.document_title') }} — {{ $paciente->nombre }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            margin: 0;
            padding: 28px 32px 40px;
        }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 3px solid {{ $colorPrimario }};
        }
        .header-left { display: table-cell; vertical-align: middle; width: 72px; }
        .header-mid { display: table-cell; vertical-align: middle; padding-left: 14px; }
        .header-right { display: table-cell; vertical-align: middle; text-align: right; width: 38%; font-size: 9px; color: #444; }
        .logo { max-width: 64px; max-height: 64px; }
        .clinic-name { font-size: 18px; font-weight: bold; color: {{ $colorPrimario }}; margin: 0 0 4px; }
        .doc-title { font-size: 13px; margin: 0; color: #333; }
        .doc-sub { font-size: 10px; margin: 4px 0 0; color: #666; }
        .card {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 12px 14px;
            margin-bottom: 16px;
            background: {{ $colorSecundario }};
        }
        .card h2 {
            margin: 0 0 10px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: {{ $colorPrimario }};
        }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { padding: 4px 8px 4px 0; vertical-align: top; }
        .grid .k { font-weight: bold; color: #555; width: 28%; }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 8.2px;
        }
        table.data th {
            text-align: left;
            padding: 5px 4px;
            background: {{ $colorPrimario }};
            color: #fff;
            font-weight: bold;
        }
        table.data td {
            padding: 4px;
            border-bottom: 1px solid #e5e5e5;
            vertical-align: top;
        }
        table.data tr:nth-child(even) td { background: #fafafa; }
        .footer {
            position: fixed;
            bottom: 22px;
            left: 32px;
            right: 32px;
            font-size: 8.5px;
            color: #777;
            border-top: 1px solid #ddd;
            padding-top: 8px;
        }
        .muted { color: #888; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            @if ($logoDataUri)
                <img class="logo" src="{{ $logoDataUri }}" alt="">
            @endif
        </div>
        <div class="header-mid">
            <p class="clinic-name">{{ $clinicNombre }}</p>
            <p class="doc-title">{{ __('carnet_vacunacion.document_title') }}</p>
            <p class="doc-sub">{{ __('carnet_vacunacion.subtitle') }}</p>
        </div>
        <div class="header-right">
            @if ($clinicDireccion)
                <div>{{ $clinicDireccion }}</div>
            @endif
            @if ($clinicTelefono)
                <div>{{ $clinicTelefono }}</div>
            @endif
            @if ($clinicEmail)
                <div>{{ $clinicEmail }}</div>
            @endif
            @if ($clinicWeb)
                <div class="muted">{{ $clinicWeb }}</div>
            @endif
        </div>
    </div>

    <div class="card">
        <h2>{{ __('carnet_vacunacion.section_patient') }}</h2>
        <table class="grid">
            <tr>
                <td class="k">{{ __('carnet_vacunacion.label_name') }}</td>
                <td>{{ $paciente->nombre }}</td>
            </tr>
            <tr>
                <td class="k">{{ __('carnet_vacunacion.label_species') }}</td>
                <td>{{ $especieRaza }}</td>
            </tr>
            <tr>
                <td class="k">{{ __('carnet_vacunacion.label_sex') }}</td>
                <td>{{ $sexoTxt }}</td>
            </tr>
            <tr>
                <td class="k">{{ __('carnet_vacunacion.label_microchip') }}</td>
                <td>{{ $paciente->microchip ? $paciente->microchip : '—' }}</td>
            </tr>
        </table>
    </div>

    <div class="card" style="background: #fff;">
        <h2>{{ __('carnet_vacunacion.section_owner') }}</h2>
        <p style="margin:0;">{{ $propietarioNombre }}</p>
    </div>

    <div class="card" style="background: #fff;">
        <h2>{{ __('carnet_vacunacion.section_vaccines') }}</h2>
        @if ($vacunasCount === 0)
            <p class="muted" style="margin:0;">{{ __('carnet_vacunacion.empty') }}</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th>{{ __('carnet_vacunacion.table_date') }}</th>
                        <th>{{ __('carnet_vacunacion.table_type') }}</th>
                        <th>{{ __('carnet_vacunacion.table_vaccine') }}</th>
                        <th>{{ __('carnet_vacunacion.table_schema') }}</th>
                        <th>{{ __('carnet_vacunacion.table_dose') }}</th>
                        <th>{{ __('carnet_vacunacion.table_next') }}</th>
                        <th>{{ __('carnet_vacunacion.table_batch') }}</th>
                        <th>{{ __('carnet_vacunacion.table_vet') }}</th>
                        <th>{{ __('carnet_vacunacion.table_branch') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vacunas as $row)
                        <tr>
                            <td>{{ $row['aplicada_at'] }}</td>
                            <td>{{ $row['categoria_label'] ?? '—' }}</td>
                            <td>{{ $row['nombre_vacuna'] }}</td>
                            <td>{{ $row['esquema_antigenos'] ?? '—' }}</td>
                            <td>{{ $row['numero_dosis'] ?? '—' }}</td>
                            <td>{{ $row['fecha_proxima_sugerida'] ?? '—' }}</td>
                            <td>{{ $row['lote'] ?? '—' }}</td>
                            <td>{{ $row['veterinario'] ?? '—' }}</td>
                            <td>{{ $row['sede'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="footer">
        <div>{{ __('carnet_vacunacion.footer_generated', ['fecha' => $generadoEn]) }}</div>
        <div class="muted" style="margin-top: 5px; line-height: 1.35;">{{ __('carnet_vacunacion.footer_disclaimer') }}</div>
    </div>
</body>
</html>
