<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Arqueo de caja</title>
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
            width: 100%;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 3px solid {{ $colorPrimario }};
        }
        .clinic-name { font-size: 18px; font-weight: bold; color: {{ $colorPrimario }}; margin: 0 0 4px; }
        .doc-title { font-size: 14px; margin: 0; color: #222; font-weight: bold; }
        .doc-sub { font-size: 10px; margin: 4px 0 0; color: #666; }
        .card {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 12px 14px;
            margin-bottom: 14px;
            background: #fff;
            page-break-inside: avoid;
        }
        .card h2 {
            margin: 0 0 10px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: {{ $colorPrimario }};
        }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid td { padding: 5px 6px; vertical-align: top; border-bottom: 1px solid #eee; }
        table.grid tr:last-child td { border-bottom: 0; }
        .k { color: #555; width: 45%; }
        .v { text-align: right; font-weight: bold; font-variant-numeric: tabular-nums; }
        .muted { color: #777; font-weight: normal; font-size: 10px; }
        .kpi-table { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 0 14px; }
        .kpi {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px 12px;
            background: {{ $colorSecundario }};
            text-align: center;
        }
        .kpi .label { font-size: 9px; text-transform: uppercase; color: #666; margin-bottom: 4px; }
        .kpi .value { font-size: 15px; font-weight: bold; color: #111; }
        .diff-ok { color: #0F6E56; }
        .diff-bad { color: #B42318; }
        .footer {
            margin-top: 24px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 9px;
            color: #777;
        }
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
    $moneda = (string) ($arqueo['moneda'] ?? 'PEN');
    $diff = $arqueo['diferencia'] ?? null;
    $diffClass = '';
    if (is_string($diff) && is_numeric($diff)) {
        $diffClass = (float) $diff >= 0 ? 'diff-ok' : 'diff-bad';
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
@endphp

<div class="header">
    <p class="clinic-name">{{ $clinic_nombre }}</p>
    <p class="doc-title">Arqueo de caja / cierre de sesión</p>
    <p class="doc-sub">
        Sede: {{ $arqueo['sede_nombre'] ?? '—' }}
        · {{ $moneda }}
        @if(!empty($clinic_ruc)) · RUC {{ $clinic_ruc }} @endif
    </p>
</div>

<table class="kpi-table">
    <tr>
        <td class="kpi" width="25%">
            <div class="label">Ventas</div>
            <div class="value">{{ (int) ($arqueo['ventas_count'] ?? 0) }}</div>
        </td>
        <td class="kpi" width="25%">
            <div class="label">Total vendido</div>
            <div class="value">{{ $fmt($arqueo['ventas_total'] ?? null, $moneda) }}</div>
        </td>
        <td class="kpi" width="25%">
            <div class="label">Efectivo esperado</div>
            <div class="value">{{ $fmt($arqueo['efectivo_esperado'] ?? null, $moneda) }}</div>
        </td>
        <td class="kpi" width="25%">
            <div class="label">Diferencia</div>
            <div class="value {{ $diffClass }}">{{ $fmt(is_string($diff) ? $diff : null, $moneda) }}</div>
        </td>
    </tr>
</table>

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
            <td class="k">Diferencia (contado − esperado)</td>
            <td class="v {{ $diffClass }}">{{ $fmt(is_string($diff) ? $diff : null, $moneda) }}</td>
        </tr>
    </table>
</div>

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
                <td class="k">Anuladas (excluidas del total)</td>
                <td class="v">
                    {{ (int) $arqueo['anuladas_count'] }}
                    <span class="muted">· {{ $fmt($arqueo['anuladas_total'] ?? null, $moneda) }}</span>
                </td>
            </tr>
        @endif
    </table>
</div>

<div class="card">
    <h2>Métodos de pago</h2>
    <table class="grid">
        <tr>
            <td class="k" style="font-weight:bold;">Método</td>
            <td class="v" style="text-align:center;font-weight:bold;">Cant.</td>
            <td class="v" style="font-weight:bold;">Total</td>
        </tr>
        @foreach(($arqueo['metodos'] ?? []) as $metodo)
            <tr>
                <td class="k">{{ $metodoLabel((string) ($metodo['codigo'] ?? 'otro')) }}</td>
                <td class="v" style="text-align:center;font-weight:normal;">{{ (int) ($metodo['count'] ?? 0) }}</td>
                <td class="v">{{ $fmt($metodo['total'] ?? null, $moneda) }}</td>
            </tr>
        @endforeach
    </table>
</div>

<div class="footer">
    Generado {{ now()->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
    · Sesión {{ $sesion->getKey() }}
</div>
</body>
</html>
