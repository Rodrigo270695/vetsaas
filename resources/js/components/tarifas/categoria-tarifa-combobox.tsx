import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Combobox, type ComboboxOption } from '@/components/ui/combobox';

export type CategoriaTarifaOption = {
    id: string;
    nombre: string;
};

type Props = {
    id?: string;
    value: string | null;
    onChange: (categoriaId: string | null) => void;
    options: readonly CategoriaTarifaOption[];
    onOptionsChange?: (options: readonly CategoriaTarifaOption[]) => void;
    /** POST endpoint that creates a category and refreshes Inertia props. */
    createUrl: string;
    /** Prop key returned by the create endpoint (only: [propKey]). */
    optionsPropKey: string;
    canCreate?: boolean;
    disabled?: boolean;
    loading?: boolean;
    'aria-invalid'?: boolean;
};

export function CategoriaTarifaCombobox({
    id,
    value,
    onChange,
    options,
    onOptionsChange,
    createUrl,
    optionsPropKey,
    canCreate = false,
    disabled = false,
    loading = false,
    'aria-invalid': ariaInvalid,
}: Props) {
    const { t } = useTranslation(['tarifas-servicios']);
    const [creating, setCreating] = useState(false);

    const comboboxOptions = useMemo<readonly ComboboxOption[]>(
        () =>
            options.map((c) => ({
                value: c.id,
                label: c.nombre,
            })),
        [options],
    );

    const handleCreateOption = (query: string) => {
        const nombre = query.trim();

        if (!canCreate || nombre === '' || creating) {
            return;
        }

        const antes = new Set(options.map((c) => c.id));
        setCreating(true);

        router.post(
            createUrl,
            { nombre },
            {
                preserveScroll: true,
                preserveState: true,
                only: [optionsPropKey],
                onSuccess: (page) => {
                    const props = page.props as Record<string, unknown>;
                    const next = (props[optionsPropKey] as CategoriaTarifaOption[] | undefined) ?? options;

                    onOptionsChange?.(next);

                    const nueva = next.find((c) => !antes.has(c.id));

                    if (nueva) {
                        onChange(nueva.id);
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
            onChange={(v) => onChange(v)}
            placeholder={t('catalogo.form.categoria_select_placeholder')}
            searchPlaceholder={t('catalogo.form.categoria_search')}
            emptyMessage={t('catalogo.form.categoria_empty')}
            disabled={disabled}
            loading={loading || creating}
            clearable
            onCreateOption={canCreate ? handleCreateOption : undefined}
            createOptionLabel={(q) => t('catalogo.form.categoria_create', { nombre: q })}
            aria-invalid={ariaInvalid}
        />
    );
}
