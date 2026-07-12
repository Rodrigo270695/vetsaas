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

function formatFecha(value: string | null | undefined, locale: string): string {
    if (!value) {
        return '—';
    }
    const d = new Date(`${value.slice(0, 10)}T12:00:00`);
    if (Number.isNaN(d.getTime())) {
        return value;
    }
    return d.toLocaleDateString(locale, { year: 'numeric', month: 'short', day: 'numeric' });
}

export function CompraLineasDialog({ open, onOpenChange, compra }: CompraLineasDialogProps) {
    const { t, i18n } = useTranslation(['compras-inventario']);
    const lineas = compra?.lineas ?? [];
    const doc = [compra?.serie, compra?.numero_documento].filter(Boolean).join('-');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{t('lineas_dialog.title')}</DialogTitle>
                    <DialogDescription>
                        {doc ? t('lineas_dialog.subtitle_doc', { doc }) : t('lineas_dialog.subtitle_sin_doc')}
                    </DialogDescription>
                </DialogHeader>
                {lineas.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t('lineas_dialog.vacio')}</p>
                ) : (
                    <div className="overflow-x-auto rounded-md border border-border">
                        <div className="grid min-w-[36rem] grid-cols-[minmax(8rem,1.4fr)_4.5rem_5.5rem_5.5rem_6.5rem] gap-2 border-b border-border bg-muted/40 px-3 py-2 text-xs font-medium text-muted-foreground">
                            <span>{t('lineas_dialog.col_producto')}</span>
                            <span className="text-right">{t('lineas_dialog.col_cantidad')}</span>
                            <span>{t('lineas_dialog.col_lote')}</span>
                            <span>{t('lineas_dialog.col_vencimiento')}</span>
                            <span className="text-right">{t('lineas_dialog.col_costo')}</span>
                        </div>
                        <ul className="min-w-[36rem] divide-y divide-border">
                            {lineas.map((ln) => {
                                const qty = Number(ln.cantidad);
                                const cost = ln.costo_unitario != null ? Number(ln.costo_unitario) : null;
                                const sub = cost !== null && !Number.isNaN(cost) && !Number.isNaN(qty) ? qty * cost : null;

                                return (
                                    <li
                                        key={ln.id}
                                        className="grid grid-cols-[minmax(8rem,1.4fr)_4.5rem_5.5rem_5.5rem_6.5rem] gap-2 px-3 py-2.5 text-sm"
                                    >
                                        <div className="min-w-0">
                                            <div className="font-medium text-foreground">{ln.producto?.nombre ?? '—'}</div>
                                            {ln.producto?.sku ? (
                                                <div className="font-mono text-xs text-muted-foreground">{ln.producto.sku}</div>
                                            ) : null}
                                        </div>
                                        <div className="text-right tabular-nums text-muted-foreground">
                                            {qty.toLocaleString(i18n.language, { maximumFractionDigits: 3 })}
                                        </div>
                                        <div className="min-w-0 font-mono text-xs text-foreground">
                                            {ln.numero_lote?.trim() && ln.numero_lote !== 'SIN-LOTE'
                                                ? ln.numero_lote
                                                : '—'}
                                        </div>
                                        <div className="tabular-nums text-xs text-muted-foreground">
                                            {formatFecha(ln.fecha_vencimiento, i18n.language)}
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
