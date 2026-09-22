import { useEffect, useMemo, useRef, useState } from 'react';
import { Combobox } from '@/components/ui/combobox';
import type { ComboboxOption } from '@/components/ui/combobox';

export type PacienteComboboxSeed = {
    id: string;
    nombre: string;
    especie?: string | null;
    raza?: string | null;
    microchip?: string | null;
    propietario?: {
        nombres?: string | null;
        apellidos?: string | null;
        razon_social?: string | null;
        telefono?: string | null;
    } | null;
};

type RemoteHit = {
    id: string;
    label: string;
    keywords?: string;
};

function ownerLabel(seed: PacienteComboboxSeed): string {
    const p = seed.propietario;
    if (!p) {
        return '';
    }

    const razon = p.razon_social?.trim() ?? '';
    if (razon !== '') {
        return razon;
    }

    return [p.nombres, p.apellidos].filter(Boolean).join(' ').trim();
}

export function pacienteComboboxLabel(seed: PacienteComboboxSeed): string {
    const owner = ownerLabel(seed);

    return owner !== '' ? `${seed.nombre} · ${owner}` : seed.nombre;
}

function seedToOption(seed: PacienteComboboxSeed): ComboboxOption {
    const keywords = [seed.especie, seed.raza, seed.microchip, seed.propietario?.telefono]
        .filter((part) => part != null && String(part).trim() !== '')
        .join(' ');

    return {
        value: seed.id,
        label: pacienteComboboxLabel(seed),
        keywords: keywords !== '' ? keywords : undefined,
    };
}

export type PacienteComboboxProps = {
    pacientes: readonly PacienteComboboxSeed[];
    value: string | null;
    onChange: (value: string | null) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyMessage?: string;
    disabled?: boolean;
    clearable?: boolean;
    id?: string;
    className?: string;
    'aria-invalid'?: boolean;
};

/**
 * Combo de paciente: la lista inicial es la que trae la página (recientes).
 * Al escribir 2+ letras consulta todas las mascotas activas, no solo esas.
 */
export function PacienteCombobox({
    pacientes,
    value,
    onChange,
    placeholder,
    searchPlaceholder,
    emptyMessage,
    disabled = false,
    clearable = true,
    id,
    className,
    'aria-invalid': ariaInvalid,
}: PacienteComboboxProps) {
    const [search, setSearch] = useState('');
    const [remote, setRemote] = useState<ComboboxOption[] | null>(null);
    const [loading, setLoading] = useState(false);
    const known = useRef(new Map<string, ComboboxOption>());

    const seeds = useMemo(
        () => pacientes.map(seedToOption),
        [pacientes],
    );

    useEffect(() => {
        for (const option of seeds) {
            known.current.set(option.value, option);
        }
    }, [seeds]);

    useEffect(() => {
        const q = search.trim();
        if (q.length < 2) {
            setRemote(null);
            setLoading(false);

            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setLoading(true);
            void fetch(`/clinica/pacientes/opciones?q=${encodeURIComponent(q)}`, {
                credentials: 'same-origin',
                signal: controller.signal,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(async (res) => {
                    if (!res.ok) {
                        throw new Error(`HTTP ${res.status}`);
                    }

                    const body = (await res.json()) as { data?: RemoteHit[] };
                    const options = (body.data ?? []).map((hit) => ({
                        value: hit.id,
                        label: hit.label,
                        keywords: hit.keywords,
                    }));

                    for (const option of options) {
                        known.current.set(option.value, option);
                    }

                    setRemote(options);
                })
                .catch((error: unknown) => {
                    if (error instanceof DOMException && error.name === 'AbortError') {
                        return;
                    }

                    setRemote(null);
                })
                .finally(() => {
                    if (!controller.signal.aborted) {
                        setLoading(false);
                    }
                });
        }, 250);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [search]);

    const options = useMemo(() => {
        const base = remote ?? seeds;
        if (value == null || value === '' || base.some((option) => option.value === value)) {
            return base;
        }

        const selected = known.current.get(value);

        return selected ? [selected, ...base] : base;
    }, [remote, seeds, value]);

    return (
        <Combobox
            id={id}
            options={options}
            value={value}
            onChange={onChange}
            placeholder={placeholder}
            searchPlaceholder={searchPlaceholder}
            emptyMessage={emptyMessage}
            disabled={disabled}
            loading={loading}
            clearable={clearable}
            className={className}
            onSearchChange={setSearch}
            aria-invalid={ariaInvalid}
        />
    );
}
