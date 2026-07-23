<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Arqueo de caja</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: {{ $colorText }};
            margin: 0;
            padding: 24px 28px 36px;
            line-height: 1.35;
        }
        .header {
            width: 100%;
            margin-bottom: 16px;
            padding: 12px 14px;
            background: {{ $colorPrimario }};
            color: {{ $colorOnPrimary }};
            border-radius: 4px;
        }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: middle; }
        .logo { max-height: 48px; max-width: 120px; }
        .clinic-name { font-size: 16px; font-weight: bold; margin: 0 0 2px; color: {{ $colorOnPrimary }}; }
        .doc-title { font-size: 12px; margin: 0; font-weight: bold; color: {{ $colorOnPrimary }}; }
        .doc-sub { font-size: 9px; margin: 3px 0 0; opacity: 0.92; color: {{ $colorOnPrimary }}; }
        .card {
            border: 1px solid #D1D5DB;
            border-radius: 4px;
            padding: 10px 12px;
            margin-bottom: 12px;
            background: #fff;
            page-break-inside: avoid;
        }
        .card h2 {
            margin: 0 0 8px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: {{ $colorPrimario }};
            border-bottom: 2px solid {{ $colorSecundario }};
            padding-bottom: 4px;
        }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid td { padding: 4px 5px; vertical-align: top; border-bottom: 1px solid #E5E7EB; }
        table.grid tr:last-child td { border-bottom: 0; }
        .k { color: {{ $colorMuted }}; width: 42%; }
        .v { text-align: right; font-weight: bold; font-variant-numeric: tabular-nums; color: {{ $colorText }}; }
        .muted { color: {{ $colorMuted }}; font-weight: normal; font-size: 9.5px; }
        .kpi-table { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 0 12px; }
        .kpi {
            border: 1px solid #D1D5DB;
            border-radius: 4px;
            padding: 8px 10px;
            background: {{ $colorSecundario }};
            text-align: center;
        }
        .kpi .label { font-size: 8.5px; text-transform: uppercase; color: {{ $colorMuted }}; margin-bottom: 3px; font-weight: bold; }
        .kpi .value { font-size: 13px; font-weight: bold; color: {{ $colorText }}; }
        .kpi .hint { font-size: 8px; color: {{ $colorMuted }}; margin-top: 2px; }
        .diff-ok { color: #0F6E56; }
        .diff-bad { color: #B42318; }
        .note-box {
            background: {{ $colorSecundario }};
            border-left: 3px solid {{ $colorPrimario }};
            padding: 8px 10px;
            margin-bottom: 12px;
            font-size: 9.5px;
            color: {{ $colorText }};
        }
        .bar-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .bar-table td { padding: 5px 4px; vertical-align: middle; }
        .bar-label { width: 22%; font-weight: bold; color: {{ $colorText }}; }
        .bar-track {
            background: #E5E7EB;
            border-radius: 3px;
            height: 14px;
            overflow: hidden;
        }
        .bar-fill {
            height: 14px;
            background: {{ $colorPrimario }};
            border-radius: 3px;
        }
        .bar-meta { width: 28%; text-align: right; font-variant-numeric: tabular-nums; color: {{ $colorText }}; font-size: 9.5px; }
        table.detail { width: 100%; border-collapse: collapse; font-size: 9.5px; }
        table.detail th {
            text-align: left;
            background: {{ $colorSecundario }};
            color: {{ $colorText }};
            padding: 5px 4px;
            border-bottom: 2px solid {{ $colorPrimario }};
            font-size: 8.5px;
            text-transform: uppercase;
        }
        table.detail td {
            padding: 5px 4px;
            border-bottom: 1px solid #E5E7EB;
            color: {{ $colorText }};
            vertical-align: top;
        }
        table.detail td.num { text-align: right; font-variant-numeric: tabular-nums; font-weight: bold; }
        .footer {
            margin-top: 18px;
            padding-top: 8px;
            border-top: 1px solid #D1D5DB;
            font-size: 8.5px;
            color: {{ $colorMuted }};
        }
        .two-col { width: 100%; border-collapse: separate; border-spacing: 8px 0; }
        .two-col > tbody > tr > td { width: 50%; vertical-align: top; }
    </style>
</head>
<body>
@php
    $fmt = static function (?string $n, string $moneda = 'PEN'): string {
        if ($n === null || $n === '') {
            return '—';
        }
        return number_format((float) $n, 2, '.', ',').' '.$moneda;
    };
    $fmtDate = static function (?string $iso): string {
        if ($iso === null || $iso === '') {
            return '—';
        }
        try {
            return \Illuminate\Support\Carbon::parse($iso)
                ->timezone(config('app.timezone'))
                ->format('d/m/Y H:i');
        } catch (\Throwable) {
            return '—';
        }
    };
    $moneda = (string) ($arqueo['moneda'] ?? 'PEN');
    $diff = $arqueo['diferencia'] ?? null;
    $diffClass = '';
    if (is_string($diff) && is_numeric($diff)) {
        $diffClass = abs((float) $diff) < 0.005 ? 'diff-ok' : 'diff-bad';
    }
    $metodoLabel = static function (string $codigo): string {
        return match ($codigo) {
            'efectivo' => 'Efectivo',
            'yape' => 'Yape',
            'plin' => 'Plin',
            'tarjeta' => 'Tarjeta',
            'transferencia' => 'Transferencia',
            default => ucfirst($codigo),
        };
    };
    $compLabel = static function (string $c): string {
        return match ($c) {
            'boleta' => 'Boleta',
            'factura' => 'Factura',
            default => 'Ticket',
        };
    };
    $chart = is_array($arqueo['metodos_chart'] ?? null) ? $arqueo['metodos_chart'] : [];
    $ventas = is_array($arqueo['ventas'] ?? null) ? $arqueo['ventas'] : [];
@endphp

<div class="header">
    <table class="header-table">
        <tr>
            @if($logoDataUri !== '')
                <td width="130" style="padding-right:12px;">
                    <img src="{{ $logoDataUri }}" class="logo" alt="Logo">
                </td>
            @endif
            <td>
                <p class="clinic-name">{{ $clinic_nombre }}</p>
                <p class="doc-title">Reporte de arqueo y cierre de caja</p>
                <p class="doc-sub">
                    Sede: {{ $arqueo['sede_nombre'] ?? '—' }}
                    · {{ $moneda }}
                    @if(!empty($clinic_ruc)) · RUC {{ $clinic_ruc }} @endif
                    @if(!empty($clinic_telefono)) · {{ $clinic_telefono }} @endif
                </p>
                @if(!empty($clinic_direccion))
                    <p class="doc-sub">{{ $clinic_direccion }}</p>
                @endif
            </td>
        </tr>
    </table>
</div>

<div class="note-box">
    <strong>Importante:</strong> el total de ventas del turno incluye todos los métodos de pago.
    El <strong>efectivo esperado</strong> solo suma apertura + ventas en efectivo (Yape, tarjeta u otros no entran a la caja física).
</div>

<table class="kpi-table">
    <tr>
        <td class="kpi" width="20%">
            <div class="label">Ventas</div>
            <div class="value">{{ (int) ($arqueo['ventas_count'] ?? 0) }}</div>
            <div class="hint">del turno</div>
        </td>
        <td class="kpi" width="20%">
            <div class="label">Total vendido</div>
            <div class="value" style="font-size:11px;">{{ $fmt($arqueo['ventas_total'] ?? null, $moneda) }}</div>
        </td>
        <td class="kpi" width="20%">
            <div class="label">Otros medios</div>
            <div class="value" style="font-size:11px;">{{ $fmt($arqueo['no_efectivo_total'] ?? null, $moneda) }}</div>
            <div class="hint">no efectivo</div>
        </td>
        <td class="kpi" width="20%">
            <div class="label">Efectivo esperado</div>
            <div class="value" style="font-size:11px;">{{ $fmt($arqueo['efectivo_esperado'] ?? null, $moneda) }}</div>
        </td>
        <td class="kpi" width="20%">
            <div class="label">Diferencia</div>
            <div class="value {{ $diffClass }}" style="font-size:11px;">{{ $fmt(is_string($diff) ? $diff : null, $moneda) }}</div>
        </td>
    </tr>
</table>

<table class="two-col">
    <tr>
        <td>
            <div class="card">
                <h2>Turno</h2>
                <table class="grid">
                    <tr><td class="k">Apertura</td><td class="v">{{ optional($sesion->opened_at)->timezone(config('app.timezone'))?->format('d/m/Y H:i') ?? '—' }}</td></tr>
                    <tr><td class="k">Cierre</td><td class="v">{{ optional($sesion->closed_at)->timezone(config('app.timezone'))?->format('d/m/Y H:i') ?? '—' }}</td></tr>
                    <tr><td class="k">Abrió</td><td class="v">{{ $abierta_por ?? '—' }}</td></tr>
                    <tr><td class="k">Cerró</td><td class="v">{{ $cerrada_por ?? '—' }}</td></tr>
                    <tr><td class="k">Saldo apertura</td><td class="v">{{ $fmt($arqueo['saldo_apertura'] ?? null, $moneda) }}</td></tr>
                    <tr><td class="k">Ventas en efectivo</td><td class="v">{{ $fmt($arqueo['efectivo_ventas'] ?? null, $moneda) }}</td></tr>
                    <tr><td class="k">Efectivo esperado</td><td class="v">{{ $fmt($arqueo['efectivo_esperado'] ?? null, $moneda) }}</td></tr>
                    <tr><td class="k">Efectivo contado</td><td class="v">{{ $fmt($arqueo['efectivo_contado'] ?? null, $moneda) }}</td></tr>
                    <tr>
                        <td class="k">Diferencia</td>
                        <td class="v {{ $diffClass }}">{{ $fmt(is_string($diff) ? $diff : null, $moneda) }}</td>
                    </tr>
                </table>
            </div>
        </td>
        <td>
            <div class="card">
                <h2>Comprobantes</h2>
                <table class="grid">
                    <tr>
                        <td class="k">Tickets</td>
                        <td class="v">
                            {{ (int) ($arqueo['comprobantes']['tickets']['count'] ?? 0) }}
                            <span class="muted">· {{ $fmt($arqueo['comprobantes']['tickets']['total'] ?? null, $moneda) }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="k">Boletas</td>
                        <td class="v">
                            {{ (int) ($arqueo['comprobantes']['boletas']['count'] ?? 0) }}
                            <span class="muted">· {{ $fmt($arqueo['comprobantes']['boletas']['total'] ?? null, $moneda) }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="k">Facturas</td>
                        <td class="v">
                            {{ (int) ($arqueo['comprobantes']['facturas']['count'] ?? 0) }}
                            <span class="muted">· {{ $fmt($arqueo['comprobantes']['facturas']['total'] ?? null, $moneda) }}</span>
                        </td>
                    </tr>
                    @if(((int) ($arqueo['anuladas_count'] ?? 0)) > 0)
                        <tr>
                            <td class="k">Anuladas (excluidas)</td>
                            <td class="v">
                                {{ (int) $arqueo['anuladas_count'] }}
                                <span class="muted">· {{ $fmt($arqueo['anuladas_total'] ?? null, $moneda) }}</span>
                            </td>
                        </tr>
                    @endif
                </table>
            </div>
        </td>
    </tr>
</table>

<div class="card">
    <h2>Métodos de pago — distribución</h2>
    @if(count($chart) === 0)
        <p class="muted">Sin ventas cobradas en este turno.</p>
    @else
        <table class="bar-table">
            @foreach($chart as $row)
                <tr>
                    <td class="bar-label">{{ $metodoLabel((string) ($row['codigo'] ?? 'otro')) }}</td>
                    <td>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: {{ number_format((float) ($row['bar_pct'] ?? 0), 1, '.', '') }}%;"></div>
                        </div>
                    </td>
                    <td class="bar-meta">
                        {{ (int) ($row['count'] ?? 0) }} · {{ $fmt($row['total'] ?? null, $moneda) }}
                        · {{ number_format((float) ($row['pct'] ?? 0), 1) }}%
                    </td>
                </tr>
            @endforeach
        </table>
        <table class="grid" style="margin-top:10px;">
            <tr>
                <td class="k" style="font-weight:bold;">Método</td>
                <td class="v" style="text-align:center;font-weight:bold;">Cant.</td>
                <td class="v" style="font-weight:bold;">Total</td>
                <td class="v" style="font-weight:bold;">%</td>
            </tr>
            @foreach($chart as $metodo)
                <tr>
                    <td class="k">{{ $metodoLabel((string) ($metodo['codigo'] ?? 'otro')) }}</td>
                    <td class="v" style="text-align:center;font-weight:normal;">{{ (int) ($metodo['count'] ?? 0) }}</td>
                    <td class="v">{{ $fmt($metodo['total'] ?? null, $moneda) }}</td>
                    <td class="v">{{ number_format((float) ($metodo['pct'] ?? 0), 1) }}%</td>
                </tr>
            @endforeach
            <tr>
                <td class="k" style="font-weight:bold;">Total ventas</td>
                <td class="v" style="text-align:center;">{{ (int) ($arqueo['ventas_count'] ?? 0) }}</td>
                <td class="v">{{ $fmt($arqueo['ventas_total'] ?? null, $moneda) }}</td>
                <td class="v">100%</td>
            </tr>
        </table>
    @endif
</div>

<div class="card">
    <h2>Detalle de ventas del turno ({{ count($ventas) }})</h2>
    @if(count($ventas) === 0)
        <p class="muted">No hay ventas pagadas asociadas a esta sesión.</p>
    @else
        <table class="detail">
            <thead>
                <tr>
                    <th style="width:16%;">Número</th>
                    <th style="width:14%;">Fecha</th>
                    <th style="width:28%;">Cliente / paciente</th>
                    <th style="width:12%;">Método</th>
                    <th style="width:12%;">Comp.</th>
                    <th style="width:18%; text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ventas as $venta)
                    <tr>
                        <td>{{ $venta['numero'] ?? '—' }}</td>
                        <td>{{ $fmtDate($venta['fecha'] ?? null) }}</td>
                        <td>{{ $venta['cliente'] ?? '—' }}</td>
                        <td>{{ $metodoLabel((string) ($venta['metodo'] ?? 'otro')) }}</td>
                        <td>{{ $compLabel((string) ($venta['comprobante'] ?? 'ticket')) }}</td>
                        <td class="num">{{ $fmt($venta['total'] ?? null, $moneda) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

@if(!empty($notas_cierre))
    <div class="card">
        <h2>Notas de cierre</h2>
        <p style="margin:0; white-space: pre-wrap;">{{ $notas_cierre }}</p>
    </div>
@endif

<div class="footer">
    Generado {{ now()->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
    · Sesión {{ $sesion->getKey() }}
    · VetSaaS
</div>
</body>
</html>
