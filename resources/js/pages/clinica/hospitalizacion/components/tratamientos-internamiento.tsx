import { useForm } from '@inertiajs/react';
import { Loader2, Syringe } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection } from '@/components/forms';
import { Combobox } from '@/components/ui/combobox';
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
import type { InternamientoTratamientoRow, ServicioTratamientoOpcion } from '../types';
import { AccionesRegistro } from './acciones-registro';

type Props = {
    internamientoId: string;
    tratamientos: readonly InternamientoTratamientoRow[];
    servicios: readonly ServicioTratamientoOpcion[];
    canUpdate: boolean;
    timeZone?: string;
};

export function TratamientosInternamiento({ internamientoId, tratamientos, servicios, canUpdate, timeZone }: Props) {
    const { t, i18n } = useTranslation(['hospitalizacion', 'common']);
    const zona = zonaPeru(timeZone);
    const [editando, setEditando] = useState<InternamientoTratamientoRow | null | 'nuevo'>(null);
    const [borrar, setBorrar] = useState<InternamientoTratamientoRow | null>(null);

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-3 space-y-0 pb-3">
                <CardTitle className="text-base">{t('tratamientos.title')}</CardTitle>
                {canUpdate ? (
                    <Button type="button" size="sm" className="cursor-pointer gap-2" onClick={() => setEditando('nuevo')}>
                        <Syringe className="size-4" strokeWidth={2.25} />
                        {t('tratamientos.add')}
                    </Button>
                ) : null}
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {tratamientos.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t('tratamientos.empty')}</p>
                ) : (
                    tratamientos.map((item) => (
                        <article key={item.id} className="flex items-start gap-3 rounded-lg border border-border/60 px-3 py-2.5">
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-medium text-foreground">
                                    {item.servicio_clinico?.nombre ?? '—'}
                                </p>
                                {item.servicio_clinico ? (
                                    <p className="text-xs text-muted-foreground">
                                        {item.servicio_clinico.moneda} {item.servicio_clinico.precio_lista}
                                    </p>
                                ) : null}
                                {item.detalle ? <p className="mt-1 text-sm whitespace-pre-wrap">{item.detalle}</p> : null}
                                <p className="mt-1.5 text-xs text-muted-foreground">
                                    {formatAtendidoInAppTimezone(item.registrado_at, i18n.language, zona)}
                                    {item.creado_por?.name ? ` · ${item.creado_por.name}` : ''}
                                </p>
                            </div>
                            {canUpdate ? (
                                <AccionesRegistro onEdit={() => setEditando(item)} onDelete={() => setBorrar(item)} />
                            ) : null}
                        </article>
                    ))
                )}
            </CardContent>
            <TratamientoForm
                open={editando !== null}
                item={editando === 'nuevo' ? null : editando}
                servicios={servicios}
                internamientoId={internamientoId}
                timeZone={zona}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditando(null);
                    }
                }}
            />
            <BorrarTratamiento
                item={borrar}
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

function TratamientoForm({
    open,
    item,
    servicios,
    internamientoId,
    timeZone,
    onOpenChange,
}: {
    open: boolean;
    item: InternamientoTratamientoRow | null;
    servicios: readonly ServicioTratamientoOpcion[];
    internamientoId: string;
    timeZone: string;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation(['hospitalizacion', 'common']);
    const { data, setData, post, put, processing, errors, clearErrors, reset } = useForm({
        registrado_at: '',
        servicio_clinico_id: '',
        detalle: '',
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        clearErrors();
        setData({
            registrado_at: item
                ? parseIsoToDatetimeLocal(item.registrado_at, timeZone)
                : toDatetimeLocalValue(Date.now(), timeZone),
            servicio_clinico_id: item?.servicio_clinico_id ?? '',
            detalle: item?.detalle ?? '',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, item?.id]);

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const done = () => onOpenChange(false);

        if (item) {
            put(`/clinica/hospitalizacion/${internamientoId}/tratamientos/${item.id}`, {
                preserveScroll: true,
                onSuccess: done,
            });
            return;
        }

        post(`/clinica/hospitalizacion/${internamientoId}/tratamientos`, { preserveScroll: true, onSuccess: done });
    };

    const opciones = servicios.map((servicio) => ({
        value: servicio.id,
        label: `${servicio.nombre} · ${servicio.moneda} ${servicio.precio_lista}`,
        keywords: servicio.nombre,
    }));

    return (
        <FormModal
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    reset();
                }
                onOpenChange(next);
            }}
            title={item ? t('tratamientos.title_edit') : t('tratamientos.title_create')}
            size="md"
            onSubmit={onSubmit}
            footer={
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={processing}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" disabled={processing || data.servicio_clinico_id === ''} className="gap-2">
                        {processing && <Loader2 className="size-4 animate-spin" />}
                        {t('common:actions.save')}
                    </Button>
                </div>
            }
        >
            <FormSection title={t('tratamientos.servicio')} columns={1}>
                <FormField
                    id="trat-fecha"
                    label={t('tratamientos.registrado_at')}
                    required
                    error={typeof errors.registrado_at === 'string' ? errors.registrado_at : undefined}
                >
                    <Input
                        id="trat-fecha"
                        type="datetime-local"
                        value={data.registrado_at}
                        onChange={(e) => setData('registrado_at', e.target.value)}
                        disabled={processing}
                    />
                </FormField>
                <FormField
                    id="trat-servicio"
                    label={t('tratamientos.servicio')}
                    required
                    error={typeof errors.servicio_clinico_id === 'string' ? errors.servicio_clinico_id : undefined}
                >
                    <Combobox
                        id="trat-servicio"
                        options={opciones}
                        value={data.servicio_clinico_id || null}
                        onChange={(value) => setData('servicio_clinico_id', value ?? '')}
                        placeholder={t('tratamientos.servicio_placeholder')}
                        searchPlaceholder={t('tratamientos.servicio_placeholder')}
                        emptyMessage={t('tratamientos.servicio_empty')}
                        disabled={processing}
                    />
                </FormField>
                <FormField id="trat-detalle" label={t('tratamientos.detalle')}>
                    <Textarea
                        id="trat-detalle"
                        className="min-h-16"
                        value={data.detalle}
                        placeholder={t('tratamientos.detalle_placeholder')}
                        onChange={(e) => setData('detalle', e.target.value)}
                        disabled={processing}
                    />
                </FormField>
            </FormSection>
        </FormModal>
    );
}

function BorrarTratamiento({
    item,
    internamientoId,
    onOpenChange,
}: {
    item: InternamientoTratamientoRow | null;
    internamientoId: string;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation(['hospitalizacion', 'common']);
    const { delete: destroy, processing } = useForm({});

    return (
        <Dialog open={item !== null} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{t('tratamientos.delete_title')}</DialogTitle>
                    <DialogDescription>{t('tratamientos.delete_description')}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={processing}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        className="gap-2"
                        disabled={processing || item === null}
                        onClick={() => {
                            if (!item) {
                                return;
                            }
                            destroy(`/clinica/hospitalizacion/${internamientoId}/tratamientos/${item.id}`, {
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
