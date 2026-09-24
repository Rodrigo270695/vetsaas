import { useForm } from '@inertiajs/react';
import { Droplets, Loader2 } from 'lucide-react';
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
import type { InternamientoFluidoRow } from '../types';
import { AccionesRegistro } from './acciones-registro';

const TIPOS = ['cristaloide', 'coloide', 'mantenimiento', 'otro'] as const;
const VIAS = ['iv_periferica', 'iv_central', 'subcutanea', 'intraosea', 'oral'] as const;
const selectClass =
    'h-10 w-full cursor-pointer rounded-md border border-input bg-card/70 px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/25';

type Props = {
    internamientoId: string;
    fluidos: readonly InternamientoFluidoRow[];
    canUpdate: boolean;
    timeZone?: string;
};

function soloDecimal(value: string): string {
    const normalizado = value.replace(',', '.');
    let salida = '';
    let punto = false;

    for (const char of normalizado) {
        if (char >= '0' && char <= '9') {
            salida += char;
        } else if (char === '.' && !punto) {
            punto = true;
            salida += '.';
        }
    }

    return salida;
}

export function FluidosInternamiento({ internamientoId, fluidos, canUpdate, timeZone }: Props) {
    const { t, i18n } = useTranslation(['hospitalizacion', 'common']);
    const zona = zonaPeru(timeZone);
    const [editando, setEditando] = useState<InternamientoFluidoRow | null | 'nuevo'>(null);
    const [borrar, setBorrar] = useState<InternamientoFluidoRow | null>(null);

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-3 space-y-0 pb-3">
                <CardTitle className="text-base">{t('fluidos.title')}</CardTitle>
                {canUpdate ? (
                    <Button type="button" size="sm" className="cursor-pointer gap-2" onClick={() => setEditando('nuevo')}>
                        <Droplets className="size-4" strokeWidth={2.25} />
                        {t('fluidos.add')}
                    </Button>
                ) : null}
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {fluidos.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t('fluidos.empty')}</p>
                ) : (
                    fluidos.map((fluido) => (
                        <article key={fluido.id} className="flex items-start gap-3 rounded-lg border border-border/60 px-3 py-3">
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap gap-1.5">
                                    {fluido.tipo ? <Chip>{t(`fluidos.tipo_opcion.${fluido.tipo}`)}</Chip> : null}
                                    {fluido.solucion ? <Chip>{fluido.solucion}</Chip> : null}
                                    {fluido.via ? <Chip>{t(`fluidos.via_opcion.${fluido.via}`)}</Chip> : null}
                                    {fluido.volumen_ml ? <Chip>{fluido.volumen_ml} ml</Chip> : null}
                                    {fluido.velocidad_ml_h ? <Chip>{fluido.velocidad_ml_h} ml/h</Chip> : null}
                                    {fluido.goteo_gtt_min ? <Chip>{fluido.goteo_gtt_min} gtt/min</Chip> : null}
                                    {fluido.duracion_horas ? <Chip>{fluido.duracion_horas} h</Chip> : null}
                                </div>
                                {fluido.aditivos ? (
                                    <p className="mt-2 text-sm text-foreground">
                                        {t('fluidos.aditivos')}: {fluido.aditivos}
                                    </p>
                                ) : null}
                                <p className="mt-1.5 text-xs text-muted-foreground">
                                    {formatAtendidoInAppTimezone(fluido.registrado_at, i18n.language, zona)}
                                    {fluido.creado_por?.name ? ` · ${fluido.creado_por.name}` : ''}
                                </p>
                            </div>
                            {canUpdate ? (
                                <AccionesRegistro onEdit={() => setEditando(fluido)} onDelete={() => setBorrar(fluido)} />
                            ) : null}
                        </article>
                    ))
                )}
            </CardContent>
            <FluidoForm
                open={editando !== null}
                fluido={editando === 'nuevo' ? null : editando}
                internamientoId={internamientoId}
                timeZone={zona}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditando(null);
                    }
                }}
            />
            <BorrarFluido
                fluido={borrar}
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

function Chip({ children }: { children: string }) {
    return (
        <span className="inline-flex rounded-md bg-muted px-2 py-1 text-xs font-medium text-foreground">{children}</span>
    );
}

function FluidoForm({
    open,
    fluido,
    internamientoId,
    timeZone,
    onOpenChange,
}: {
    open: boolean;
    fluido: InternamientoFluidoRow | null;
    internamientoId: string;
    timeZone: string;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation(['hospitalizacion', 'common']);
    const { data, setData, post, put, processing, errors, clearErrors, transform, reset } = useForm({
        registrado_at: '',
        tipo: '',
        solucion: '',
        via: '',
        volumen_ml: '',
        velocidad_ml_h: '',
        goteo_gtt_min: '',
        duracion_horas: '',
        aditivos: '',
    });

    useEffect(() => {
        transform((raw) => ({
            registrado_at: raw.registrado_at,
            tipo: raw.tipo || null,
            solucion: raw.solucion.trim() || null,
            via: raw.via || null,
            volumen_ml: raw.volumen_ml || null,
            velocidad_ml_h: raw.velocidad_ml_h || null,
            goteo_gtt_min: raw.goteo_gtt_min || null,
            duracion_horas: raw.duracion_horas || null,
            aditivos: raw.aditivos.trim() || null,
        }));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }

        clearErrors();
        setData({
            registrado_at: fluido
                ? parseIsoToDatetimeLocal(fluido.registrado_at, timeZone)
                : toDatetimeLocalValue(Date.now(), timeZone),
            tipo: fluido?.tipo ?? '',
            solucion: fluido?.solucion ?? '',
            via: fluido?.via ?? '',
            volumen_ml: fluido?.volumen_ml ?? '',
            velocidad_ml_h: fluido?.velocidad_ml_h ?? '',
            goteo_gtt_min: fluido?.goteo_gtt_min ?? '',
            duracion_horas: fluido?.duracion_horas ?? '',
            aditivos: fluido?.aditivos ?? '',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, fluido?.id]);

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const done = () => onOpenChange(false);

        if (fluido) {
            put(`/clinica/hospitalizacion/${internamientoId}/fluidos/${fluido.id}`, { preserveScroll: true, onSuccess: done });
            return;
        }

        post(`/clinica/hospitalizacion/${internamientoId}/fluidos`, { preserveScroll: true, onSuccess: done });
    };

    const numero = (key: 'volumen_ml' | 'velocidad_ml_h' | 'goteo_gtt_min' | 'duracion_horas') => (
        <Input
            inputMode="decimal"
            value={data[key]}
            onChange={(e) => setData(key, soloDecimal(e.target.value))}
            disabled={processing}
        />
    );

    return (
        <FormModal
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    reset();
                }
                onOpenChange(next);
            }}
            title={fluido ? t('fluidos.title_edit') : t('fluidos.title_create')}
            size="lg"
            onSubmit={onSubmit}
            footer={
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={processing}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" disabled={processing} className="gap-2">
                        {processing && <Loader2 className="size-4 animate-spin" />}
                        {t('common:actions.save')}
                    </Button>
                </div>
            }
        >
            <FormSection title={t('fluidos.registrado_at')} columns={2}>
                <FormField id="fluido-fecha" label={t('fluidos.registrado_at')} required>
                    <Input
                        id="fluido-fecha"
                        type="datetime-local"
                        value={data.registrado_at}
                        onChange={(e) => setData('registrado_at', e.target.value)}
                        disabled={processing}
                    />
                </FormField>
                <FormField id="fluido-tipo" label={t('fluidos.tipo')}>
                    <select id="fluido-tipo" className={selectClass} value={data.tipo} onChange={(e) => setData('tipo', e.target.value)}>
                        <option value="">{t('fluidos.opcion_vacia')}</option>
                        {TIPOS.map((tipo) => (
                            <option key={tipo} value={tipo}>
                                {t(`fluidos.tipo_opcion.${tipo}`)}
                            </option>
                        ))}
                    </select>
                </FormField>
                <FormField id="fluido-solucion" label={t('fluidos.solucion')} error={typeof errors.solucion === 'string' ? errors.solucion : undefined}>
                    <Input
                        id="fluido-solucion"
                        list="fluidos-soluciones"
                        value={data.solucion}
                        placeholder={t('fluidos.solucion_placeholder')}
                        onChange={(e) => setData('solucion', e.target.value)}
                        disabled={processing}
                    />
                    <datalist id="fluidos-soluciones">
                        <option value="Lactato de Ringer" />
                        <option value="NaCl 0.9%" />
                        <option value="Glucosado 5%" />
                        <option value="Glucosalino" />
                    </datalist>
                </FormField>
                <FormField id="fluido-via" label={t('fluidos.via')}>
                    <select id="fluido-via" className={selectClass} value={data.via} onChange={(e) => setData('via', e.target.value)}>
                        <option value="">{t('fluidos.opcion_vacia')}</option>
                        {VIAS.map((via) => (
                            <option key={via} value={via}>
                                {t(`fluidos.via_opcion.${via}`)}
                            </option>
                        ))}
                    </select>
                </FormField>
                <FormField id="fluido-vol" label={t('fluidos.volumen')}>{numero('volumen_ml')}</FormField>
                <FormField id="fluido-vel" label={t('fluidos.velocidad')}>{numero('velocidad_ml_h')}</FormField>
                <FormField id="fluido-gtt" label={t('fluidos.goteo')}>{numero('goteo_gtt_min')}</FormField>
                <FormField id="fluido-dur" label={t('fluidos.duracion')}>{numero('duracion_horas')}</FormField>
                <FormField id="fluido-aditivos" label={t('fluidos.aditivos')} className="sm:col-span-2">
                    <Textarea
                        id="fluido-aditivos"
                        className="min-h-16"
                        value={data.aditivos}
                        placeholder={t('fluidos.aditivos_placeholder')}
                        onChange={(e) => setData('aditivos', e.target.value)}
                        disabled={processing}
                    />
                </FormField>
            </FormSection>
        </FormModal>
    );
}

function BorrarFluido({
    fluido,
    internamientoId,
    onOpenChange,
}: {
    fluido: InternamientoFluidoRow | null;
    internamientoId: string;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation(['hospitalizacion', 'common']);
    const { delete: destroy, processing } = useForm({});

    return (
        <Dialog open={fluido !== null} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{t('fluidos.delete_title')}</DialogTitle>
                    <DialogDescription>{t('fluidos.delete_description')}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={processing}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        className="gap-2"
                        disabled={processing || fluido === null}
                        onClick={() => {
                            if (!fluido) {
                                return;
                            }
                            destroy(`/clinica/hospitalizacion/${internamientoId}/fluidos/${fluido.id}`, {
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
