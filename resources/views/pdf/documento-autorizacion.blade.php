@php
    /** @var \App\Models\DocumentoAutorizacionEnvio $envio */
    $cuerpoTieneLogo = str_contains((string) $cuerpoHtml, 'auth-doc-logo');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $envio->titulo }}</title>
    @include('pdf.partials.clinic-styles')
    <style>
        body { padding: 28px 32px 48px; }
        .letterhead {
            text-align: center;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1.5px solid {{ $colorPrimario }};
        }
        .letterhead .clinic { margin: 0; font-size: 13px; font-weight: bold; color: {{ $colorPrimario }}; }
        .letterhead .meta { margin: 2px 0 0; font-size: 8px; color: #6b7280; }
        .auth-cuerpo { font-size: 10.5px; line-height: 1.55; color: #111827; }
        .auth-cuerpo p { margin: 0 0 8px; }
        .auth-cuerpo h2, .auth-cuerpo h3 { margin: 0 0 10px; font-size: 13px; text-align: center; }
        .auth-cuerpo ol, .auth-cuerpo ul { margin: 8px 0 12px; padding-left: 22px; }
        .auth-cuerpo ol { list-style-type: decimal; }
        .auth-cuerpo ul { list-style-type: disc; }
        .auth-cuerpo li { display: list-item; margin: 0 0 5px; }
        .auth-cuerpo img.auth-doc-logo {
            display: block;
            margin: 0 auto 10px;
            height: 58px;
            width: auto;
            max-width: 150px;
        }
        .firma-box {
            margin-top: 28px;
            padding-top: 12px;
            border-top: 1px solid #d1d5db;
        }
        .firma-label { margin: 0 0 6px; font-size: 8px; letter-spacing: 0.08em; text-transform: uppercase; color: #6b7280; }
        .firma-line { margin-top: 4px; font-size: 10px; }
        .firma-date { margin: 3px 0 0; font-size: 8px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="letterhead">
        @if (! $cuerpoTieneLogo && ! empty($logoDataUri))
            <img src="{{ $logoDataUri }}" alt="" style="height: 44px; margin-bottom: 6px;">
        @endif
        <p class="clinic">{{ $clinicNombre }}</p>
        @if (! empty($clinicEmail) || ! empty($clinicTelefono))
            <p class="meta">
                {{ implode(' · ', array_filter([$clinicTelefono ?? null, $clinicEmail ?? null])) }}
            </p>
        @endif
    </div>

    <div class="auth-cuerpo">
        {!! $cuerpoHtml !!}
    </div>

    <div class="firma-box">
        <p class="firma-label">Firma del titular</p>
        @if (! empty($firmaDataUri))
            <img src="{{ $firmaDataUri }}" alt="Firma" style="height: 52px;">
        @endif
        <p class="firma-line">
            {{ $envio->firmante_nombre }}
            @if ($envio->firmante_documento)
                · {{ $envio->firmante_documento }}
            @endif
        </p>
        <p class="firma-date">
            Firmado el {{ optional($envio->firmado_at)->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
        </p>
    </div>

    <div class="footer">
        <div>{{ $generadoEn }}</div>
    </div>
</body>
</html>
