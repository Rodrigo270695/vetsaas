<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ticket {{ $venta->numero }}</title>
    <style>
        @page {
            size: {{ $ancho_mm }}mm auto;
            margin: 2mm;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0 auto;
            padding: 0;
            width: {{ $ancho_mm }}mm;
            max-width: {{ $ancho_mm }}mm;
            font-family: DejaVu Sans Mono, DejaVu Sans, monospace;
            font-size: {{ $tf['fs'] }}px;
            line-height: 1.25;
            color: #111;
        }
        .center { text-align: center; }
        .title {
            font-weight: bold;
            font-size: {{ $tf['fs_title'] }}px;
            margin: 0 0 2px;
        }
        .muted { color: #555; font-size: {{ $tf['fs_sm'] }}px; margin: 0 0 1px; }
        .rule {
            border: 0;
            border-top: 1px dashed #999;
            margin: 6px 0;
        }
        .badge {
            display: inline-block;
            padding: 1px 6px;
            border: 1px solid #222;
            font-size: {{ $tf['fs_sm'] }}px;
            font-weight: bold;
        }
        .section-title {
            font-size: {{ $tf['fs_sm'] }}px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 4px 0 2px;
            border-bottom: 1px dotted #bbb;
            padding-bottom: 1px;
        }
        table.meta { width: 100%; border-collapse: collapse; margin: 0 0 2px; }
        table.meta td { padding: 1px 0; vertical-align: top; }
        table.meta .lbl { color: #555; font-size: {{ $tf['fs_sm'] }}px; width: 38%; }
        table.meta .val { font-weight: bold; text-align: right; }
        table.items { width: 100%; border-collapse: collapse; font-size: {{ $tf['fs_sm'] }}px; }
        table.items th, table.items td { padding: 2px 0; vertical-align: top; text-align: left; }
        table.items th { border-bottom: 1px solid #333; }
        table.items .num { text-align: right; white-space: nowrap; }
        table.items tfoot td { padding-top: 3px; }
        table.items tfoot tr:first-child td { border-top: 1px solid #333; padding-top: 4px; }
        table.items .tot { font-weight: bold; }
        table.items .tot-num { font-size: {{ $tf['fs_total'] }}px; font-weight: bold; text-align: right; }
        .footer {
            margin-top: 8px;
            font-size: {{ $tf['footer'] }}px;
            color: #444;
            text-align: center;
        }
        .logo { max-width: 70%; max-height: {{ $tf['logo_max'] }}mm; margin: 0 auto 4px; display: block; }
    </style>
</head>
<body>
    <div class="center">
        @if(! empty($clinic_logo_url))
            <img src="{{ $clinic_logo_url }}" alt="" class="logo">
        @endif
        <p class="title">{{ $clinic_nombre }}</p>
        @if($clinic_ruc)
            <p class="muted">{{ __('caja.ventas.ticket.label_ruc', ['num' => $clinic_ruc]) }}</p>
        @endif
        @if($clinic_direccion)
            <p class="muted">{{ $clinic_direccion }}</p>
        @endif
        @if($clinic_telefono)
            <p class="muted">{{ __('caja.ventas.ticket.label_telefono', ['tel' => $clinic_telefono]) }}</p>
        @endif
    </div>

    <hr class="rule">

    <div class="center">
        <p class="muted">{{ __('caja.ventas.ticket.subtitle') }}</p>
        <span class="badge">{{ __('caja.ventas.ticket.estado_'.$venta->estado) }}</span>
    </div>

    <p class="section-title">{{ __('caja.ventas.ticket.section_venta') }}</p>
    <table class="meta">
        <tr>
            <td class="lbl">{{ __('caja.ventas.ticket.numero') }}</td>
            <td class="val">{{ $venta->numero }}</td>
        </tr>
        <tr>
            <td class="lbl">{{ __('caja.ventas.ticket.fecha') }}</td>
            <td class="val">{{ $fecha_cobro }}</td>
        </tr>
        @if($sede_nombre)
            <tr>
                <td class="lbl">{{ __('caja.ventas.ticket.sede') }}</td>
                <td class="val">{{ $sede_nombre }}</td>
            </tr>
        @endif
        @if(! empty($cpe_numero))
            <tr>
                <td class="lbl">{{ __('caja.ventas.ticket.cpe_numero') }}</td>
                <td class="val">{{ $cpe_numero }}</td>
            </tr>
        @endif
    </table>

    <p class="section-title">{{ __('caja.ventas.ticket.section_cliente') }}</p>
    <table class="meta">
        <tr>
            <td class="lbl">{{ __('caja.ventas.ticket.cliente') }}</td>
            <td class="val">{{ $cliente_nombre }}</td>
        </tr>
        @if($cliente_doc)
            <tr>
                <td class="lbl">{{ __('caja.ventas.ticket.doc_cliente') }}</td>
                <td class="val">{{ $cliente_doc }}</td>
            </tr>
        @endif
        @if($paciente_nombre)
            <tr>
                <td class="lbl">{{ __('caja.ventas.ticket.paciente') }}</td>
                <td class="val">{{ $paciente_nombre }}</td>
            </tr>
        @endif
        @if($cajero_nombre)
            <tr>
                <td class="lbl">{{ __('caja.ventas.ticket.cajero') }}</td>
                <td class="val">{{ $cajero_nombre }}</td>
            </tr>
        @endif
    </table>

    <hr class="rule">

    <table class="items">
        <thead>
            <tr>
                <th>{{ __('caja.ventas.ticket.col_producto') }}</th>
                <th class="num">{{ __('caja.ventas.ticket.col_cant') }}</th>
                <th class="num">{{ __('caja.ventas.ticket.col_sub') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lineas as $ln)
                <tr>
                    <td>{{ $ln['descripcion'] }}</td>
                    <td class="num">{{ $ln['cantidad'] }}</td>
                    <td class="num">{{ $ln['subtotal'] }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td class="tot">{{ __('caja.ventas.ticket.subtotal') }}</td>
                <td></td>
                <td class="num">{{ $moneda }} {{ $venta->subtotal }}</td>
            </tr>
            @if((float) $venta->descuento_monto > 0)
                <tr>
                    <td class="tot">{{ __('caja.ventas.ticket.descuento') }}</td>
                    <td></td>
                    <td class="num">- {{ $moneda }} {{ $venta->descuento_monto }}</td>
                </tr>
            @endif
            <tr>
                <td class="tot">{{ __('caja.ventas.ticket.igv', ['pct' => $igv_porcentaje]) }}</td>
                <td></td>
                <td class="num">{{ $moneda }} {{ $venta->igv_monto }}</td>
            </tr>
            <tr>
                <td class="tot">{{ __('caja.ventas.ticket.total') }}</td>
                <td></td>
                <td class="tot-num">{{ $moneda }} {{ $venta->total }}</td>
            </tr>
        </tfoot>
    </table>

    @if($metodo_pago_label)
        <hr class="rule">
        <p class="section-title">{{ __('caja.ventas.ticket.section_pago') }}</p>
        <table class="meta">
            <tr>
                <td class="lbl">{{ __('caja.ventas.ticket.metodo_pago') }}</td>
                <td class="val">{{ $metodo_pago_label }}</td>
            </tr>
            @if($venta->metodo_pago === 'efectivo' && $venta->monto_recibido !== null)
                <tr>
                    <td class="lbl">{{ __('caja.ventas.ticket.monto_recibido') }}</td>
                    <td class="val">{{ $moneda }} {{ $venta->monto_recibido }}</td>
                </tr>
                @if($venta->vuelto !== null)
                    <tr>
                        <td class="lbl">{{ __('caja.ventas.ticket.vuelto') }}</td>
                        <td class="val">{{ $moneda }} {{ $venta->vuelto }}</td>
                    </tr>
                @endif
            @endif
        </table>
    @endif

    @if($venta->notas)
        <hr class="rule">
        <p class="muted">{{ __('caja.ventas.ticket.notas') }}</p>
        <p>{{ $venta->notas }}</p>
    @endif

    <div class="footer">
        {{ __('caja.ventas.ticket.disclaimer') }}<br>
        {{ __('caja.ventas.ticket.ancho_papel', ['mm' => $ancho_mm]) }}
    </div>
</body>
</html>
