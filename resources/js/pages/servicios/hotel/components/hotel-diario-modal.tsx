import { Loader2, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { FormModal } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { toastManager } from '@/lib/toast';
import servicios from '@/routes/servicios';
import type { HotelEstanciaRow } from '../types';

type DiarioApiRow = {
    id: string;
    fecha: string;
    notas: string | null;
    created_at: string | null;
    creado_por: { id: string; name: string } | null;
};

function readXsrfToken(): string {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return m ? decodeURIComponent(m[1]) : '';
}

function jsonHeaders(): HeadersInit {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': readXsrfToken(),
    };
}

async function parseJsonResponse(res: Response): Promise<unknown> {
    const text = await res.text();

    if (!text) {
        return {};
    }

    try {
        return JSON.parse(text) as unknown;
    } catch {
        throw new Error(`HTTP ${res.status}`);
    }
}

function firstValidationMessage(body: unknown): string | null {
    if (!body || typeof body !== 'object' || !('errors' in body)) {
        return null;
    }

    const errs = (body as { errors?: Record<string, string[] | string> }).errors;
    if (!errs || typeof errs !== 'object') {
        return null;
    }

    for (const v of Object.values(errs)) {
        if (Array.isArray(v) && v.length > 0 && typeof v[0] === 'string') {
            return v[0];
        }

        if (typeof v === 'string' && v.trim() !== '') {
            return v;
        }
    }

    return null;
}

export type HotelDiarioModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    estancia: HotelEstanciaRow | null;
    canEdit: boolean;
};

export function HotelDiarioModal({ open, onOpenChange, estancia, canEdit }: HotelDiarioModalProps) {
    const { t, i18n } = useTranslation(['hotel', 'common']);
    const [loading, setLoading] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [entries, setEntries] = useState<DiarioApiRow[]>([]);
    const [fecha, setFecha] = useState('');
    const [notas, setNotas] = useState('');

    const listUrl = useMemo(() => {
        if (!estancia) {
            return '';
        }

        return servicios.hotel.diarios.index.url(estancia.id);
    }, [estancia]);

    const loadEntries = useCallback(async () => {
        if (!listUrl) {
            setEntries([]);

            return;
        }

        setLoading(true);

        try {
            const res = await fetch(listUrl, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': readXsrfToken(),
                },
            });

            const body = await parseJsonResponse(res);

            if (!res.ok) {
                throw new Error(firstValidationMessage(body) ?? `HTTP ${res.status}`);
            }

            const data = (body as { data?: DiarioApiRow[] }).data;
            setEntries(Array.isArray(data) ? data : []);
        } catch (e) {
            toastManager.error({
                title: t('diarios.toast.load_error'),
                description: e instanceof Error ? e.message : undefined,
            });
            setEntries([]);
        } finally {
            setLoading(false);
        }
    }, [listUrl, t]);

    useEffect(() => {
        if (!open || !estancia) {
            return;
        }

        const today = new Date();
        const pad = (n: number) => String(n).padStart(2, '0');
        setFecha(`${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`);
        setNotas('');
        void loadEntries();
    }, [open, estancia, loadEntries]);

    const onSubmit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!canEdit || !estancia || submitting || fecha.trim() === '') {
            return;
        }

        setSubmitting(true);

        try {
            const url = servicios.hotel.diarios.store.url(estancia.id);
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: jsonHeaders(),
                body: JSON.stringify({
                    fecha: fecha.trim(),
                    notas: notas.trim() === '' ? null : notas.trim(),
                }),
            });

            const body = await parseJsonResponse(res);

            if (!res.ok) {
                const msg =
                    firstValidationMessage(body) ??
                    (res.status === 422 ? t('diarios.toast.validation_error') : `HTTP ${res.status}`);
                toastManager.error({ title: t('diarios.toast.save_error'), description: msg });

                return;
            }

            toastManager.success({ title: t('diarios.toast.saved') });
            setNotas('');
            await loadEntries();
        } catch (e) {
            toastManager.error({
                title: t('diarios.toast.save_error'),
                description: e instanceof Error ? e.message : undefined,
            });
        } finally {
            setSubmitting(false);
        }
    };

    const onDelete = async (row: DiarioApiRow) => {
        if (!canEdit || !estancia || submitting) {
            return;
        }

        const ok = window.confirm(t('diarios.confirm_delete'));

        if (!ok) {
            return;
        }

        setSubmitting(true);

        try {
            const url = servicios.hotel.diarios.destroy.url({
                hotel_estancia: estancia.id,
                hotel_estancia_diario: row.id,
            });
            const res = await fetch(url, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': readXsrfToken(),
                },
            });

            if (!res.ok) {
                const body = await parseJsonResponse(res);
                throw new Error(firstValidationMessage(body) ?? `HTTP ${res.status}`);
            }

            toastManager.success({ title: t('diarios.toast.deleted') });
            await loadEntries();
        } catch (e) {
            toastManager.error({
                title: t('diarios.toast.delete_error'),
                description: e instanceof Error ? e.message : undefined,
            });
        } finally {
            setSubmitting(false);
        }
    };

    const tituloPaciente = estancia?.paciente?.nombre ?? '';

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={t('diarios.title')}
            description={
                tituloPaciente !== ''
                    ? t('diarios.description_with_patient', { nombre: tituloPaciente })
                    : t('diarios.description')
            }
            size="lg"
            blockDismiss
            onSubmit={canEdit ? onSubmit : undefined}
            footer={
                <Button type="button" variant="outline" className="cursor-pointer" onClick={() => onOpenChange(false)}>
                    {t('common:actions.close')}
                </Button>
            }
        >
            {canEdit ? (
                <div className="space-y-3 rounded-lg border border-border/80 bg-muted/30 p-4">
                    <div className="grid gap-2">
                        <Label htmlFor="hotel-diario-fecha">{t('diarios.field_fecha')}</Label>
                        <Input
                            id="hotel-diario-fecha"
                            type="date"
                            value={fecha}
                            onChange={(e) => setFecha(e.target.value)}
                            disabled={submitting || loading}
                            className="max-w-xs"
                            required
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="hotel-diario-notas">{t('diarios.field_notas')}</Label>
                        <Textarea
                            id="hotel-diario-notas"
                            value={notas}
                            onChange={(e) => setNotas(e.target.value)}
                            disabled={submitting || loading}
                            rows={3}
                            placeholder={t('diarios.field_notas_placeholder')}
                        />
                    </div>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={submitting || loading} className="cursor-pointer gap-2">
                            {submitting ? <Loader2 className="size-4 animate-spin" /> : null}
                            {t('diarios.submit_add')}
                        </Button>
                    </div>
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">{t('diarios.read_only_hint')}</p>
            )}

            <div className="mt-6 space-y-2">
                <p className="text-sm font-medium text-foreground">{t('diarios.list_heading')}</p>

                {loading ? (
                    <div className="flex items-center gap-2 py-6 text-sm text-muted-foreground">
                        <Loader2 className="size-4 animate-spin" />
                        {t('diarios.loading')}
                    </div>
                ) : entries.length === 0 ? (
                    <p className="py-4 text-sm text-muted-foreground">{t('diarios.empty')}</p>
                ) : (
                    <ul className="max-h-72 space-y-3 overflow-y-auto pr-1">
                        {entries.map((row) => (
                            <li
                                key={row.id}
                                className="rounded-md border border-border/70 bg-background px-3 py-2 text-sm shadow-sm"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div className="min-w-0 space-y-1">
                                        <div className="font-medium text-foreground">
                                            {new Date(row.fecha + 'T12:00:00').toLocaleDateString(i18n.language, {
                                                weekday: 'short',
                                                day: '2-digit',
                                                month: 'short',
                                                year: 'numeric',
                                            })}
                                        </div>
                                        {row.notas != null && row.notas.trim() !== '' ? (
                                            <p className="whitespace-pre-wrap text-muted-foreground">{row.notas}</p>
                                        ) : (
                                            <p className="text-xs italic text-muted-foreground">{t('diarios.sin_notas')}</p>
                                        )}
                                        {row.creado_por ? (
                                            <p className="text-xs text-muted-foreground">
                                                {t('diarios.registrado_por', { nombre: row.creado_por.name })}
                                            </p>
                                        ) : null}
                                    </div>
                                    {canEdit ? (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-8 shrink-0 cursor-pointer text-destructive hover:text-destructive"
                                            disabled={submitting}
                                            aria-label={t('diarios.delete_aria')}
                                            onClick={() => void onDelete(row)}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    ) : null}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </FormModal>
    );
}
