import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Combobox, type ComboboxOption } from '@/components/ui/combobox';
import type { ProductoOptionCompra, ProductoUnidadOptionCompra } from '../types';
import { ProductoQuickCreateDialog } from './producto-quick-create-dialog';

type ProductoCompraComboboxProps = {
    id?: string;
    value: string | null;
    onChange: (productoId: string | null) => void;
    productoOptions: readonly ProductoOptionCompra[];
    onProductoCreated: (producto: ProductoOptionCompra) => void;
    unidadOptions: readonly ProductoUnidadOptionCompra[];
    canCreateProducto: boolean;
    costoUnitarioHint?: string;
    disabled?: boolean;
    'aria-invalid'?: boolean;
};

export function ProductoCompraCombobox({
    id,
    value,
    onChange,
    productoOptions,
    onProductoCreated,
    unidadOptions,
    canCreateProducto,
    costoUnitarioHint,
    disabled = false,
    'aria-invalid': ariaInvalid,
}: ProductoCompraComboboxProps) {
    const { t } = useTranslation(['compras-inventario']);
    const [createOpen, setCreateOpen] = useState(false);
    const [createNombre, setCreateNombre] = useState('');

    const options = useMemo<readonly ComboboxOption[]>(
        () =>
            productoOptions.map((p) => ({
                value: p.id,
                label: p.sku ? `${p.nombre} (${p.sku})` : p.nombre,
            })),
        [productoOptions],
    );

    const handleCreateOption = (query: string) => {
        setCreateNombre(query.trim());
        setCreateOpen(true);
    };

    const handleCreated = (producto: ProductoOptionCompra) => {
        onProductoCreated(producto);
        onChange(producto.id);
    };

    return (
        <>
            <Combobox
                id={id}
                options={options}
                value={value}
                onChange={onChange}
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

            {canCreateProducto ? (
                <ProductoQuickCreateDialog
                    open={createOpen}
                    onOpenChange={setCreateOpen}
                    initialNombre={createNombre}
                    initialPrecioCompra={costoUnitarioHint}
                    unidadOptions={unidadOptions}
                    onCreated={handleCreated}
                />
            ) : null}
        </>
    );
}
