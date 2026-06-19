@php
    $sexo = $paciente->sexo ? strtolower((string) $paciente->sexo) : null;
    $sexoTxt = match ($sexo) {
        'm' => __('carnet_vacunacion.sex_m'),
        'h' => __('carnet_vacunacion.sex_h'),
        'u' => __('carnet_vacunacion.sex_u'),
        default => $paciente->sexo ? (string) $paciente->sexo : '—',
    };
    $especieRaza = collect([$paciente->especie, $paciente->raza])->filter()->implode(' · ') ?: '—';
@endphp

<div class="card">
    <h2>{{ __('carnet_vacunacion.section_patient') }}</h2>
    <table class="grid">
        <tr>
            <td class="k">{{ __('carnet_vacunacion.label_name') }}</td>
            <td>{{ $paciente->nombre }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('carnet_vacunacion.label_species') }}</td>
            <td>{{ $especieRaza }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('carnet_vacunacion.label_sex') }}</td>
            <td>{{ $sexoTxt }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('carnet_vacunacion.label_microchip') }}</td>
            <td>{{ $paciente->microchip ? $paciente->microchip : '—' }}</td>
        </tr>
    </table>
</div>

<div class="card card-white">
    <h2>{{ __('carnet_vacunacion.section_owner') }}</h2>
    <p style="margin:0;">{{ $propietarioNombre }}</p>
</div>
