import { useTranslation } from 'react-i18next';
import { ProductoCatalogoCombobox } from '@/components/inventario/producto-catalogo-combobox';
import type { ProductoOptionCompra } from '../types';

type ProductoCompraComboboxProps = {
    id?: string;
    value: string | null;
    onChange: (productoId: string | null) => void;
    productoOptions: readonly ProductoOptionCompra[];
    canCreateProducto: boolean;
    onRequestCreate?: (query: string) => void;
    disabled?: boolean;
    'aria-invalid'?: boolean;
};

export function ProductoCompraCombobox({
    id,
    value,
    onChange,
    productoOptions,
    canCreateProducto,
    onRequestCreate,
    disabled = false,
    'aria-invalid': ariaInvalid,
}: ProductoCompraComboboxProps) {
    const { t } = useTranslation(['compras-inventario']);

    const handleCreateOption = (query: string) => {
        onRequestCreate?.(query.trim());
    };

    return (
        <ProductoCatalogoCombobox
            id={id}
            value={value}
            onChange={(productoId) => onChange(productoId)}
            seeds={productoOptions}
            placeholder={t('modal.linea_producto_placeholder')}
            searchPlaceholder={t('modal.linea_producto_search')}
            emptyMessage={
                canCreateProducto ? t('modal.linea_producto_empty_create') : t('modal.linea_producto_empty')
            }
            disabled={disabled}
            onCreateOption={canCreateProducto ? handleCreateOption : undefined}
            createOptionLabel={(q) => t('modal.linea_producto_create', { nombre: q })}
            aria-invalid={ariaInvalid}
        />
    );
}
