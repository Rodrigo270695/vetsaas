@php
    /** @var \App\Models\DocumentoAutorizacionEnvio $envio */
    $docTitle = $envio->titulo;
    $docSubtitle = 'Documento firmado';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $docTitle }}</title>
    @include('pdf.partials.clinic-styles')
</head>
<body>
    @include('pdf.partials.clinic-header')
    @include('pdf.partials.patient-owner-cards')

    <h2 style="margin: 0 0 8px; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.03em; color: {{ $colorPrimario }};">
        {{ $docTitle }}
    </h2>
    <div style="font-size: 10px; line-height: 1.45; color: #1f2937;">
        {!! $cuerpoHtml !!}
    </div>

    <div style="margin-top: 22px; padding-top: 10px; border-top: 1px solid #e5e7eb;">
        <p style="margin: 0 0 4px; font-size: 9px; text-transform: uppercase; color: #6b7280;">Firma del titular</p>
        @if (! empty($firmaDataUri))
            <img src="{{ $firmaDataUri }}" alt="Firma" style="height: 48px;">
        @endif
        <p style="margin: 8px 0 0; font-size: 10px;">
            {{ $envio->firmante_nombre }}
            @if ($envio->firmante_documento)
                · {{ $envio->firmante_documento }}
            @endif
        </p>
        <p style="margin: 2px 0 0; font-size: 8px; color: #6b7280;">
            Firmado el {{ optional($envio->firmado_at)->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
        </p>
    </div>

    <div class="footer">
        <div>{{ $generadoEn }}</div>
    </div>
</body>
</html>
