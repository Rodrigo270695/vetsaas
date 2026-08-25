@php
    /** @var \App\Models\Paciente $paciente */
    /** @var string $propietarioNombre */
    /** @var array<string, mixed> $entry */
    /** @var string $generadoEn */
    $docTitle = __('historial_clinico.consulta_title');
    $docSubtitle = __('historial_clinico.consulta_subtitle');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $docTitle }} — {{ $paciente->nombre }}</title>
    @include('pdf.partials.clinic-styles')
</head>
<body>
    @include('pdf.partials.clinic-header')
    @include('pdf.partials.patient-owner-cards')

    <h2 style="margin: 0 0 6px; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.03em; color: {{ $colorPrimario }};">
        {{ __('historial_clinico.section_record') }}
    </h2>
    @include('pdf.partials.historial-entry', ['entry' => $entry])

    <div class="footer">
        <div>{{ __('carnet_vacunacion.footer_generated', ['fecha' => $generadoEn]) }}</div>
        <div class="muted" style="margin-top: 3px; line-height: 1.3;">{{ __('historial_clinico.footer_disclaimer') }}</div>
    </div>
</body>
</html>
