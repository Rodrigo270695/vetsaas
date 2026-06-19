@php
    /** @var array<string, mixed> $entry */
@endphp
<div class="entry">
    <div class="entry-head">
        <div class="entry-badges">
            <span class="badge">{{ $entry['tipo_label'] }}</span>
            <span class="badge">{{ $entry['estado_label'] }}</span>
        </div>
        <p class="entry-title">{{ $entry['titulo'] }}</p>
        <p class="entry-meta">
            {{ $entry['fecha'] }}
            @if (! empty($entry['veterinario']))
                · {{ $entry['veterinario'] }}
            @endif
        </p>
    </div>

    @if (! empty($entry['signos']))
        <p class="section-title">{{ __('historial_clinico.section_vitals') }}</p>
        <table class="grid">
            @foreach ($entry['signos'] as $signo)
                <tr>
                    <td class="k">{{ $signo['label'] }}</td>
                    <td>{{ $signo['value'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (! empty($entry['fields']))
        <table class="grid">
            @foreach ($entry['fields'] as $field)
                <tr>
                    <td class="k">{{ $field['label'] }}</td>
                    <td style="white-space: pre-wrap;">{{ $field['value'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @foreach ($entry['soap'] as $block)
        <div class="soap-block">
            <p class="soap-label">{{ $block['label'] }}</p>
            <p class="soap-text">{{ $block['text'] }}</p>
        </div>
    @endforeach

    @if (! empty($entry['vinculos']))
        <p class="section-title">{{ __('historial_clinico.section_links') }}</p>
        <ul class="vinculos">
            @foreach ($entry['vinculos'] as $vinculo)
                <li>{{ $vinculo }}</li>
            @endforeach
        </ul>
    @endif
</div>
