@php
    /** @var string|null $logoDataUri */
    /** @var \App\Models\Receta $receta */
    /** @var string $encabezadoNombre */
    /** @var string $encabezadoDireccion */
    /** @var string $encabezadoContacto */
    /** @var string $propietarioNombre */
    /** @var string $documento */
    /** @var string $direccionPropietario */
    /** @var string $mascota */
    /** @var string $especie */
    /** @var string $raza */
    /** @var string $sexo */
    /** @var string $reproductivo */
    /** @var string $fechaNacimiento */
    /** @var string $microchip */
    /** @var string $edad */
    /** @var string $peso */
    /** @var string $fechaAtencion */
    /** @var string $atendidoPor */
    /** @var string $motivo */
    /** @var string $indicacionMedica */
    /** @var string $examenes */
    /** @var string $consultaControl */
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
            color: #111;
            margin: 0;
            padding: 22px 28px 36px;
        }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .header td { vertical-align: middle; }
        .logo { max-width: 72px; max-height: 72px; }
        .clinic-name { font-size: 13px; font-weight: bold; margin: 0; letter-spacing: 0.02em; }
        .clinic-line { font-size: 10px; margin: 2px 0 0; }
        .fields { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .fields td { padding: 2px 10px 2px 0; vertical-align: top; font-size: 11px; }
        .k { font-weight: bold; }
        .group { border-bottom: 1px solid #222; padding-bottom: 4px; }
        .receta-title {
            font-size: 12px;
            font-weight: bold;
            margin: 14px 0 8px;
            border-bottom: 1px solid #222;
            padding-bottom: 2px;
        }
        .block-title {
            font-size: 11px;
            font-weight: bold;
            text-decoration: underline;
            margin: 10px 0 3px;
        }
        .block-body { margin: 0; line-height: 1.35; }
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
        }
        .body-wrap { position: relative; z-index: 1; }
        .vet-firma { margin: 22px 0 8px; text-align: center; }
        .vet-firma img { max-height: 72px; max-width: 200px; }
        .vet-firma-name { margin-top: 4px; font-size: 10px; font-weight: bold; }
        .vet-firma-cmvp { font-size: 9px; }
        .vet-firma-role { font-size: 8px; color: #444; }
    </style>
</head>
<body>
    @if ($receta->estado === \App\Models\Receta::ESTADO_ANULADA)
        <div class="stamp">{{ __('recetas.pdf.anulada_stamp') }}</div>
    @endif

    <div class="body-wrap">
        <table class="header">
            <tr>
                @if ($logoDataUri)
                    <td style="width: 84px;">
                        <img class="logo" src="{{ $logoDataUri }}" alt="">
                    </td>
                @endif
                <td>
                    <p class="clinic-name">{{ $encabezadoNombre }}</p>
                    @if ($encabezadoDireccion !== '')
                        <p class="clinic-line">{{ $encabezadoDireccion }}</p>
                    @endif
                    @if ($encabezadoContacto !== '')
                        <p class="clinic-line">{{ $encabezadoContacto }}</p>
                    @endif
                </td>
            </tr>
        </table>

        <table class="fields group">
            <tr>
                <td style="width: 62%;"><span class="k">{{ __('recetas.pdf.propietario') }}:</span> {{ $propietarioNombre }}</td>
                <td><span class="k">{{ __('recetas.pdf.documento') }}:</span> {{ $documento }}</td>
            </tr>
            <tr>
                <td colspan="2"><span class="k">{{ __('recetas.pdf.direccion') }}:</span> {{ $direccionPropietario }}</td>
            </tr>
        </table>

        <table class="fields group">
            <tr>
                <td style="width: 62%;"><span class="k">{{ __('recetas.pdf.mascota') }}:</span> {{ $mascota }}</td>
                <td><span class="k">{{ __('recetas.pdf.historia') }}:</span></td>
            </tr>
            <tr>
                <td><span class="k">{{ __('recetas.pdf.especie') }}:</span> {{ $especie }}</td>
                <td><span class="k">{{ __('recetas.pdf.raza') }}:</span> {{ $raza }}</td>
            </tr>
            <tr>
                <td><span class="k">{{ __('recetas.pdf.sexo') }}:</span> {{ $sexo }}</td>
                <td><span class="k">{{ __('recetas.pdf.reproductivo') }}:</span> {{ $reproductivo }}</td>
            </tr>
            <tr>
                <td><span class="k">{{ __('recetas.pdf.nacimiento') }}:</span> {{ $fechaNacimiento }}</td>
                <td><span class="k">{{ __('recetas.pdf.microchip') }}:</span> {{ $microchip }}</td>
            </tr>
            <tr>
                <td colspan="2"><span class="k">{{ __('recetas.pdf.edad') }}:</span> {{ $edad }}</td>
            </tr>
            <tr>
                <td colspan="2"><span class="k">{{ __('recetas.pdf.peso') }}:</span> {{ $peso }}</td>
            </tr>
        </table>

        <table class="fields group">
            <tr>
                <td style="width: 62%;"><span class="k">{{ __('recetas.pdf.fecha_atencion') }}:</span> {{ $fechaAtencion }}</td>
                <td><span class="k">{{ __('recetas.pdf.atendido_por') }}:</span> {{ $atendidoPor }}</td>
            </tr>
            <tr>
                <td colspan="2"><span class="k">{{ __('recetas.pdf.motivo') }}:</span> {{ $motivo }}</td>
            </tr>
        </table>

        <div class="receta-title">{{ __('recetas.pdf.receta') }}</div>

        <div class="block-title">{{ __('recetas.pdf.indicacion') }}</div>
        @if ($indicacionMedica !== '')
            <p class="block-body">{{ $indicacionMedica }}</p>
        @endif

        <div class="block-title">{{ __('recetas.pdf.examenes') }}</div>
        @if ($examenes !== '')
            <p class="block-body">{{ $examenes }}</p>
        @endif

        <div class="block-title">{{ __('recetas.pdf.control') }}</div>
        @if ($consultaControl !== '')
            <p class="block-body">{{ $consultaControl }}</p>
        @endif

        <div class="block-title">{{ __('recetas.pdf.alarmas') }}</div>
    </div>

    @include('pdf.partials.vet-firma')
</body>
</html>
