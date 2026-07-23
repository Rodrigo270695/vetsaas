<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Arqueo {{ $ancho_mm }}mm</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            width: 100%;
            font-family: DejaVu Sans, sans-serif;
            font-size: {{ $tf['fs'] }}px;
            line-height: 1.25;
            color: #000;
        }
        .pad { padding: 2mm {{ $tf['pad_x'] }} 3mm; }
        .center { text-align: center; }
        .logo {
            display: block;
            margin: 0 auto 3px;
            max-width: 70%;
            max-height: {{ $tf['logo_max'] }}mm;
            width: auto;
            height: auto;
        }
        .title {
            font-weight: bold;
            font-size: {{ $tf['fs_title'] }}px;
            margin: 0 0 2px;
        }
        .muted { font-size: {{ $tf['fs_sm'] }}px; }
        .rule {
            border: 0;
            border-top: 1px dashed #000;
            margin: 5px 0;
        }
        table.rows {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.rows td {
            padding: 1px 0;
            vertical-align: top;
            font-size: {{ $tf['fs'] }}px;
            line-height: 1.3;
        }
        table.rows .lbl { width: 58%; }
        table.rows .val {
            width: 42%;
            text-align: right;
            font-weight: bold;
            font-variant-numeric: tabular-nums;
        }
        .sec {
            font-weight: bold;
            font-size: {{ $tf['fs_sm'] }}px;
            text-transform: uppercase;
            margin: 4px 0 2px;
        }
        .total-row td {
            font-weight: bold;
            padding-top: 3px;
            border-top: 1px solid #000;
        }
        .footer {
            margin-top: 6px;
            font-size: {{ $tf['footer'] }}px;
            text-align: center;
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

<div class="pad">
    <div class="center head">
        @if($logoDataUri !== '')
            <img src="{{ $logoDataUri }}" class="logo" alt="Logo">
        @endif
        <p class="title">{{ $clinic_nombre }}</p>
        <p class="muted">Arqueo / cierre de caja</p>
        <p class="muted">
            {{ $arqueo['sede_nombre'] ?? '—' }}
            @if(!empty($clinic_ruc)) · RUC {{ $clinic_ruc }} @endif
        </p>
    </div>

    <hr class="rule">

    <table class="rows">
        <tr>
            <td class="lbl">Ventas del turno</td>
            <td class="val">{{ (int) ($arqueo['ventas_count'] ?? 0) }}</td>
        </tr>
        <tr>
            <td class="lbl">Total cobrado</td>
            <td class="val">{{ $fmt($arqueo['ventas_total'] ?? null, $moneda) }}</td>
        </tr>
        <tr>
            <td class="lbl">Productos</td>
            <td class="val">{{ $fmt($arqueo['productos_total'] ?? null, $moneda) }}</td>
        </tr>
        <tr>
            <td class="lbl">Servicios</td>
            <td class="val">{{ $fmt($arqueo['servicios_total'] ?? null, $moneda) }}</td>
        </tr>
        <tr>
            <td class="lbl">Otros medios</td>
            <td class="val">{{ $fmt($arqueo['no_efectivo_total'] ?? null, $moneda) }}</td>
        </tr>
        <tr>
            <td class="lbl">Efectivo a cuadrar</td>
            <td class="val">{{ $fmt($arqueo['efectivo_esperado'] ?? null, $moneda) }}</td>
        </tr>
        <tr>
            <td class="lbl">Efectivo contado</td>
            <td class="val">{{ $fmt($arqueo['efectivo_contado'] ?? null, $moneda) }}</td>
        </tr>
        <tr>
            <td class="lbl">Diferencia</td>
            <td class="val">{{ $fmt(is_string($diff) ? $diff : null, $moneda) }}</td>
        </tr>
    </table>

    <hr class="rule">
    <p class="sec">Comprobantes</p>
    <table class="rows">
        <tr>
            <td class="lbl">Tickets</td>
            <td class="val">
                {{ (int) ($arqueo['comprobantes']['tickets']['count'] ?? 0) }}
                · {{ $fmt($arqueo['comprobantes']['tickets']['total'] ?? null, $moneda) }}
            </td>
        </tr>
        <tr>
            <td class="lbl">Boletas</td>
            <td class="val">
                {{ (int) ($arqueo['comprobantes']['boletas']['count'] ?? 0) }}
                · {{ $fmt($arqueo['comprobantes']['boletas']['total'] ?? null, $moneda) }}
            </td>
        </tr>
        <tr>
            <td class="lbl">Facturas</td>
            <td class="val">
                {{ (int) ($arqueo['comprobantes']['facturas']['count'] ?? 0) }}
                · {{ $fmt($arqueo['comprobantes']['facturas']['total'] ?? null, $moneda) }}
            </td>
        </tr>
    </table>

    <hr class="rule">
    <p class="sec">Métodos de pago</p>
    <table class="rows">
        @foreach(($arqueo['metodos'] ?? []) as $metodo)
            <tr>
                <td class="lbl">{{ $metodoLabel((string) ($metodo['codigo'] ?? 'otro')) }}</td>
                <td class="val">
                    {{ (int) ($metodo['count'] ?? 0) }}
                    · {{ $fmt($metodo['total'] ?? null, $moneda) }}
                </td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td class="lbl">Total cobrado</td>
            <td class="val">
                {{ (int) ($arqueo['ventas_count'] ?? 0) }}
                · {{ $fmt($arqueo['ventas_total'] ?? null, $moneda) }}
            </td>
        </tr>
    </table>

    <hr class="rule">
    <p class="sec">Cuadre de efectivo</p>
    <table class="rows">
        <tr>
            <td class="lbl">Apertura</td>
            <td class="val">{{ $fmt($arqueo['saldo_apertura'] ?? null, $moneda) }}</td>
        </tr>
        <tr>
            <td class="lbl">+ Ventas efectivo</td>
            <td class="val">{{ $fmt($arqueo['efectivo_ventas'] ?? null, $moneda) }}</td>
        </tr>
        <tr>
            <td class="lbl">= Esperado</td>
            <td class="val">{{ $fmt($arqueo['efectivo_esperado'] ?? null, $moneda) }}</td>
        </tr>
        <tr>
            <td class="lbl">Contado</td>
            <td class="val">{{ $fmt($arqueo['efectivo_contado'] ?? null, $moneda) }}</td>
        </tr>
        <tr class="total-row">
            <td class="lbl">Diferencia</td>
            <td class="val">{{ $fmt(is_string($diff) ? $diff : null, $moneda) }}</td>
        </tr>
    </table>

    @if(!empty($notas_cierre))
        <hr class="rule">
        <p class="sec">Notas</p>
        <p class="muted" style="margin:0; white-space:pre-wrap;">{{ $notas_cierre }}</p>
    @endif

    <hr class="rule">
    <table class="rows">
        <tr>
            <td class="lbl">Apertura</td>
            <td class="val">{{ optional($sesion->opened_at)->timezone(config('app.timezone'))?->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Cierre</td>
            <td class="val">{{ optional($sesion->closed_at)->timezone(config('app.timezone'))?->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Abrió</td>
            <td class="val">{{ $abierta_por ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Cerró</td>
            <td class="val">{{ $cerrada_por ?? '—' }}</td>
        </tr>
    </table>

    <p class="footer">
        {{ now()->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
        · {{ $ancho_mm }}mm · VetSaaS
    </p>
</div>
</body>
</html>
