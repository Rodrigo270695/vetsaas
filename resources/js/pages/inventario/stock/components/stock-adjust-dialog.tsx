import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import inventario from '@/routes/inventario';
import type { StockProductoFila } from '../types';

type StockAdjustDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    producto: StockProductoFila | null;
    sedeId: string;
};

type FormData = {
    producto_id: string;
    sede_id: string;
    cantidad: string;
};

const empty: FormData = {
    producto_id: '',
    sede_id: '',
    cantidad: '',
};

function cantidadToInputValue(value: string | number | null | undefined): string {
    if (value === null || value === undefined) {
        return '';
    }
    const s = String(value).trim();
    if (s === '') {
        return '';
    }
    const n = Number(s);
    if (Number.isNaN(n)) {
        return s;
    }
    return String(n);
}

export function StockAdjustDialog({ open, onOpenChange, producto, sedeId }: StockAdjustDialogProps) {
    const { t } = useTranslation(['stock-inventario', 'common']);
    const { data, setData, patch, processing, errors, reset, clearErrors } = useForm<FormData>(empty);

    useEffect(() => {
        if (!open || !producto) {
            return;
        }
        setData({
            producto_id: producto.id,
            sede_id: sedeId,
            cantidad: cantidadToInputValue(producto.cantidad_stock),
        });
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, producto?.id, sedeId]);

    const onSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (!producto || sedeId === '') {
            return;
        }

        patch(inventario.stock.adjust.url(), {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                reset();
                clearErrors();
            },
        });
    };

    const puedeEnviar = Boolean(producto) && sedeId !== '' && !processing;

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={t('adjust.title')}
            description={t('adjust.description')}
            size="sm"
            onSubmit={onSubmit}
            footer={
                <>
                    <Button type="button" variant="outline" disabled={processing} onClick={() => onOpenChange(false)}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" disabled={!puedeEnviar} className="gap-2">
                        {processing && <Loader2 className="size-4 animate-spin" />}
                        {t('adjust.submit')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4">
                {producto ? <p className="text-sm font-medium text-foreground">{producto.nombre}</p> : null}

                <FormField id="stock-cantidad" label={t('adjust.cantidad_label')} error={errors.cantidad} hint={t('adjust.cantidad_hint')} className="min-w-0">
                    <Input
                        id="stock-cantidad"
                        type="text"
                        inputMode="decimal"
                        autoComplete="off"
                        value={data.cantidad}
                        onChange={(e) => setData('cantidad', e.target.value)}
                        disabled={processing || sedeId === ''}
                        className="h-10 w-full"
                        aria-invalid={Boolean(errors.cantidad)}
                    />
                </FormField>
            </div>
        </FormModal>
    );
}
