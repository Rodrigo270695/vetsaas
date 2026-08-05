import { router } from '@inertiajs/react';
import { ClipboardList, Loader2 } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type PrecuentaPendiente = {
    id: string;
    origen: 'consulta' | 'grooming' | 'hotel' | 'internamiento';
    origen_id: string;
    origen_label: string;
    propietario_id: string | null;
    propietario_nombre: string | null;
    paciente_nombre: string | null;
    total: string;
    moneda: string;
    confirmado_at: string | null;
    url_cobrar: string;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    listUrl: string;
    disabled?: boolean;
};

function readXsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function fetchPrecuentas(url: string): Promise<PrecuentaPendiente[]> {
    const res = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': readXsrfToken(),
        },
    });

    if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
    }

    const body = (await res.json()) as { data?: PrecuentaPendiente[] };

    return Array.isArray(body.data) ? body.data : [];
}

function formatWhen(iso: string | null, locale: string): string {
    if (!iso) {
        return '—';
    }
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) {
        return '—';
    }

    return d.toLocaleString(locale, {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function PrecuentasPendientesModal({ open, onOpenChange, listUrl, disabled = false }: Props) {
    const { t, i18n } = useTranslation(['caja', 'common']);
    const [rows, setRows] = useState<PrecuentaPendiente[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [navigatingId, setNavigatingId] = useState<string | null>(null);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const data = await fetchPrecuentas(listUrl);
            setRows(data);
        } catch {
            setRows([]);
            setError(t('caja:ventas.create.precuentas_error'));
        } finally {
            setLoading(false);
        }
    }, [listUrl, t]);

    useEffect(() => {
        if (!open) {
            return;
        }
        void load();
    }, [open, load]);

    const cobrar = (row: PrecuentaPendiente) => {
        if (disabled || navigatingId) {
            return;
        }
        setNavigatingId(row.id);
        router.visit(row.url_cobrar, {
            onFinish: () => setNavigatingId(null),
            onError: () => setNavigatingId(null),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[85vh] flex-col gap-0 p-0 sm:max-w-lg">
                <DialogHeader className="shrink-0 border-b border-border/50 px-4 py-3 pr-12">
                    <DialogTitle className="flex items-center gap-2 text-base">
                        <ClipboardList className="size-4 text-primary" aria-hidden />
                        {t('caja:ventas.create.precuentas_title')}
                    </DialogTitle>
                    <DialogDescription className="text-xs">
                        {t('caja:ventas.create.precuentas_desc')}
                    </DialogDescription>
                </DialogHeader>

                <div className="min-h-0 flex-1 px-2 py-2">
                    {loading ? (
                        <div className="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground">
                            <Loader2 className="size-4 animate-spin" aria-hidden />
                            {t('caja:ventas.create.precuentas_cargando')}
                        </div>
                    ) : error ? (
                        <div className="flex flex-col items-center gap-3 px-4 py-8 text-center">
                            <p className="text-sm text-destructive">{error}</p>
                            <Button type="button" variant="secondary" size="sm" onClick={() => void load()}>
                                {t('caja:ventas.create.precuentas_reintentar')}
                            </Button>
                        </div>
                    ) : rows.length === 0 ? (
                        <p className="px-4 py-10 text-center text-sm text-muted-foreground">
                            {t('caja:ventas.create.precuentas_vacio')}
                        </p>
                    ) : (
                        <div className="max-h-[min(50vh,22rem)] overflow-y-auto px-2">
                            <ul className="flex flex-col gap-1.5 pb-2">
                                {rows.map((row) => {
                                    const busy = navigatingId === row.id;

                                    return (
                                        <li key={row.id}>
                                            <button
                                                type="button"
                                                disabled={disabled || navigatingId !== null}
                                                onClick={() => cobrar(row)}
                                                onDoubleClick={() => cobrar(row)}
                                                className="flex w-full items-start gap-3 rounded-lg border border-border/60 bg-muted/20 px-3 py-2.5 text-left transition-colors hover:bg-muted/50 disabled:opacity-60"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex flex-wrap items-center gap-1.5">
                                                        <Badge variant="secondary" className="text-[10px]">
                                                            {row.origen_label}
                                                        </Badge>
                                                        <span className="text-[10px] text-muted-foreground">
                                                            {formatWhen(row.confirmado_at, i18n.language)}
                                                        </span>
                                                    </div>
                                                    <p className="mt-1 truncate text-sm font-medium">
                                                        {row.paciente_nombre ?? '—'}
                                                    </p>
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {row.propietario_nombre ?? '—'}
                                                    </p>
                                                </div>
                                                <div className="shrink-0 text-right">
                                                    <p className="text-sm font-semibold tabular-nums">
                                                        {row.moneda} {Number(row.total).toFixed(2)}
                                                    </p>
                                                    <p className="text-[10px] text-muted-foreground">
                                                        {busy
                                                            ? t('caja:ventas.create.precuentas_abriendo')
                                                            : t('caja:ventas.create.precuentas_cobrar')}
                                                    </p>
                                                </div>
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
