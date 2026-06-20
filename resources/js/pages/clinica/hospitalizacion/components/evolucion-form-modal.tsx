import { useForm, usePage } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { enqueueIfOffline } from '@/lib/offline/enqueue-if-offline';
import { useOfflineSync } from '@/hooks/use-offline-sync';
import type { InternamientoEvolucionRow } from '../types';

const controlClass = 'h-10 w-full min-w-0';

function toDatetimeLocalValue(d: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function parseIsoToDatetimeLocal(iso: string): string {
    const d = new Date(iso);

    if (Number.isNaN(d.getTime())) {
        return toDatetimeLocalValue(new Date());
    }

    return toDatetimeLocalValue(d);
}

type FormShape = {
    registrado_at: string;
    evolucion: string;
    tratamiento: string;
    peso_kg: string;
    temperatura_c: string;
    fc_lpm: string;
    fr_rpm: string;
    veterinario_id: string | null;
};

function emptyForm(defaultVetId: string | null): FormShape {
    return {
        registrado_at: toDatetimeLocalValue(new Date()),
        evolucion: '',
        tratamiento: '',
        peso_kg: '',
        temperatura_c: '',
        fc_lpm: '',
        fr_rpm: '',
        veterinario_id: defaultVetId,
    };
}

function fromEvolucion(e: InternamientoEvolucionRow, defaultVetId: string | null): FormShape {
    return {
        registrado_at: parseIsoToDatetimeLocal(e.registrado_at),
        evolucion: e.evolucion,
        tratamiento: e.tratamiento ?? '',
        peso_kg: e.peso_kg != null && e.peso_kg !== '' ? String(e.peso_kg) : '',
        temperatura_c: e.temperatura_c != null && e.temperatura_c !== '' ? String(e.temperatura_c) : '',
        fc_lpm: e.fc_lpm != null ? String(e.fc_lpm) : '',
        fr_rpm: e.fr_rpm != null ? String(e.fr_rpm) : '',
        veterinario_id: e.veterinario_id ?? defaultVetId,
    };
}

export type EvolucionFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    internamientoId: string;
    evolucion: InternamientoEvolucionRow | null;
};

export function EvolucionFormModal({
    open,
    onOpenChange,
    internamientoId,
    evolucion,
}: EvolucionFormModalProps) {
    const { t } = useTranslation(['hospitalizacion', 'common', 'offline']);
    const { refreshPending } = useOfflineSync();
    const authUser = usePage().props.auth?.user as { id?: string } | undefined;
    const defaultVetId = authUser?.id ?? null;

    const { data, setData, post, put, processing, errors, clearErrors, transform, setDefaults, reset } =
        useForm<FormShape>(emptyForm(defaultVetId));

    const isEdit = evolucion !== null;
    const initialRef = useRef<FormShape>(emptyForm(null));

    useEffect(() => {
        transform((raw) => {
            const r = raw;
            const peso = r.peso_kg.trim();
            const temp = r.temperatura_c.trim();

            return {
                registrado_at: r.registrado_at,
                evolucion: r.evolucion.trim(),
                tratamiento: r.tratamiento.trim() === '' ? null : r.tratamiento.trim(),
                peso_kg: peso === '' ? null : Number.parseFloat(peso),
                temperatura_c: temp === '' ? null : Number.parseFloat(temp),
                fc_lpm: r.fc_lpm.trim() === '' ? null : Number.parseInt(r.fc_lpm, 10),
                fr_rpm: r.fr_rpm.trim() === '' ? null : Number.parseInt(r.fr_rpm, 10),
                veterinario_id:
                    r.veterinario_id != null && r.veterinario_id !== '' ? r.veterinario_id : null,
            };
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }

        clearErrors();
        const next = evolucion !== null ? fromEvolucion(evolucion, defaultVetId) : emptyForm(defaultVetId);
        initialRef.current = structuredClone(next);
        setData(next);
        setDefaults();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, evolucion?.id, defaultVetId, evolucion]);

    const handleClose = (next: boolean) => {
        if (!next) {
            reset();
            clearErrors();
        }

        onOpenChange(next);
    };

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const onSuccess = () => {
            reset();
            clearErrors();
            onOpenChange(false);
        };

        if (isEdit && evolucion) {
            put(`/clinica/hospitalizacion/${internamientoId}/evoluciones/${evolucion.id}`, {
                preserveScroll: true,
                onSuccess,
            });

            return;
        }

        void (async () => {
            const peso = data.peso_kg.trim();
            const temp = data.temperatura_c.trim();
            const payload: Record<string, unknown> = {
                internamiento_id: internamientoId,
                registrado_at: data.registrado_at,
                evolucion: data.evolucion.trim(),
                tratamiento: data.tratamiento.trim() === '' ? null : data.tratamiento.trim(),
                peso_kg: peso === '' ? null : Number.parseFloat(peso),
                temperatura_c: temp === '' ? null : Number.parseFloat(temp),
                fc_lpm: data.fc_lpm.trim() === '' ? null : Number.parseInt(data.fc_lpm, 10),
                fr_rpm: data.fr_rpm.trim() === '' ? null : Number.parseInt(data.fr_rpm, 10),
                veterinario_id:
                    data.veterinario_id != null && data.veterinario_id !== ''
                        ? data.veterinario_id
                        : null,
            };

            const queued = await enqueueIfOffline(
                'clinica.internamiento.evolucion.create',
                payload,
                {
                    refreshPending,
                    onSuccess,
                    title: t('offline:evolucion.queued_title'),
                    description: t('offline:evolucion.queued_body'),
                },
            );

            if (queued) {
                return;
            }

            post(`/clinica/hospitalizacion/${internamientoId}/evoluciones`, {
                preserveScroll: true,
                onSuccess,
            });
        })();
    };

    const err = (key: string): string | undefined => {
        const v = (errors as Record<string, string | undefined>)[key];

        return typeof v === 'string' ? v : undefined;
    };

    const canSubmit = data.evolucion.trim().length > 0 && data.registrado_at.trim().length > 0 && !processing;

    return (
        <FormModal
            open={open}
            onOpenChange={handleClose}
            title={isEdit ? t('evolucion.title_edit') : t('evolucion.title_create')}
            size="lg"
            onSubmit={onSubmit}
            footer={
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={() => handleClose(false)} disabled={processing}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" disabled={!canSubmit} className="gap-2">
                        {processing && <Loader2 className="size-4 animate-spin" />}
                        {t('common:actions.save')}
                    </Button>
                </div>
            }
        >
            <FormSection title={t('evolucion.registrado_at')} columns={2}>
                <FormField
                    id="evo-fecha"
                    label={t('evolucion.registrado_at')}
                    required
                    error={err('registrado_at')}
                >
                    <Input
                        id="evo-fecha"
                        type="datetime-local"
                        className={controlClass}
                        value={data.registrado_at}
                        onChange={(e) => setData('registrado_at', e.target.value)}
                        disabled={processing}
                    />
                </FormField>
                <FormField
                    id="evo-texto"
                    label={t('evolucion.evolucion')}
                    required
                    error={err('evolucion')}
                    className="sm:col-span-2"
                >
                    <Textarea
                        id="evo-texto"
                        className="min-h-24 w-full"
                        value={data.evolucion}
                        onChange={(e) => setData('evolucion', e.target.value)}
                        placeholder={t('evolucion.evolucion_placeholder')}
                        disabled={processing}
                    />
                </FormField>
                <FormField
                    id="evo-trat"
                    label={t('evolucion.tratamiento')}
                    error={err('tratamiento')}
                    className="sm:col-span-2"
                >
                    <Textarea
                        id="evo-trat"
                        className="min-h-20 w-full"
                        value={data.tratamiento}
                        onChange={(e) => setData('tratamiento', e.target.value)}
                        placeholder={t('evolucion.tratamiento_placeholder')}
                        disabled={processing}
                    />
                </FormField>
            </FormSection>
            <FormSection title={t('evolucion.vitales')} columns={4}>
                <FormField id="evo-peso" label={t('evolucion.peso_kg')} error={err('peso_kg')}>
                    <Input
                        id="evo-peso"
                        inputMode="decimal"
                        className={controlClass}
                        value={data.peso_kg}
                        onChange={(e) => setData('peso_kg', e.target.value)}
                        placeholder={t('evolucion.vital_placeholder')}
                        disabled={processing}
                    />
                </FormField>
                <FormField id="evo-temp" label={t('evolucion.temperatura_c')} error={err('temperatura_c')}>
                    <Input
                        id="evo-temp"
                        inputMode="decimal"
                        className={controlClass}
                        value={data.temperatura_c}
                        onChange={(e) => setData('temperatura_c', e.target.value)}
                        placeholder={t('evolucion.vital_placeholder')}
                        disabled={processing}
                    />
                </FormField>
                <FormField id="evo-fc" label={t('evolucion.fc_lpm')} error={err('fc_lpm')}>
                    <Input
                        id="evo-fc"
                        inputMode="numeric"
                        className={controlClass}
                        value={data.fc_lpm}
                        onChange={(e) => setData('fc_lpm', e.target.value)}
                        placeholder={t('evolucion.vital_placeholder')}
                        disabled={processing}
                    />
                </FormField>
                <FormField id="evo-fr" label={t('evolucion.fr_rpm')} error={err('fr_rpm')}>
                    <Input
                        id="evo-fr"
                        inputMode="numeric"
                        className={controlClass}
                        value={data.fr_rpm}
                        onChange={(e) => setData('fr_rpm', e.target.value)}
                        placeholder={t('evolucion.vital_placeholder')}
                        disabled={processing}
                    />
                </FormField>
            </FormSection>
        </FormModal>
    );
}
