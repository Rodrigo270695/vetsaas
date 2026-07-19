import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Combobox, type ComboboxOption } from '@/components/ui/combobox';

export type CategoriaServicioClinicoOption = {
    id: string;
    nombre: string;
};

type Props = {
    id?: string;
    value: string | null;
    onChange: (categoriaId: string | null) => void;
    options: readonly CategoriaServicioClinicoOption[];
    onOptionsChange?: (options: readonly CategoriaServicioClinicoOption[]) => void;
    canCreate?: boolean;
    disabled?: boolean;
    loading?: boolean;
    'aria-invalid'?: boolean;
};

export function CategoriaServicioClinicoCombobox({
    id,
    value,
    onChange,
    options,
    onOptionsChange,
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
            '/configuracion/tarifas/clinica/categorias',
            { nombre },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['categoriaOptions'],
                onSuccess: (page) => {
                    const next =
                        (page.props as { categoriaOptions?: CategoriaServicioClinicoOption[] }).categoriaOptions ??
                        options;

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
