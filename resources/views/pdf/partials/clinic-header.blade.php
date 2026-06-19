<div class="header">
    <div class="header-left">
        @if ($logoDataUri)
            <img class="logo" src="{{ $logoDataUri }}" alt="">
        @endif
    </div>
    <div class="header-mid">
        <p class="clinic-name">{{ $clinicNombre }}</p>
        <p class="doc-title">{{ $docTitle }}</p>
        @if (! empty($docSubtitle))
            <p class="doc-sub">{{ $docSubtitle }}</p>
        @endif
    </div>
    <div class="header-right">
        @if ($clinicDireccion)
            <div>{{ $clinicDireccion }}</div>
        @endif
        @if ($clinicTelefono)
            <div>{{ $clinicTelefono }}</div>
        @endif
        @if ($clinicEmail)
            <div>{{ $clinicEmail }}</div>
        @endif
        @if ($clinicWeb)
            <div class="muted">{{ $clinicWeb }}</div>
        @endif
    </div>
</div>
