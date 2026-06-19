@php
    /** @var \App\Models\Paciente $paciente */
    /** @var string $propietarioNombre */
    /** @var \Illuminate\Support\Collection<int, array<string, mixed>>|array<int, array<string, mixed>> $entries */
    /** @var int $entriesCount */
    /** @var string $generadoEn */
    $docTitle = __('historial_clinico.document_title');
    $docSubtitle = __('historial_clinico.document_subtitle');
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

    <div class="card card-white">
        <h2>{{ __('historial_clinico.section_timeline') }}</h2>
        @if ($entriesCount === 0)
            <p class="muted" style="margin:0;">{{ __('historial_clinico.empty') }}</p>
        @else
            @foreach ($entries as $entry)
                @include('pdf.partials.historial-entry', ['entry' => $entry])
            @endforeach
        @endif
    </div>

    <div class="footer">
        <div>{{ __('carnet_vacunacion.footer_generated', ['fecha' => $generadoEn]) }}</div>
        <div class="muted" style="margin-top: 5px; line-height: 1.35;">{{ __('historial_clinico.footer_disclaimer') }}</div>
    </div>
</body>
</html>
