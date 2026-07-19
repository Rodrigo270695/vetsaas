import {
    CategoriaTarifaCombobox,
    type CategoriaTarifaOption,
} from '@/components/tarifas/categoria-tarifa-combobox';

/** @deprecated Prefer CategoriaTarifaCombobox with createUrl. */
export type CategoriaServicioClinicoOption = CategoriaTarifaOption;

type Props = {
    id?: string;
    value: string | null;
    onChange: (categoriaId: string | null) => void;
    options: readonly CategoriaTarifaOption[];
    onOptionsChange?: (options: readonly CategoriaTarifaOption[]) => void;
    canCreate?: boolean;
    disabled?: boolean;
    loading?: boolean;
    'aria-invalid'?: boolean;
};

export function CategoriaServicioClinicoCombobox(props: Props) {
    return (
        <CategoriaTarifaCombobox
            {...props}
            createUrl="/configuracion/tarifas/clinica/categorias"
            optionsPropKey="categoriaOptions"
        />
    );
}
