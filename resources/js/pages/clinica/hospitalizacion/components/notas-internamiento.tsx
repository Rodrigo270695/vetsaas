import { useForm } from '@inertiajs/react';
import { Loader2, StickyNote } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { formatAtendidoInAppTimezone } from '../../historias-clinicas/format-atendido';
import { parseIsoToDatetimeLocal, toDatetimeLocalValue, zonaPeru } from '../fecha-clinica';
import type { InternamientoNotaRow } from '../types';
import { AccionesRegistro } from './acciones-registro';

type Props = {
    internamientoId: string;
    notas: readonly InternamientoNotaRow[];
    canUpdate: boolean;
    timeZone?: string;
};

export function NotasInternamiento({ internamientoId, notas, canUpdate, timeZone }: Props) {
    const { t, i18n } = useTranslation(['hospitalizacion', 'common']);
    const zona = zonaPeru(timeZone);
    const [editando, setEditando] = useState<InternamientoNotaRow | null | 'nueva'>(null);
    const [borrar, setBorrar] = useState<InternamientoNotaRow | null>(null);

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-3 space-y-0 pb-3">
                <CardTitle className="text-base">{t('notas.title')}</CardTitle>
                {canUpdate ? (
                    <Button type="button" size="sm" className="cursor-pointer gap-2" onClick={() => setEditando('nueva')}>
                        <StickyNote className="size-4" strokeWidth={2.25} />
                        {t('notas.add')}
                    </Button>
                ) : null}
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {notas.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t('notas.empty')}</p>
                ) : (
                    notas.map((nota) => (
                        <article key={nota.id} className="flex items-start gap-3 rounded-lg border border-border/60 px-3 py-2.5">
                            <div className="min-w-0 flex-1">
                                <p className="text-sm whitespace-pre-wrap text-foreground">{nota.cuerpo}</p>
                                <p className="mt-1.5 text-xs text-muted-foreground">
                                    {formatAtendidoInAppTimezone(nota.registrado_at, i18n.language, zona)}
                                    {nota.creado_por?.name ? ` · ${nota.creado_por.name}` : ''}
                                </p>
                            </div>
                            {canUpdate ? (
                                <AccionesRegistro onEdit={() => setEditando(nota)} onDelete={() => setBorrar(nota)} />
                            ) : null}
                        </article>
                    ))
                )}
            </CardContent>
            <NotaFormModal
                open={editando !== null}
                nota={editando === 'nueva' ? null : editando}
                internamientoId={internamientoId}
                timeZone={zona}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditando(null);
                    }
                }}
            />
            <BorrarNota
                nota={borrar}
                internamientoId={internamientoId}
                onOpenChange={(open) => {
                    if (!open) {
                        setBorrar(null);
                    }
                }}
            />
        </Card>
    );
}

function NotaFormModal({
    open,
    nota,
    internamientoId,
    timeZone,
    onOpenChange,
}: {
    open: boolean;
    nota: InternamientoNotaRow | null;
    internamientoId: string;
    timeZone: string;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation(['hospitalizacion', 'common']);
    const { data, setData, post, put, processing, errors, clearErrors, reset } = useForm({
        registrado_at: '',
        cuerpo: '',
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        clearErrors();
        setData({
            registrado_at: nota
                ? parseIsoToDatetimeLocal(nota.registrado_at, timeZone)
                : toDatetimeLocalValue(Date.now(), timeZone),
            cuerpo: nota?.cuerpo ?? '',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, nota?.id]);

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const done = () => onOpenChange(false);

        if (nota) {
            put(`/clinica/hospitalizacion/${internamientoId}/notas/${nota.id}`, { preserveScroll: true, onSuccess: done });
            return;
        }

        post(`/clinica/hospitalizacion/${internamientoId}/notas`, { preserveScroll: true, onSuccess: done });
    };

    const err = (key: string) => {
        const value = (errors as Record<string, string | undefined>)[key];
        return typeof value === 'string' ? value : undefined;
    };

    return (
        <FormModal
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    reset();
                }
                onOpenChange(next);
            }}
            title={nota ? t('notas.title_edit') : t('notas.title_create')}
            size="md"
            onSubmit={onSubmit}
            footer={
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={processing}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" disabled={processing || data.cuerpo.trim() === ''} className="gap-2">
                        {processing && <Loader2 className="size-4 animate-spin" />}
                        {t('common:actions.save')}
                    </Button>
                </div>
            }
        >
            <FormSection title={t('notas.registrado_at')} columns={1}>
                <FormField id="nota-fecha" label={t('notas.registrado_at')} required error={err('registrado_at')}>
                    <Input
                        id="nota-fecha"
                        type="datetime-local"
                        value={data.registrado_at}
                        onChange={(e) => setData('registrado_at', e.target.value)}
                        disabled={processing}
                    />
                </FormField>
                <FormField id="nota-cuerpo" label={t('notas.cuerpo')} required error={err('cuerpo')}>
                    <Textarea
                        id="nota-cuerpo"
                        className="min-h-28"
                        value={data.cuerpo}
                        onChange={(e) => setData('cuerpo', e.target.value)}
                        placeholder={t('notas.cuerpo_placeholder')}
                        disabled={processing}
                    />
                </FormField>
            </FormSection>
        </FormModal>
    );
}

function BorrarNota({
    nota,
    internamientoId,
    onOpenChange,
}: {
    nota: InternamientoNotaRow | null;
    internamientoId: string;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation(['hospitalizacion', 'common']);
    const { delete: destroy, processing } = useForm({});

    return (
        <Dialog open={nota !== null} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{t('notas.delete_title')}</DialogTitle>
                    <DialogDescription>{t('notas.delete_description')}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={processing}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={processing || nota === null}
                        className="gap-2"
                        onClick={() => {
                            if (!nota) {
                                return;
                            }
                            destroy(`/clinica/hospitalizacion/${internamientoId}/notas/${nota.id}`, {
                                preserveScroll: true,
                                onSuccess: () => onOpenChange(false),
                            });
                        }}
                    >
                        {processing && <Loader2 className="size-4 animate-spin" />}
                        {t('common:actions.delete')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
