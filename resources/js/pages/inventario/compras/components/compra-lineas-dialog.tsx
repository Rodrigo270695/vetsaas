import { useTranslation } from 'react-i18next';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { CompraFila } from '../types';

type CompraLineasDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    compra: CompraFila | null;
};

export function CompraLineasDialog({ open, onOpenChange, compra }: CompraLineasDialogProps) {
    const { t, i18n } = useTranslation(['compras-inventario']);
    const lineas = compra?.lineas ?? [];
    const doc = [compra?.serie, compra?.numero_documento].filter(Boolean).join('-');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('lineas_dialog.title')}</DialogTitle>
                    <DialogDescription>
                        {doc ? t('lineas_dialog.subtitle_doc', { doc }) : t('lineas_dialog.subtitle_sin_doc')}
                    </DialogDescription>
                </DialogHeader>
                {lineas.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t('lineas_dialog.vacio')}</p>
                ) : (
                    <div className="rounded-md border border-border">
                        <div className="grid grid-cols-[1fr_5.5rem_7rem] gap-2 border-b border-border bg-muted/40 px-3 py-2 text-xs font-medium text-muted-foreground">
                            <span>{t('lineas_dialog.col_producto')}</span>
                            <span className="text-right">{t('lineas_dialog.col_cantidad')}</span>
                            <span className="text-right">{t('lineas_dialog.col_costo')}</span>
                        </div>
                        <ul className="divide-y divide-border">
                            {lineas.map((ln) => {
                                const qty = Number(ln.cantidad);
                                const cost = ln.costo_unitario != null ? Number(ln.costo_unitario) : null;
                                const sub = cost !== null && !Number.isNaN(cost) && !Number.isNaN(qty) ? qty * cost : null;

                                return (
                                    <li key={ln.id} className="grid grid-cols-[1fr_5.5rem_7rem] gap-2 px-3 py-2.5 text-sm">
                                        <div className="min-w-0">
                                            <div className="font-medium text-foreground">{ln.producto?.nombre ?? '—'}</div>
                                            {ln.producto?.sku ? (
                                                <div className="font-mono text-xs text-muted-foreground">{ln.producto.sku}</div>
                                            ) : null}
                                        </div>
                                        <div className="text-right tabular-nums text-muted-foreground">
                                            {qty.toLocaleString(i18n.language, { maximumFractionDigits: 3 })}
                                        </div>
                                        <div className="text-right">
                                            {cost !== null && !Number.isNaN(cost) ? (
                                                <div className="flex flex-col items-end gap-0.5">
                                                    <span className="tabular-nums text-foreground">
                                                        {compra?.moneda}{' '}
                                                        {cost.toLocaleString(i18n.language, {
                                                            minimumFractionDigits: 2,
                                                            maximumFractionDigits: 4,
                                                        })}
                                                    </span>
                                                    {sub !== null && !Number.isNaN(sub) ? (
                                                        <span className="text-[0.65rem] text-muted-foreground">
                                                            {t('lineas_dialog.subtotal')}: {compra?.moneda}{' '}
                                                            {sub.toLocaleString(i18n.language, {
                                                                minimumFractionDigits: 2,
                                                                maximumFractionDigits: 2,
                                                            })}
                                                        </span>
                                                    ) : null}
                                                </div>
                                            ) : (
                                                <span className="text-muted-foreground">—</span>
                                            )}
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
