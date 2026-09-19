@if (! empty($showVetFirma))
    <div class="vet-firma">
        @if (! empty($vetFirmaDataUri))
            <img src="{{ $vetFirmaDataUri }}" alt="">
        @endif
        @if (! empty($vetFirmaNombre))
            <div class="vet-firma-name">{{ $vetFirmaNombre }}</div>
        @endif
        @if (! empty($vetFirmaColegiatura))
        <div class="vet-firma-cmvp">{{ __('config_clinic.pdf.vet_cmvp', ['nro' => $vetFirmaColegiatura]) }}</div>
        @endif
        <div class="vet-firma-role">{{ __('config_clinic.pdf.vet_role') }}</div>
    </div>
@endif
