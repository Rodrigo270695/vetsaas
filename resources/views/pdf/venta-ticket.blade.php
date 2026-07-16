<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ticket {{ $venta->numero }}</title>
    <style>
        @page {
            margin: 2mm;
        }
        * {
            box-sizing: border-box;
        }
        html, body {
            margin: 0;
            padding: 0;
        }
        body {
            width: 100%;
            font-family: DejaVu Sans Mono, DejaVu Sans, monospace;
            font-size: {{ $tf['fs'] }}px;
            line-height: 1.25;
            color: #111;
        }
        .pad {
            padding: 2mm {{ $tf['pad_x'] }} 3mm;
        }
        .center { text-align: center; }
        .head p {
            margin: 0 0 1px;
        }
        .logo-ticket {
            display: block;
            margin: 0 auto 3px;
            max-width: 85%;
            max-height: {{ $tf['logo_max'] }}mm;
            width: auto;
            height: auto;
        }
        .muted { color: #555; font-size: {{ $tf['fs_sm'] }}px; }
        .title {
            font-weight: bold;
            font-size: {{ $tf['fs_title'] }}px;
            margin: 0 0 2px;
            line-height: 1.2;
        }
        .rule {
            border: 0;
            border-top: 1px dashed #999;
            margin: 4px 0;
        }
        .doc-head {
            margin: 2px 0 4px;
        }
        .doc-head .subtitle {
            margin: 0 0 3px;
            font-size: {{ $tf['fs_sm'] }}px;
        }
        .badge {
            display: inline-block;
            padding: 0 5px;
            border: 1px solid #222;
            font-size: {{ $tf['fs_sm'] }}px;
            font-weight: bold;
            line-height: 1.35;
        }
        table.meta {
            width: 100%;
            border-collapse: collapse;
            font-size: {{ $tf['fs'] }}px;
            margin: 0 0 2px;
            table-layout: fixed;
        }
        table.meta td {
            padding: 1px 0;
            vertical-align: top;
            line-height: 1.3;
        }
        table.meta .lbl {
            color: #555;
            font-size: {{ $tf['fs_sm'] }}px;
            width: 36%;
            padding-right: 3px;
        }
        table.meta .val {
            font-weight: bold;
            text-align: right;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .meta-section {
            margin-bottom: 3px;
        }
        .meta-section-title {
            font-size: {{ $tf['fs_sm'] }}px;
            font-weight: bold;
            color: #333;
            margin: 0 0 2px;
            padding-bottom: 1px;
            border-bottom: 1px dotted #bbb;
            text-transform: uppercase;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: {{ $tf['fs_sm'] }}px;
            margin-top: 2px;
            table-layout: fixed;
        }
        table.items th,
        table.items td {
            text-align: left;
            vertical-align: top;
            padding: 2px 0;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        table.items th {
            border-bottom: 1px solid #333;
            font-size: {{ $tf['fs_sm'] }}px;
            padding-bottom: 3px;
        }
        table.items .num {
            text-align: right;
            white-space: nowrap;
            width: 22%;
        }
        table.items .col-qty {
            width: 18%;
        }
        table.items tfoot td {
            padding-top: 3px;
            border: 0;
            vertical-align: baseline;
        }
        table.items tfoot tr:first-child td {
            border-top: 1px solid #333;
            padding-top: 4px;
        }
        table.items .tot-label {
            text-align: left;
            font-weight: bold;
        }
        table.items tfoot .num {
            font-weight: bold;
        }
        table.items tfoot tr:last-child .num {
            font-size: {{ $tf['fs_total'] }}px;
        }
        .pay-block {
            margin-top: 2px;
        }
        .notes-block {
            margin-top: 2px;
            font-size: {{ $tf['fs_sm'] }}px;
            line-height: 1.3;
        }
        .notes-block .lbl {
            color: #555;
            display: block;
            margin-bottom: 1px;
        }
        .footer {
            margin-top: 5px;
            font-size: {{ $tf['footer'] }}px;
            line-height: 1.25;
            color: #444;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="pad">
        <div class="center head">
            @if(! empty($clinic_logo_url))
                <img src="{{ $clinic_logo_url }}" alt="" class="logo-ticket">
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

        <div class="center doc-head">
            <p class="subtitle muted">{{ __('caja.ventas.ticket.subtitle') }}</p>
            <span class="badge">{{ __('caja.ventas.ticket.estado_'.$venta->estado) }}</span>
        </div>

        <div class="meta-section">
            <p class="meta-section-title">{{ __('caja.ventas.ticket.section_venta') }}</p>
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
        </div>

        <div class="meta-section">
            <p class="meta-section-title">{{ __('caja.ventas.ticket.section_cliente') }}</p>
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
            </table>
        </div>

        @if($cajero_nombre)
            <div class="meta-section">
                <table class="meta">
                    <tr>
                        <td class="lbl">{{ __('caja.ventas.ticket.cajero') }}</td>
                        <td class="val">{{ $cajero_nombre }}</td>
                    </tr>
                </table>
            </div>
        @endif

        <hr class="rule">

        <table class="items">
            <thead>
                <tr>
                    <th>{{ __('caja.ventas.ticket.col_producto') }}</th>
                    <th class="num col-qty">{{ __('caja.ventas.ticket.col_cant') }}</th>
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
                    <td class="tot-label">{{ __('caja.ventas.ticket.subtotal') }}</td>
                    <td></td>
                    <td class="num">{{ $moneda }} {{ $venta->subtotal }}</td>
                </tr>
                @if((float) $venta->descuento_monto > 0)
                    <tr>
                        <td class="tot-label">{{ __('caja.ventas.ticket.descuento') }}</td>
                        <td></td>
                        <td class="num">- {{ $moneda }} {{ $venta->descuento_monto }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="tot-label">{{ __('caja.ventas.ticket.igv', ['pct' => $igv_porcentaje]) }}</td>
                    <td></td>
                    <td class="num">{{ $moneda }} {{ $venta->igv_monto }}</td>
                </tr>
                <tr>
                    <td class="tot-label">{{ __('caja.ventas.ticket.total') }}</td>
                    <td></td>
                    <td class="num">{{ $moneda }} {{ $venta->total }}</td>
                </tr>
            </tfoot>
        </table>

        @if($metodo_pago_label)
            <hr class="rule">
            <div class="pay-block">
                <p class="meta-section-title">{{ __('caja.ventas.ticket.section_pago') }}</p>
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
            </div>
        @endif

        @if($venta->notas)
            <hr class="rule">
            <div class="notes-block">
                <span class="lbl">{{ __('caja.ventas.ticket.notas') }}</span>
                {{ $venta->notas }}
            </div>
        @endif

        <div class="footer">
            {{ __('caja.ventas.ticket.disclaimer') }}<br>
            {{ __('caja.ventas.ticket.ancho_papel', ['mm' => $ancho_mm]) }}
        </div>
    </div>
</body>
</html>
