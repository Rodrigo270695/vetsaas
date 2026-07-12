import { useTranslation } from 'react-i18next';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Producto, ProductoLoteFila } from '../types';

type ProductoLotesDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    producto: Producto | null;
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

function formatCantidad(value: string | number, locale: string): string {
    const n = typeof value === 'string' ? Number(value) : value;
    if (Number.isNaN(n)) {
        return String(value);
    }
    return n.toLocaleString(locale, { minimumFractionDigits: 0, maximumFractionDigits: 3 });
}

function sedeLabel(lote: ProductoLoteFila): string {
    if (lote.sede_nombre) {
        return lote.sede_codigo ? `${lote.sede_nombre} · ${lote.sede_codigo}` : lote.sede_nombre;
    }
    return '—';
}

export function ProductoLotesDialog({ open, onOpenChange, producto }: ProductoLotesDialogProps) {
    const { t, i18n } = useTranslation(['productos-inventario']);
    const lotes = producto?.lotes ?? [];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{t('lotes_dialog.title')}</DialogTitle>
                    <DialogDescription>
                        {t('lotes_dialog.subtitle', { producto: producto?.nombre ?? '' })}
                    </DialogDescription>
                </DialogHeader>
                {lotes.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t('lotes_dialog.vacio')}</p>
                ) : (
                    <div className="overflow-x-auto rounded-md border border-border">
                        <div className="grid min-w-lg grid-cols-[1fr_7rem_6rem_5rem] gap-2 border-b border-border bg-muted/40 px-3 py-2 text-xs font-medium text-muted-foreground">
                            <span>{t('lotes_dialog.col_lote')}</span>
                            <span>{t('lotes_dialog.col_sede')}</span>
                            <span>{t('lotes_dialog.col_vencimiento')}</span>
                            <span className="text-right">{t('lotes_dialog.col_cantidad')}</span>
                        </div>
                        <ul className="min-w-lg divide-y divide-border">
                            {lotes.map((lote, index) => (
                                <li
                                    key={lote.id}
                                    className="grid grid-cols-[1fr_7rem_6rem_5rem] gap-2 px-3 py-2.5 text-sm"
                                >
                                    <div className="min-w-0">
                                        <div className="font-mono text-xs text-foreground">
                                            {lote.numero_lote?.trim()
                                                ? lote.numero_lote
                                                : t('lotes_dialog.sin_numero')}
                                        </div>
                                        {index === 0 ? (
                                            <div className="mt-0.5 text-[0.65rem] text-muted-foreground">
                                                {t('lotes_dialog.fefo_primero')}
                                            </div>
                                        ) : null}
                                    </div>
                                    <div className="min-w-0 text-xs text-muted-foreground">{sedeLabel(lote)}</div>
                                    <div className="tabular-nums text-xs text-muted-foreground">
                                        {formatFecha(lote.fecha_vencimiento, i18n.language)}
                                    </div>
                                    <div className="text-right tabular-nums font-medium text-foreground">
                                        {formatCantidad(lote.cantidad, i18n.language)}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
