import { TZDate } from '@date-fns/tz';
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
const ZONA_PERU = 'America/Lima';

function zonaPeru(timeZone: string | undefined): string {
    const zona = (timeZone ?? '').trim();

    if (zona === '' || zona.toUpperCase() === 'UTC' || zona === 'Etc/UTC') {
        return ZONA_PERU;
    }

    return zona;
}

function toDatetimeLocalValue(instant: Date | number, timeZone: string): string {
    const d = new TZDate(instant, timeZone);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function parseIsoToDatetimeLocal(iso: string, timeZone: string): string {
    const d = new TZDate(iso, timeZone);

    if (Number.isNaN(d.getTime())) {
        return toDatetimeLocalValue(Date.now(), timeZone);
    }

    return toDatetimeLocalValue(d, timeZone);
}

type FormShape = {
    registrado_at: string;
    evolucion: string;
    tratamiento: string;
    peso_kg: string;
    temperatura_c: string;
    fc_lpm: string;
    fr_rpm: string;
    deshidratacion_pct: string;
    tllc_segundos: string;
    pas: string;
    pad: string;
    pam: string;
    veterinario_id: string | null;
};

function numOrEmpty(value: number | string | null | undefined): string {
    if (value == null || value === '') {
        return '';
    }

    return String(value);
}

function sugerirPam(pas: string, pad: string): string {
    const sistolica = Number.parseInt(pas, 10);
    const diastolica = Number.parseInt(pad, 10);

    if (Number.isNaN(sistolica) || Number.isNaN(diastolica)) {
        return '';
    }

    return String(Math.round((sistolica + 2 * diastolica) / 3));
}

function emptyForm(defaultVetId: string | null, timeZone: string): FormShape {
    return {
        registrado_at: toDatetimeLocalValue(Date.now(), timeZone),
        evolucion: '',
        tratamiento: '',
        peso_kg: '',
        temperatura_c: '',
        fc_lpm: '',
        fr_rpm: '',
        deshidratacion_pct: '',
        tllc_segundos: '',
        pas: '',
        pad: '',
        pam: '',
        veterinario_id: defaultVetId,
    };
}

function fromEvolucion(e: InternamientoEvolucionRow, defaultVetId: string | null, timeZone: string): FormShape {
    return {
        registrado_at: parseIsoToDatetimeLocal(e.registrado_at, timeZone),
        evolucion: e.evolucion ?? '',
        tratamiento: e.tratamiento ?? '',
        peso_kg: numOrEmpty(e.peso_kg),
        temperatura_c: numOrEmpty(e.temperatura_c),
        fc_lpm: numOrEmpty(e.fc_lpm),
        fr_rpm: numOrEmpty(e.fr_rpm),
        deshidratacion_pct: numOrEmpty(e.deshidratacion_pct),
        tllc_segundos: numOrEmpty(e.tllc_segundos),
        pas: numOrEmpty(e.pas),
        pad: numOrEmpty(e.pad),
        pam: numOrEmpty(e.pam),
        veterinario_id: e.veterinario_id ?? defaultVetId,
    };
}

function decimalOrNull(value: string): number | null {
    const trimmed = value.trim();

    if (trimmed === '') {
        return null;
    }

    const parsed = Number.parseFloat(trimmed);

    return Number.isNaN(parsed) ? null : parsed;
}

function intOrNull(value: string): number | null {
    const trimmed = value.trim();

    if (trimmed === '') {
        return null;
    }

    const parsed = Number.parseInt(trimmed, 10);

    return Number.isNaN(parsed) ? null : parsed;
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
    const page = usePage();
    const authUser = page.props.auth?.user as { id?: string } | undefined;
    const defaultVetId = authUser?.id ?? null;
    const zona = zonaPeru(page.props.timezone);

    const { data, setData, post, put, processing, errors, clearErrors, transform, setDefaults, reset } =
        useForm<FormShape>(emptyForm(defaultVetId, zona));

    const isEdit = evolucion !== null;
    const initialRef = useRef<FormShape>(emptyForm(null, zona));
    const pamManualRef = useRef(false);

    useEffect(() => {
        transform((raw) => {
            const r = raw;

            return {
                registrado_at: r.registrado_at,
                evolucion: r.evolucion.trim() === '' ? null : r.evolucion.trim(),
                tratamiento: r.tratamiento.trim() === '' ? null : r.tratamiento.trim(),
                peso_kg: decimalOrNull(r.peso_kg),
                temperatura_c: decimalOrNull(r.temperatura_c),
                fc_lpm: intOrNull(r.fc_lpm),
                fr_rpm: intOrNull(r.fr_rpm),
                deshidratacion_pct: decimalOrNull(r.deshidratacion_pct),
                tllc_segundos: decimalOrNull(r.tllc_segundos),
                pas: intOrNull(r.pas),
                pad: intOrNull(r.pad),
                pam: intOrNull(r.pam),
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
        pamManualRef.current = evolucion !== null && evolucion.pam != null;
        const next = evolucion !== null ? fromEvolucion(evolucion, defaultVetId, zona) : emptyForm(defaultVetId, zona);
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
            const payload: Record<string, unknown> = {
                internamiento_id: internamientoId,
                registrado_at: data.registrado_at,
                evolucion: data.evolucion.trim() === '' ? null : data.evolucion.trim(),
                tratamiento: data.tratamiento.trim() === '' ? null : data.tratamiento.trim(),
                peso_kg: decimalOrNull(data.peso_kg),
                temperatura_c: decimalOrNull(data.temperatura_c),
                fc_lpm: intOrNull(data.fc_lpm),
                fr_rpm: intOrNull(data.fr_rpm),
                deshidratacion_pct: decimalOrNull(data.deshidratacion_pct),
                tllc_segundos: decimalOrNull(data.tllc_segundos),
                pas: intOrNull(data.pas),
                pad: intOrNull(data.pad),
                pam: intOrNull(data.pam),
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

    const tieneAlgunaConstante = [
        data.peso_kg,
        data.temperatura_c,
        data.fc_lpm,
        data.fr_rpm,
        data.deshidratacion_pct,
        data.tllc_segundos,
        data.pas,
        data.pad,
        data.pam,
        data.evolucion,
        data.tratamiento,
    ].some((value) => value.trim() !== '');

    const canSubmit = tieneAlgunaConstante && data.registrado_at.trim().length > 0 && !processing;

    const setPresion = (key: 'pas' | 'pad', value: string) => {
        const next = { ...data, [key]: value };
        const pam = sugerirPam(next.pas, next.pad);

        setData({
            ...next,
            pam: pamManualRef.current ? data.pam : pam,
        });
    };

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
            </FormSection>
            <FormSection title={t('evolucion.vitales')} columns={2}>
                <FormField id="evo-temp" label={t('evolucion.temperatura_c')} error={err('temperatura_c')}>
                    <Input
                        id="evo-temp"
                        inputMode="decimal"
                        className={controlClass}
                        value={data.temperatura_c}
                        onChange={(e) => setData('temperatura_c', e.target.value)}
                        placeholder="°C"
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
                        placeholder="LPM"
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
                        placeholder="RPM"
                        disabled={processing}
                    />
                </FormField>
                <FormField id="evo-peso" label={t('evolucion.peso_kg')} error={err('peso_kg')}>
                    <Input
                        id="evo-peso"
                        inputMode="decimal"
                        className={controlClass}
                        value={data.peso_kg}
                        onChange={(e) => setData('peso_kg', e.target.value)}
                        placeholder="Kg"
                        disabled={processing}
                    />
                </FormField>
                <FormField id="evo-deshid" label={t('evolucion.deshidratacion')} error={err('deshidratacion_pct')}>
                    <Input
                        id="evo-deshid"
                        inputMode="decimal"
                        className={controlClass}
                        value={data.deshidratacion_pct}
                        onChange={(e) => setData('deshidratacion_pct', e.target.value)}
                        placeholder="%"
                        disabled={processing}
                    />
                </FormField>
                <FormField id="evo-tllc" label={t('evolucion.tllc')} error={err('tllc_segundos')}>
                    <Input
                        id="evo-tllc"
                        inputMode="decimal"
                        className={controlClass}
                        value={data.tllc_segundos}
                        onChange={(e) => setData('tllc_segundos', e.target.value)}
                        placeholder={t('evolucion.tllc_placeholder')}
                        disabled={processing}
                    />
                </FormField>
                <FormField id="evo-pas" label={t('evolucion.pas')} error={err('pas')}>
                    <Input
                        id="evo-pas"
                        inputMode="numeric"
                        className={controlClass}
                        value={data.pas}
                        onChange={(e) => setPresion('pas', e.target.value)}
                        placeholder="mmHg"
                        disabled={processing}
                    />
                </FormField>
                <FormField id="evo-pad" label={t('evolucion.pad')} error={err('pad')}>
                    <Input
                        id="evo-pad"
                        inputMode="numeric"
                        className={controlClass}
                        value={data.pad}
                        onChange={(e) => setPresion('pad', e.target.value)}
                        placeholder="mmHg"
                        disabled={processing}
                    />
                </FormField>
                <FormField id="evo-pam" label={t('evolucion.pam')} hint={t('evolucion.pam_hint')} error={err('pam')}>
                    <Input
                        id="evo-pam"
                        inputMode="numeric"
                        className={controlClass}
                        value={data.pam}
                        onChange={(e) => {
                            pamManualRef.current = e.target.value.trim() !== '';
                            setData('pam', e.target.value);
                        }}
                        placeholder="mmHg"
                        disabled={processing}
                    />
                </FormField>
                <FormField
                    id="evo-texto"
                    label={t('evolucion.evolucion')}
                    error={err('evolucion')}
                    className="sm:col-span-2"
                >
                    <Textarea
                        id="evo-texto"
                        className="min-h-16 w-full"
                        value={data.evolucion}
                        onChange={(e) => setData('evolucion', e.target.value)}
                        placeholder={t('evolucion.evolucion_placeholder')}
                        disabled={processing}
                    />
                </FormField>
            </FormSection>
        </FormModal>
    );
}
