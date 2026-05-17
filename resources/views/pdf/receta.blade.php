@php
    /** @var string $clinicNombre */
    /** @var string|null $logoDataUri */
    /** @var string $colorPrimario */
    /** @var string $colorSecundario */
    /** @var string|null $clinicEmail */
    /** @var string|null $clinicTelefono */
    /** @var string|null $clinicWeb */
    /** @var string|null $clinicDireccion */
    /** @var \App\Models\Receta $receta */
    /** @var string $propietarioNombre */
    /** @var string $emitidaAt */
    /** @var string $consultaAt */
    /** @var string $generadoEn */
    $obs = $receta->observaciones ? trim((string) $receta->observaciones) : '';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('recetas.pdf.document_title') }} — {{ $receta->paciente->nombre }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            margin: 0;
            padding: 28px 32px 48px;
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
            margin-bottom: 14px;
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
        .grid .k { font-weight: bold; color: #555; width: 30%; }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 9px;
        }
        table.data th {
            text-align: left;
            padding: 6px 5px;
            background: {{ $colorPrimario }};
            color: #fff;
            font-weight: bold;
        }
        table.data td {
            padding: 6px 5px;
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
        .stamp {
            position: fixed;
            top: 42%;
            left: 12%;
            right: 12%;
            text-align: center;
            font-size: 36px;
            font-weight: bold;
            color: rgba(185, 28, 28, 0.18);
            transform: rotate(-18deg);
            z-index: 0;
            pointer-events: none;
        }
        .body-wrap { position: relative; z-index: 1; }
    </style>
</head>
<body>
    @if ($receta->estado === \App\Models\Receta::ESTADO_ANULADA)
        <div class="stamp">{{ __('recetas.pdf.anulada_stamp') }}</div>
    @endif

    <div class="body-wrap">
        <div class="header">
            <div class="header-left">
                @if ($logoDataUri)
                    <img class="logo" src="{{ $logoDataUri }}" alt="">
                @endif
            </div>
            <div class="header-mid">
                <p class="clinic-name">{{ $clinicNombre }}</p>
                <p class="doc-title">{{ __('recetas.pdf.document_title') }}</p>
                <p class="doc-sub">{{ __('recetas.pdf.subtitle') }}</p>
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
            <h2>{{ __('recetas.pdf.section_patient') }}</h2>
            <table class="grid">
                <tr>
                    <td class="k">{{ __('recetas.pdf.label_patient') }}</td>
                    <td>{{ $receta->paciente->nombre }}</td>
                </tr>
                <tr>
                    <td class="k">{{ __('recetas.pdf.label_owner') }}</td>
                    <td>{{ $propietarioNombre }}</td>
                </tr>
            </table>
        </div>

        <div class="card" style="background: #fff;">
            <h2>{{ __('recetas.pdf.section_context') }}</h2>
            <table class="grid">
                <tr>
                    <td class="k">{{ __('recetas.pdf.label_date') }}</td>
                    <td>{{ $emitidaAt }}</td>
                </tr>
                <tr>
                    <td class="k">{{ __('recetas.pdf.label_status') }}</td>
                    <td>{{ __('recetas.pdf.estados.'.$receta->estado) }}</td>
                </tr>
                <tr>
                    <td class="k">{{ __('recetas.pdf.label_consulta') }}</td>
                    <td>{{ $consultaAt }}</td>
                </tr>
                <tr>
                    <td class="k">{{ __('recetas.pdf.label_vet') }}</td>
                    <td>{{ $receta->veterinario?->name ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="k">{{ __('recetas.pdf.label_sede') }}</td>
                    <td>{{ $receta->sede?->nombre ?? '—' }}</td>
                </tr>
                @if ($obs !== '')
                    <tr>
                        <td class="k">{{ __('recetas.pdf.label_obs') }}</td>
                        <td>{{ $obs }}</td>
                    </tr>
                @endif
            </table>
        </div>

        <div class="card" style="background: #fff;">
            <h2>{{ __('recetas.pdf.section_meds') }}</h2>
            @if ($receta->lineas->isEmpty())
                <p class="muted" style="margin:0;">{{ __('recetas.pdf.empty_meds') }}</p>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            <th style="width:22%;">{{ __('recetas.pdf.table_med') }}</th>
                            <th style="width:28%;">{{ __('recetas.pdf.table_posology') }}</th>
                            <th style="width:12%;">{{ __('recetas.pdf.table_days') }}</th>
                            <th style="width:38%;">{{ __('recetas.pdf.table_instructions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($receta->lineas as $ln)
                            @php
                                $dur = $ln->duracion_dias !== null
                                    ? (string) $ln->duracion_dias.' '.__('recetas.pdf.days_suffix')
                                    : '—';
                                $pos = $ln->posologia ? trim((string) $ln->posologia) : '—';
                                $ins = $ln->instrucciones ? trim((string) $ln->instrucciones) : '—';
                            @endphp
                            <tr>
                                <td>{{ $ln->nombre_medicamento }}</td>
                                <td>{{ $pos }}</td>
                                <td>{{ $dur }}</td>
                                <td>{{ $ins }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="footer">
        <div>{{ __('recetas.pdf.footer_generated', ['fecha' => $generadoEn]) }}</div>
        <div class="muted" style="margin-top: 5px; line-height: 1.35;">{{ __('recetas.pdf.footer_disclaimer') }}</div>
    </div>
</body>
</html>
