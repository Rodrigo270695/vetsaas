import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Combobox, type ComboboxOption } from '@/components/ui/combobox';

export type UnidadMedidaOption = {
    id: string;
    codigo: string;
    nombre: string;
    es_sistema: boolean;
};

type UnidadMedidaComboboxProps = {
    id?: string;
    value: string;
    onChange: (codigo: string) => void;
    unidadOptions: readonly UnidadMedidaOption[];
    onUnidadOptionsChange?: (options: readonly UnidadMedidaOption[]) => void;
    canCreate?: boolean;
    disabled?: boolean;
    loading?: boolean;
    'aria-invalid'?: boolean;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyMessage?: string;
    /** Namespace i18n con claves `form.unidad_*` (default: productos-inventario). */
    translationNs?: string;
};

export function UnidadMedidaCombobox({
    id,
    value,
    onChange,
    unidadOptions,
    onUnidadOptionsChange,
    canCreate = false,
    disabled = false,
    loading = false,
    'aria-invalid': ariaInvalid,
    placeholder,
    searchPlaceholder,
    emptyMessage,
    translationNs = 'productos-inventario',
}: UnidadMedidaComboboxProps) {
    const { t } = useTranslation([translationNs]);
    const [creating, setCreating] = useState(false);

    const codigosConocidos = useMemo(() => new Set(unidadOptions.map((u) => u.codigo)), [unidadOptions]);

    const legacyOption = useMemo((): ComboboxOption | null => {
        const codigo = value.trim().toUpperCase();

        if (codigo === '' || codigosConocidos.has(codigo)) {
            return null;
        }

        return {
            value: codigo,
            label: t('form.unidad_legacy_label', { codigo }),
        };
    }, [value, codigosConocidos, t]);

    const comboboxOptions = useMemo<readonly ComboboxOption[]>(() => {
        const mapped = unidadOptions.map((u) => ({
            value: u.codigo,
            label: `${u.nombre} (${u.codigo})`,
        }));

        if (legacyOption) {
            return [legacyOption, ...mapped];
        }

        return mapped;
    }, [unidadOptions, legacyOption]);

    const handleCreateOption = (query: string) => {
        const nombre = query.trim();

        if (!canCreate || nombre === '' || creating) {
            return;
        }

        const antes = new Set(unidadOptions.map((u) => u.codigo));
        setCreating(true);

        router.post(
            '/inventario/unidades-medida',
            { nombre },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['unidadOptions'],
                onSuccess: (page) => {
                    const next =
                        (page.props as { unidadOptions?: UnidadMedidaOption[] }).unidadOptions ?? unidadOptions;

                    onUnidadOptionsChange?.(next);

                    const nueva = next.find((u) => !antes.has(u.codigo));

                    if (nueva) {
                        onChange(nueva.codigo);
                    }
                },
                onFinish: () => setCreating(false),
            },
        );
    };

    return (
        <Combobox
            id={id}
            options={comboboxOptions}
            value={value}
            onChange={(v) => onChange(v ?? 'UN')}
            placeholder={placeholder ?? t('form.unidad_placeholder')}
            searchPlaceholder={searchPlaceholder ?? t('form.unidad_search')}
            emptyMessage={emptyMessage ?? t('form.unidad_empty')}
            disabled={disabled}
            loading={loading || creating}
            clearable={false}
            onCreateOption={canCreate ? handleCreateOption : undefined}
            createOptionLabel={(q) => t('form.unidad_create', { nombre: q })}
            aria-invalid={ariaInvalid}
        />
    );
}
