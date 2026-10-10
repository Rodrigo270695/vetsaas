import { useEffect, useMemo, useRef, useState } from 'react';
import { Combobox, type ComboboxOption } from '@/components/ui/combobox';

export type ProductoCatalogoHit = {
    id: string;
    nombre: string;
    sku: string | null;
};

type Props = {
    value: string | null;
    onChange: (id: string | null, hit: ProductoCatalogoHit | null) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyMessage?: string;
    disabled?: boolean;
    clearable?: boolean;
    id?: string;
    className?: string;
    soloActivos?: boolean;
    soloMedicamentos?: boolean;
    /** Productos recién creados o el ya elegido, para que el nombre no desaparezca. */
    seeds?: readonly ProductoCatalogoHit[];
    excludeIds?: readonly string[];
    'aria-invalid'?: boolean;
    onCreateOption?: (query: string) => void;
    createOptionLabel?: (query: string) => string;
};

function labelOf(hit: ProductoCatalogoHit): string {
    return hit.sku ? `${hit.nombre} (${hit.sku})` : hit.nombre;
}

function toOption(hit: ProductoCatalogoHit): ComboboxOption {
    return {
        value: hit.id,
        label: labelOf(hit),
        keywords: hit.sku ?? undefined,
    };
}

export function ProductoCatalogoCombobox({
    value,
    onChange,
    placeholder,
    searchPlaceholder,
    emptyMessage = 'Ningún producto coincide.',
    disabled = false,
    clearable = true,
    id,
    className,
    soloActivos = false,
    soloMedicamentos = false,
    seeds = [],
    excludeIds = [],
    'aria-invalid': ariaInvalid,
    onCreateOption,
    createOptionLabel,
}: Props) {
    const [search, setSearch] = useState('');
    const [remote, setRemote] = useState<ProductoCatalogoHit[] | null>(null);
    const [loading, setLoading] = useState(false);
    const known = useRef(new Map<string, ProductoCatalogoHit>());

    useEffect(() => {
        for (const hit of seeds) {
            known.current.set(hit.id, hit);
        }
    }, [seeds]);

    useEffect(() => {
        const q = search.trim();
        if (q.length < 1) {
            setRemote(null);
            setLoading(false);

            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setLoading(true);
            const params = new URLSearchParams({ q });
            if (soloActivos) {
                params.set('solo_activos', '1');
            }
            if (soloMedicamentos) {
                params.set('solo_medicamentos', '1');
            }

            void fetch(`/inventario/productos/opciones?${params.toString()}`, {
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

                    const body = (await res.json()) as { data?: ProductoCatalogoHit[] };
                    const hits = body.data ?? [];
                    for (const hit of hits) {
                        known.current.set(hit.id, hit);
                    }
                    setRemote(hits);
                })
                .catch((error: unknown) => {
                    if (error instanceof DOMException && error.name === 'AbortError') {
                        return;
                    }
                    setRemote([]);
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
    }, [search, soloActivos, soloMedicamentos]);

    const excluded = useMemo(() => new Set(excludeIds), [excludeIds]);

    const options = useMemo(() => {
        const source = remote ?? seeds;
        const base = source.filter((hit) => !excluded.has(hit.id) || hit.id === value).map(toOption);

        if (value == null || value === '' || base.some((option) => option.value === value)) {
            return base;
        }

        const selected = known.current.get(value);

        return selected ? [toOption(selected), ...base] : base;
    }, [remote, seeds, excluded, value]);

    return (
        <Combobox
            id={id}
            options={options}
            value={value}
            onChange={(next) => {
                if (next == null || next === '') {
                    onChange(null, null);

                    return;
                }

                onChange(next, known.current.get(next) ?? null);
            }}
            placeholder={placeholder}
            searchPlaceholder={searchPlaceholder}
            emptyMessage={search.trim().length < 1 ? (searchPlaceholder ?? emptyMessage) : emptyMessage}
            disabled={disabled}
            loading={loading}
            clearable={clearable}
            className={className}
            onSearchChange={setSearch}
            onCreateOption={onCreateOption}
            createOptionLabel={createOptionLabel}
            aria-invalid={ariaInvalid}
        />
    );
}
