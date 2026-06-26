<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('consulta-cargos.ticket.document_title') }}</title>
    @php($tf = \App\Support\Caja\TicketAnchoMm::typography($ancho_mm))
    <style>
        :root {
            --paper: {{ $ancho_mm }}mm;
            --fs: {{ $tf['fs'] }}px;
            --fs-sm: {{ $tf['fs_sm'] }}px;
            --fs-title: {{ $tf['fs_title'] }}px;
            --fs-total: {{ $tf['fs_total'] }}px;
        }
        @page {
            size: {{ $ancho_mm }}mm auto;
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
            width: var(--paper);
            max-width: var(--paper);
            font-family: ui-monospace, 'Cascadia Code', 'Consolas', monospace;
            font-size: var(--fs);
            line-height: 1.35;
            color: #111;
            background: #fff;
        }
        .pad {
            padding: 2mm {{ $tf['pad_x'] }} 3mm;
        }
        .center { text-align: center; }
        .logo-wrap {
            margin: 0 auto 5px;
        }
        .logo-ticket {
            display: block;
            margin: 0 auto;
            max-width: 88%;
            max-height: {{ $tf['logo_max'] }}mm;
            width: auto;
            height: auto;
            object-fit: contain;
        }
        .muted { color: #444; font-size: var(--fs-sm); }
        .title { font-weight: 700; font-size: var(--fs-title); margin: 0 0 2px; }
        .rule {
            border: 0;
            border-top: 1px dashed #999;
            margin: 6px 0;
        }
        .row { margin: 3px 0; }
        .row-strong { font-weight: 600; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: var(--fs-sm);
        }
        th, td {
            text-align: left;
            vertical-align: top;
            padding: 2px 0;
            word-break: break-word;
        }
        th { border-bottom: 1px solid #333; }
        .num { text-align: right; white-space: nowrap; }
        tfoot td {
            padding-top: 4px;
            border: 0;
            vertical-align: baseline;
        }
        tfoot tr:first-child td {
            border-top: 1px solid #333;
            padding-top: 6px;
        }
        .tot-label {
            text-align: left;
            font-weight: 600;
        }
        .tot-label-total {
            font-size: 11px;
        }
        tfoot .num {
            font-weight: 600;
        }
        tfoot tr:last-child .num {
            font-size: 12px;
        }
        .badge {
            display: inline-block;
            padding: 1px 6px;
            border: 1px solid #333;
            font-size: var(--fs-sm);
            font-weight: 600;
        }
        .footer {
            margin-top: 8px;
            font-size: 9px;
            line-height: 1.3;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="pad">
    <div class="center">
        @if(! empty($clinic_logo_url))
            <div class="logo-wrap">
                <img
                    src="{{ $clinic_logo_url }}"
                    alt=""
                    class="logo-ticket"
                >
            </div>
        @endif
        <p class="title">{{ $clinic_nombre }}</p>
        @if($clinic_ruc)
            <p class="muted">{{ __('consulta-cargos.ticket.label_ruc', ['num' => $clinic_ruc]) }}</p>
        @endif
        @if($clinic_direccion)
            <p class="muted">{{ $clinic_direccion }}</p>
        @endif
        @if($clinic_telefono)
            <p class="muted">{{ __('consulta-cargos.ticket.label_telefono', ['tel' => $clinic_telefono]) }}</p>
        @endif
    </div>

    <hr class="rule">

    <p class="center muted" style="margin:4px 0">{{ __('consulta-cargos.ticket.subtitle') }}</p>
    <p class="center" style="margin:2px 0">
        <span class="badge">{{ $cargo->estado === \App\Models\ConsultaCargo::ESTADO_CONFIRMADO ? __('consulta-cargos.ticket.estado_confirmado') : __('consulta-cargos.ticket.estado_borrador') }}</span>
    </p>

    <div class="row"><span class="muted">{{ __('consulta-cargos.ticket.paciente') }}</span><br>{{ $paciente_nombre ?? $consulta?->historiaClinica?->paciente?->nombre ?? '—' }}</div>
    @if(($veterinario_nombre ?? null) || ($consulta?->veterinario ?? null))
        <div class="row"><span class="muted">{{ __('consulta-cargos.ticket.veterinario') }}</span><br>{{ $veterinario_nombre ?? $consulta?->veterinario?->name }}</div>
    @endif
    <div class="row"><span class="muted">{{ __('consulta-cargos.ticket.atencion') }}</span><br>{{ ($fecha_referencia ?? $consulta?->atendido_at)?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</div>

    <hr class="rule">

    <table>
        <thead>
        <tr>
            <th>{{ __('consulta-cargos.ticket.col_concepto') }}</th>
            <th class="num">{{ __('consulta-cargos.ticket.col_cant') }}</th>
            <th class="num">{{ __('consulta-cargos.ticket.col_pu') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($lineas as $ln)
            <tr>
                <td>
                    <span class="muted">[{{ $ln['tipo'] }}]</span><br>
                    {{ $ln['concepto'] }}
                </td>
                <td class="num">{{ $ln['cantidad'] }}</td>
                <td class="num">{{ $ln['precio_unitario'] }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr>
            <td class="tot-label">{{ __('consulta-cargos.ticket.subtotal') }}</td>
            <td></td>
            <td class="num">{{ $moneda }} {{ $cargo->subtotal_sin_igv }}</td>
        </tr>
        <tr>
            <td class="tot-label">{{ __('consulta-cargos.ticket.igv', ['pct' => $igv_porcentaje]) }}</td>
            <td></td>
            <td class="num">{{ $moneda }} {{ $cargo->igv_importe }}</td>
        </tr>
        <tr>
            <td class="tot-label tot-label-total">{{ __('consulta-cargos.ticket.total') }}</td>
            <td></td>
            <td class="num">{{ $moneda }} {{ $cargo->total }}</td>
        </tr>
        </tfoot>
    </table>
    @if($precio_incluye_igv)
        <p class="muted" style="margin:6px 0 0">{{ __('consulta-cargos.ticket.hint_precios_con_igv', ['pct' => $igv_porcentaje]) }}</p>
    @endif

    <div class="footer">
        {{ __('consulta-cargos.ticket.disclaimer') }}<br>
        {{ __('consulta-cargos.ticket.ancho_papel', ['mm' => $ancho_mm]) }}
    </div>
</div>
@if($auto_print)
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 350);
        });
    </script>
@endif
</body>
</html>
