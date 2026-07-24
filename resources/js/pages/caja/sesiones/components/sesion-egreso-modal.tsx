import { router } from '@inertiajs/react';
import { Loader2, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { toastManager } from '@/lib/toast';
import caja from '@/routes/caja';
import type { QueryParams } from '@/wayfinder';
import { arqueoCsrfToken, formatArqueoMoney } from './arqueo-types';
import type { CajaSesionRow } from '../types';

const MOTIVOS = ['insumos', 'delivery', 'servicios', 'personal', 'cambio', 'otros'] as const;

type EgresoRow = {
    id: string;
    monto: string;
    motivo: string;
    motivo_label: string;
    notas: string | null;
    created_at: string | null;
    created_by: string | null;
};

type SesionEgresoModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    sesion: CajaSesionRow | null;
    listQuery: QueryParams;
};

type FormData = {
    monto: string;
    motivo: string;
    notas: string;
};

const empty: FormData = {
    monto: '',
    motivo: 'otros',
    notas: '',
};

function egresosUrl(sesionId: string): string {
    return `/caja/sesiones/${sesionId}/egresos`;
}

function egresoItemUrl(sesionId: string, egresoId: string): string {
    return `/caja/sesiones/${sesionId}/egresos/${egresoId}`;
}

export function SesionEgresoModal({
    open,
    onOpenChange,
    sesion,
    listQuery,
}: SesionEgresoModalProps) {
    const { t, i18n } = useTranslation('caja');
    const [data, setData] = useState<FormData>(empty);
    const [errors, setErrors] = useState<Partial<Record<keyof FormData, string>>>({});
    const [egresos, setEgresos] = useState<EgresoRow[]>([]);
    const [total, setTotal] = useState('0.00');
    const [loadingList, setLoadingList] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [deletingId, setDeletingId] = useState<string | null>(null);

    const locale = i18n.language?.startsWith('en') ? 'en-US' : 'es-PE';
    const moneda = sesion?.moneda || 'PEN';

    const refreshSesionesIndex = useCallback(() => {
        router.get(caja.sesiones.index.url({ query: listQuery }), {}, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['sesiones', 'mi_sesion_abierta', 'stats'],
        });
    }, [listQuery]);

    const loadEgresos = useCallback(
        async (sesionId: string, { silent = false }: { silent?: boolean } = {}) => {
            setLoadingList(true);
            try {
                const token = arqueoCsrfToken();
                const res = await fetch(egresosUrl(sesionId), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    },
                    credentials: 'same-origin',
                });

                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}`);
                }

                const json = (await res.json()) as { egresos: EgresoRow[]; total: string };
                setEgresos(json.egresos ?? []);
                setTotal(json.total ?? '0.00');
            } catch {
                if (!silent) {
                    toastManager.error({ title: t('sesiones.dialog_egreso.load_error') });
                }
                setEgresos([]);
                setTotal('0.00');
            } finally {
                setLoadingList(false);
            }
        },
        [t],
    );

    useEffect(() => {
        if (!open || !sesion) {
            return;
        }
        setData(empty);
        setErrors({});
        void loadEgresos(sesion.id);
    }, [open, sesion, loadEgresos]);

    const onSubmit = async (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (!sesion || processing) {
            return;
        }

        setProcessing(true);
        setErrors({});

        try {
            const token = arqueoCsrfToken();
            const res = await fetch(egresosUrl(sesion.id), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                },
                body: JSON.stringify({
                    monto: data.monto,
                    motivo: data.motivo,
                    notas: data.notas.trim() === '' ? null : data.notas.trim(),
                }),
            });

            const json = (await res.json().catch(() => ({}))) as {
                message?: string;
                errors?: Record<string, string[]>;
                egreso?: EgresoRow;
                total?: string;
                egresos?: EgresoRow[];
            };

            if (res.status === 422 && json.errors) {
                const next: Partial<Record<keyof FormData, string>> = {};
                for (const key of ['monto', 'motivo', 'notas'] as const) {
                    const msg = json.errors[key]?.[0];
                    if (msg) {
                        next[key] = msg;
                    }
                }
                setErrors(next);
                return;
            }

            if (!res.ok) {
                toastManager.error({
                    title: json.message ?? t('sesiones.dialog_egreso.load_error'),
                });
                return;
            }

            setData(empty);
            if (json.egresos) {
                setEgresos(json.egresos);
                setTotal(json.total ?? '0.00');
            } else {
                await loadEgresos(sesion.id, { silent: true });
            }
            toastManager.success({ title: t('sesiones.dialog_egreso.saved') });
            refreshSesionesIndex();
        } catch {
            toastManager.error({ title: t('sesiones.dialog_egreso.load_error') });
        } finally {
            setProcessing(false);
        }
    };

    const onDelete = async (egresoId: string) => {
        if (!sesion || deletingId) {
            return;
        }
        if (!window.confirm(t('sesiones.dialog_egreso.delete_confirm'))) {
            return;
        }

        setDeletingId(egresoId);
        try {
            const token = arqueoCsrfToken();
            const res = await fetch(egresoItemUrl(sesion.id, egresoId), {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                },
            });

            if (!res.ok) {
                const json = (await res.json().catch(() => ({}))) as { message?: string };
                toastManager.error({
                    title: json.message ?? t('sesiones.dialog_egreso.load_error'),
                });
                return;
            }

            await loadEgresos(sesion.id, { silent: true });
            toastManager.success({ title: t('sesiones.dialog_egreso.deleted') });
            refreshSesionesIndex();
        } catch {
            toastManager.error({ title: t('sesiones.dialog_egreso.load_error') });
        } finally {
            setDeletingId(null);
        }
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={t('sesiones.dialog_egreso.title')}
            description={t('sesiones.dialog_egreso.description')}
            onSubmit={onSubmit}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                        className="cursor-pointer"
                    >
                        {t('sesiones.dialog_egreso.cancel')}
                    </Button>
                    <Button type="submit" disabled={processing || !sesion} className="cursor-pointer gap-2">
                        {processing ? <Loader2 className="size-4 animate-spin" /> : null}
                        {t('sesiones.dialog_egreso.submit')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4">
                <FormField label={t('sesiones.fields.egreso_monto')} error={errors.monto} required>
                    <Input
                        type="number"
                        inputMode="decimal"
                        min="0.01"
                        step="0.01"
                        value={data.monto}
                        onChange={(ev) => setData((prev) => ({ ...prev, monto: ev.target.value }))}
                        autoFocus
                    />
                </FormField>

                <FormField label={t('sesiones.fields.egreso_motivo')} error={errors.motivo} required>
                    <Select
                        value={data.motivo}
                        onValueChange={(v) => setData((prev) => ({ ...prev, motivo: v }))}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {MOTIVOS.map((m) => (
                                <SelectItem key={m} value={m}>
                                    {t(`sesiones.dialog_egreso.motivos.${m}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>

                <FormField label={t('sesiones.fields.egreso_notas')} error={errors.notas}>
                    <Textarea
                        value={data.notas}
                        onChange={(ev) => setData((prev) => ({ ...prev, notas: ev.target.value }))}
                        rows={2}
                    />
                </FormField>

                <div className="rounded-xl border border-border/60">
                    <div className="flex items-center justify-between gap-2 border-b border-border/50 bg-muted/40 px-3 py-2">
                        <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            {t('sesiones.dialog_egreso.list_title')}
                        </p>
                        <span className="text-xs font-medium tabular-nums text-rose-700 dark:text-rose-300">
                            {t('sesiones.dialog_egreso.list_total')}:{' '}
                            {formatArqueoMoney(total, moneda, locale)}
                        </span>
                    </div>
                    {loadingList ? (
                        <p className="px-3 py-4 text-sm text-muted-foreground">
                            {t('sesiones.dialog_egreso.loading')}
                        </p>
                    ) : egresos.length === 0 ? (
                        <p className="px-3 py-4 text-sm text-muted-foreground">
                            {t('sesiones.dialog_egreso.list_empty')}
                        </p>
                    ) : (
                        <ul className="max-h-48 divide-y divide-border/40 overflow-y-auto">
                            {egresos.map((row) => (
                                <li
                                    key={row.id}
                                    className="flex items-center justify-between gap-2 px-3 py-2 text-sm"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">{row.motivo_label}</p>
                                        {row.notas ? (
                                            <p className="truncate text-xs text-muted-foreground">
                                                {row.notas}
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className="flex shrink-0 items-center gap-1">
                                        <span className="tabular-nums text-rose-700 dark:text-rose-300">
                                            −{formatArqueoMoney(row.monto, moneda, locale)}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-8 cursor-pointer text-muted-foreground hover:text-destructive"
                                            disabled={deletingId === row.id}
                                            onClick={() => void onDelete(row.id)}
                                            aria-label={t('sesiones.dialog_egreso.delete')}
                                            title={t('sesiones.dialog_egreso.delete')}
                                        >
                                            {deletingId === row.id ? (
                                                <Loader2 className="size-3.5 animate-spin" />
                                            ) : (
                                                <Trash2 className="size-3.5" />
                                            )}
                                        </Button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </FormModal>
    );
}
