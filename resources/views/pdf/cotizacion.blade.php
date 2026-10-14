@php
    /** @var \App\Models\Cotizacion $cotizacion */
    /** @var string|null $logoDataUri */
    /** @var string $colorPrimario */
    /** @var string $colorSecundario */
    /** @var string $encabezadoNombre */
    /** @var string $encabezadoSede */
    /** @var string $encabezadoDireccion */
    /** @var string $encabezadoContacto */
    /** @var string $clinicRuc */
    /** @var string $clinicRazonSocial */
    /** @var string $monedaSimbolo */
    /** @var string $atendidoPor */
    $money = fn ($n): string => $monedaSimbolo.' '.number_format((float) $n, 2, '.', ',');
    $qty = function ($n): string {
        $s = number_format((float) $n, 3, '.', '');

        return rtrim(rtrim($s, '0'), '.');
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización {{ $cotizacion->numero }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #1f2937;
            margin: 0;
            padding: 24px 30px 40px;
        }
        table { border-collapse: collapse; }
        .header { width: 100%; }
        .header td { vertical-align: top; }
        .logo { max-width: 90px; max-height: 80px; }
        .clinic-name { font-size: 15px; font-weight: bold; margin: 0; color: {{ $colorPrimario }}; }
        .clinic-sede { font-size: 11px; font-weight: bold; margin: 2px 0 0; }
        .clinic-line { font-size: 9.5px; margin: 2px 0 0; color: #374151; }
        .doc-box {
            border: 1.5px solid {{ $colorPrimario }};
            border-radius: 6px;
            text-align: center;
            padding: 8px 10px;
            width: 190px;
        }
        .doc-box .ruc { font-size: 10px; font-weight: bold; }
        .doc-box .title {
            background: {{ $colorPrimario }};
            color: #fff;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 0.08em;
            padding: 4px 0;
            margin: 5px -10px;
        }
        .doc-box .numero { font-size: 12px; font-weight: bold; }
        .section { margin-top: 14px; }
        .client { width: 100%; border: 1px solid #d1d5db; border-radius: 6px; }
        .client td { padding: 4px 8px; font-size: 10px; vertical-align: top; }
        .k { font-weight: bold; color: #111827; }
        .items { width: 100%; margin-top: 14px; }
        .items th {
            background: {{ $colorSecundario }};
            color: #111827;
            font-size: 9.5px;
            text-transform: uppercase;
            text-align: left;
            padding: 6px 6px;
            border-bottom: 1.5px solid {{ $colorPrimario }};
        }
        .items td { padding: 6px 6px; border-bottom: 1px solid #e5e7eb; font-size: 10px; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .tipo { font-size: 8.5px; color: #6b7280; text-transform: uppercase; }
        .totals { width: 260px; margin-left: auto; margin-top: 10px; }
        .totals td { padding: 4px 6px; font-size: 10.5px; }
        .totals .grand td {
            font-size: 13px;
            font-weight: bold;
            border-top: 1.5px solid {{ $colorPrimario }};
            color: {{ $colorPrimario }};
            padding-top: 6px;
        }
        .notes { margin-top: 18px; border-left: 3px solid {{ $colorPrimario }}; padding: 6px 10px; background: #f9fafb; }
        .notes-title { font-weight: bold; font-size: 10px; margin: 0 0 3px; }
        .notes-body { margin: 0; white-space: pre-line; line-height: 1.4; }
        .validity { margin-top: 14px; font-size: 9.5px; color: #374151; }
        .footer {
            position: fixed;
            bottom: 14px;
            left: 30px;
            right: 30px;
            text-align: center;
            font-size: 8.5px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            @if ($logoDataUri)
                <td style="width: 100px;">
                    <img class="logo" src="{{ $logoDataUri }}" alt="">
                </td>
            @endif
            <td style="padding-right: 12px;">
                <p class="clinic-name">{{ $encabezadoNombre }}</p>
                @if ($encabezadoSede !== '')
                    <p class="clinic-sede">Sede {{ $encabezadoSede }}</p>
                @endif
                @if ($clinicRazonSocial !== '' && mb_strtolower($clinicRazonSocial) !== mb_strtolower($encabezadoNombre))
                    <p class="clinic-line">{{ $clinicRazonSocial }}</p>
                @endif
                @if ($encabezadoDireccion !== '')
                    <p class="clinic-line">{{ $encabezadoDireccion }}</p>
                @endif
                @if ($encabezadoContacto !== '')
                    <p class="clinic-line">{{ $encabezadoContacto }}</p>
                @endif
            </td>
            <td style="width: 200px; text-align: right;">
                <div class="doc-box">
                    @if ($clinicRuc !== '')
                        <div class="ruc">RUC {{ $clinicRuc }}</div>
                    @endif
                    <div class="title">COTIZACIÓN</div>
                    <div class="numero">{{ $cotizacion->numero }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="section">
        <table class="client">
            <tr>
                <td style="width: 60%;"><span class="k">Cliente:</span> {{ $cotizacion->cliente_nombre }}</td>
                <td><span class="k">Fecha:</span> {{ $cotizacion->fecha->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td>
                    @if ($cotizacion->cliente_documento)
                        <span class="k">Documento:</span> {{ $cotizacion->cliente_documento }}
                    @endif
                </td>
                <td><span class="k">Válida hasta:</span> {{ $cotizacion->vigencia_hasta->format('d/m/Y') }}</td>
            </tr>
            @if ($cotizacion->cliente_telefono || $cotizacion->cliente_email)
                <tr>
                    <td>
                        @if ($cotizacion->cliente_telefono)
                            <span class="k">Teléfono:</span> {{ $cotizacion->cliente_telefono }}
                        @endif
                    </td>
                    <td>
                        @if ($cotizacion->cliente_email)
                            <span class="k">Correo:</span> {{ $cotizacion->cliente_email }}
                        @endif
                    </td>
                </tr>
            @endif
            @if ($cotizacion->paciente)
                <tr>
                    <td colspan="2">
                        <span class="k">Mascota:</span> {{ $cotizacion->paciente->nombre }}
                        @if ($cotizacion->paciente->especie)
                            ({{ trim($cotizacion->paciente->especie.' '.($cotizacion->paciente->raza ?? '')) }})
                        @endif
                    </td>
                </tr>
            @endif
        </table>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th class="center" style="width: 26px;">#</th>
                <th>Descripción</th>
                <th class="num" style="width: 60px;">Cant.</th>
                <th class="num" style="width: 80px;">P. unit.</th>
                <th class="num" style="width: 52px;">Dcto.</th>
                <th class="num" style="width: 90px;">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cotizacion->lineas as $i => $linea)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>
                        {{ $linea->descripcion }}
                        <div class="tipo">
                            {{ $linea->tipo_linea === 'servicio' ? 'Servicio' : 'Producto' }}@if ($linea->unidad) · {{ $linea->unidad }}@endif
                        </div>
                    </td>
                    <td class="num">{{ $qty($linea->cantidad) }}</td>
                    <td class="num">{{ $money($linea->precio_unitario) }}</td>
                    <td class="num">{{ (float) $linea->descuento_pct > 0 ? rtrim(rtrim(number_format((float) $linea->descuento_pct, 2, '.', ''), '0'), '.').'%' : '—' }}</td>
                    <td class="num">{{ $money($linea->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        @if ((float) $cotizacion->descuento_monto > 0)
            <tr>
                <td>Descuentos</td>
                <td class="num">- {{ $money($cotizacion->descuento_monto) }}</td>
            </tr>
        @endif
        @if ((float) $cotizacion->igv_porcentaje > 0)
            <tr>
                <td>Op. gravada</td>
                <td class="num">{{ $money($cotizacion->subtotal) }}</td>
            </tr>
            <tr>
                <td>IGV ({{ rtrim(rtrim(number_format((float) $cotizacion->igv_porcentaje, 2, '.', ''), '0'), '.') }}%)</td>
                <td class="num">{{ $money($cotizacion->igv_monto) }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td>Total</td>
            <td class="num">{{ $money($cotizacion->total) }}</td>
        </tr>
    </table>

    @if ($cotizacion->notas)
        <div class="notes">
            <p class="notes-title">Observaciones y condiciones</p>
            <p class="notes-body">{{ $cotizacion->notas }}</p>
        </div>
    @endif

    <p class="validity">
        Precios válidos hasta el {{ $cotizacion->vigencia_hasta->format('d/m/Y') }}. Esta cotización no es un comprobante de pago.
        @if ($atendidoPor !== '')
            Atendido por {{ $atendidoPor }}.
        @endif
    </p>

    <div class="footer">{{ $encabezadoNombre }} · Generado el {{ $generadoEn }}</div>
</body>
</html>
