import { router, useForm } from '@inertiajs/react';
import { AlertTriangle, Loader2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import type { ComboboxOption } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { enqueueIfOffline } from '@/lib/offline/enqueue-if-offline';
import { useOfflineSync } from '@/hooks/use-offline-sync';
import clinica from '@/routes/clinica';
import type { ConsultaHistoriaRow, PacienteHistoriaOpcion } from '../types';
import {
    ConsultaDictationBar,
    type ConsultaDictationFields,
} from './consulta-dictation-bar';
import { ConsultaEstadoBadge } from './consulta-estado-badge';

export type ConsultaFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    consulta: ConsultaHistoriaRow | null;
    pacientesOpciones: readonly PacienteHistoriaOpcion[];
    /** Desde `?nuevo_para_paciente=` en la URL (ficha del paciente / aperturar cita). */
    pacienteIdPrefillNueva?: string | null;
    /** Motivo opcional desde `?motivo=` (p. ej. al aperturar una cita). */
    motivoPrefillNueva?: string | null;
    /** Cita vinculada desde `?cita_id=` al aperturar. */
    citaIdPrefillNueva?: string | null;
    puedeCerrarConsulta?: boolean;
};

type FormData = {
    paciente_id: string;
    cita_id: string;
    atendido_at: string;
    motivo: string;
    subjetivo: string;
    objetivo: string;
    analisis: string;
    plan: string;
    peso_kg: string;
    temperatura_c: string;
    fc_lpm: string;
    fr_rpm: string;
};

const controlClass = 'h-10 w-full min-w-0';

function labelPaciente(o: PacienteHistoriaOpcion): string {
    const p = o.propietario;
    if (!p) {
        return o.nombre;
    }
    const titular =
        p.razon_social?.trim() ||
        [p.nombres, p.apellidos].filter(Boolean).join(' ').trim();
    return titular ? `${o.nombre} (${titular})` : o.nombre;
}

function toDatetimeLocalValue(iso: string): string {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) {
        return '';
    }
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function defaultAtendidoLocal(): string {
    return toDatetimeLocalValue(new Date().toISOString());
}

const emptyForm: FormData = {
    paciente_id: '',
    cita_id: '',
    atendido_at: '',
    motivo: '',
    subjetivo: '',
    objetivo: '',
    analisis: '',
    plan: '',
    peso_kg: '',
    temperatura_c: '',
    fc_lpm: '',
    fr_rpm: '',
};

function numOrNull(s: string): number | null {
    const t = s.trim();
    if (t === '') {
        return null;
    }
    const n = Number.parseInt(t, 10);
    return Number.isNaN(n) ? null : n;
}

export function ConsultaFormModal({
    open,
    onOpenChange,
    consulta,
    pacientesOpciones,
    pacienteIdPrefillNueva = null,
    motivoPrefillNueva = null,
    citaIdPrefillNueva = null,
    puedeCerrarConsulta = false,
}: ConsultaFormModalProps) {
    const { t } = useTranslation(['historias-clinicas', 'common', 'offline']);
    const { refreshPending } = useOfflineSync();
    const isEdit = consulta !== null;
    const isCerrada = Boolean(consulta?.cerrada_at);
    const prefillIdRef = useRef<string | null>(null);

    const { data, setData, post, put, processing, errors, reset, clearErrors, transform, setDefaults } =
        useForm<FormData>(emptyForm);

    const [ownerTouched, setOwnerTouched] = useState(false);
    const [cierreProcessing, setCierreProcessing] = useState(false);
    const isEditRef = useRef(isEdit);
    isEditRef.current = isEdit;

    useEffect(() => {
        transform((raw) => {
            const next: Record<string, unknown> = {
                atendido_at: raw.atendido_at,
                motivo: raw.motivo.trim() === '' ? null : raw.motivo.trim(),
                subjetivo: raw.subjetivo.trim() === '' ? null : raw.subjetivo.trim(),
                objetivo: raw.objetivo.trim() === '' ? null : raw.objetivo.trim(),
                analisis: raw.analisis.trim() === '' ? null : raw.analisis.trim(),
                plan: raw.plan.trim() === '' ? null : raw.plan.trim(),
            };
            const peso = raw.peso_kg.trim();
            next.peso_kg = peso === '' ? null : Number.parseFloat(peso);
            const temp = raw.temperatura_c.trim();
            next.temperatura_c = temp === '' ? null : Number.parseFloat(temp);
            next.fc_lpm = numOrNull(raw.fc_lpm);
            next.fr_rpm = numOrNull(raw.fr_rpm);
            if (!isEditRef.current && raw.paciente_id) {
                next.paciente_id = raw.paciente_id;
            }
            if (!isEditRef.current && raw.cita_id) {
                next.cita_id = raw.cita_id;
            }

            return next;
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }
        if (consulta) {
            setData({
                paciente_id: consulta.historia_clinica.paciente?.id ?? '',
                cita_id: '',
                atendido_at: toDatetimeLocalValue(consulta.atendido_at),
                motivo: consulta.motivo ?? '',
                subjetivo: consulta.subjetivo ?? '',
                objetivo: consulta.objetivo ?? '',
                analisis: consulta.analisis ?? '',
                plan: consulta.plan ?? '',
                peso_kg:
                    consulta.peso_kg != null && consulta.peso_kg !== ''
                        ? String(consulta.peso_kg)
                        : '',
                temperatura_c:
                    consulta.temperatura_c != null && consulta.temperatura_c !== ''
                        ? String(consulta.temperatura_c)
                        : '',
                fc_lpm: consulta.fc_lpm != null ? String(consulta.fc_lpm) : '',
                fr_rpm: consulta.fr_rpm != null ? String(consulta.fr_rpm) : '',
            });
        } else {
            const pre = pacienteIdPrefillNueva ?? '';
            setData({
                ...emptyForm,
                paciente_id: pre,
                cita_id: citaIdPrefillNueva?.trim() ? citaIdPrefillNueva.trim() : '',
                motivo: motivoPrefillNueva?.trim() ? motivoPrefillNueva.trim() : '',
                atendido_at: defaultAtendidoLocal(),
            });
            if (pre && prefillIdRef.current !== pre) {
                prefillIdRef.current = pre;
            }
        }
        setDefaults();
        setOwnerTouched(false);
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, consulta?.id, pacienteIdPrefillNueva, motivoPrefillNueva, citaIdPrefillNueva]);

    const pacienteComboboxOptions = useMemo<readonly ComboboxOption[]>(
        () =>
            pacientesOpciones.map((o) => ({
                value: o.id,
                label: labelPaciente(o),
            })),
        [pacientesOpciones],
    );

    const canSubmit =
        !isCerrada &&
        data.atendido_at.trim().length > 0 &&
        !processing &&
        (isEdit || data.paciente_id.length > 0);

    const buildCreatePayload = (raw: FormData): Record<string, unknown> => {
        const next: Record<string, unknown> = {
            atendido_at: raw.atendido_at,
            motivo: raw.motivo.trim() === '' ? null : raw.motivo.trim(),
            subjetivo: raw.subjetivo.trim() === '' ? null : raw.subjetivo.trim(),
            objetivo: raw.objetivo.trim() === '' ? null : raw.objetivo.trim(),
            analisis: raw.analisis.trim() === '' ? null : raw.analisis.trim(),
            plan: raw.plan.trim() === '' ? null : raw.plan.trim(),
        };
        const peso = raw.peso_kg.trim();
        next.peso_kg = peso === '' ? null : Number.parseFloat(peso);
        const temp = raw.temperatura_c.trim();
        next.temperatura_c = temp === '' ? null : Number.parseFloat(temp);
        next.fc_lpm = numOrNull(raw.fc_lpm);
        next.fr_rpm = numOrNull(raw.fr_rpm);
        if (raw.paciente_id) {
            next.paciente_id = raw.paciente_id;
        }
        if (raw.cita_id) {
            next.cita_id = raw.cita_id;
        }

        return next;
    };

    const onSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (isCerrada) {
            return;
        }
        const onSuccess = () => {
            // En edición el backend redirige a Plan y seguimiento;
            // aquí solo cerramos el modal (Inertia navega con el redirect).
            reset();
            clearErrors();
            onOpenChange(false);
        };

        if (isEdit && consulta) {
            put(clinica.historiasClinicas.consultas.update.url(consulta.id), {
                preserveScroll: true,
                onSuccess,
            });

            return;
        }

        void (async () => {
            const queued = await enqueueIfOffline(
                'clinica.consulta.create',
                buildCreatePayload(data),
                {
                    refreshPending,
                    onSuccess,
                    title: t('offline:consulta.queued_title'),
                    description: t('offline:consulta.queued_body'),
                },
            );

            if (queued) {
                return;
            }

            post(clinica.historiasClinicas.consultas.store.url(), {
                preserveScroll: true,
                onSuccess,
            });
        })();
    };

    const pacienteNombreEdit =
        consulta && consulta.historia_clinica.paciente
            ? labelPaciente({
                  id: consulta.historia_clinica.paciente.id,
                  nombre: consulta.historia_clinica.paciente.nombre,
                  propietario: consulta.historia_clinica.paciente.propietario ?? {
                      id: '',
                      nombres: '',
                      apellidos: null,
                      razon_social: null,
                  },
              })
            : '';

    const fieldDisabled = isCerrada || processing;

    const applyDictationFields = (fields: ConsultaDictationFields) => {
        const mergeText = (current: string, incoming: string | null): string => {
            const next = (incoming ?? '').trim();
            if (next === '') {
                return current;
            }
            const cur = current.trim();
            if (cur === '') {
                return next;
            }
            return `${cur}\n${next}`;
        };

        setData((prev) => ({
            ...prev,
            motivo: mergeText(prev.motivo, fields.motivo),
            subjetivo: mergeText(prev.subjetivo, fields.subjetivo),
            objetivo: mergeText(prev.objetivo, fields.objetivo),
            analisis: mergeText(prev.analisis, fields.analisis),
            plan: mergeText(prev.plan, fields.plan),
            peso_kg: prev.peso_kg.trim() === '' && fields.peso_kg ? fields.peso_kg : prev.peso_kg,
            temperatura_c:
                prev.temperatura_c.trim() === '' && fields.temperatura_c
                    ? fields.temperatura_c
                    : prev.temperatura_c,
            fc_lpm: prev.fc_lpm.trim() === '' && fields.fc_lpm ? fields.fc_lpm : prev.fc_lpm,
            fr_rpm: prev.fr_rpm.trim() === '' && fields.fr_rpm ? fields.fr_rpm : prev.fr_rpm,
        }));
    };
    const cierreBusy = cierreProcessing || processing;

    const onCerrar = () => {
        if (!consulta || !puedeCerrarConsulta) {
            return;
        }
        setCierreProcessing(true);
        router.post(
            clinica.historiasClinicas.consultas.cerrar.url({ consulta: consulta.id }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setCierreProcessing(false),
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    const onReabrir = () => {
        if (!consulta || !puedeCerrarConsulta) {
            return;
        }
        setCierreProcessing(true);
        router.post(
            clinica.historiasClinicas.consultas.reabrir.url({ consulta: consulta.id }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setCierreProcessing(false),
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    return (
        <>
            <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('form.title_edit') : t('form.title_create')}
            description={t('description')}
            size="lg"
            onSubmit={onSubmit}
            footer={
                <>
                    {isEdit && consulta && puedeCerrarConsulta ? (
                        isCerrada ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="cursor-pointer"
                                disabled={cierreBusy}
                                onClick={onReabrir}
                            >
                                {cierreBusy && <Loader2 className="mr-2 size-4 animate-spin" aria-hidden />}
                                {t('form.reabrir')}
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                variant="secondary"
                                className="cursor-pointer"
                                disabled={cierreBusy}
                                onClick={onCerrar}
                            >
                                {cierreBusy && <Loader2 className="mr-2 size-4 animate-spin" aria-hidden />}
                                {t('form.cerrar')}
                            </Button>
                        )
                    ) : null}
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                        className="cursor-pointer"
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="submit"
                        disabled={!canSubmit}
                        className="cursor-pointer gap-2"
                    >
                        {processing && (
                            <Loader2 className="size-4 animate-spin" aria-hidden />
                        )}
                        {isEdit ? t('form.submit_edit') : t('form.submit_create')}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-5">
                {isEdit && !isCerrada ? (
                    <div className="flex items-start gap-2 rounded-md border border-amber-200/60 bg-amber-50/50 px-3 py-2 text-sm text-amber-900 dark:border-amber-800/30 dark:bg-amber-950/20 dark:text-amber-100">
                        <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden />
                        <div className="min-w-0">
                            <ConsultaEstadoBadge
                                cerradaAt={null}
                                atendidoAt={consulta?.atendido_at}
                            />
                            <p className="mt-1.5">{t('form.abierta_banner')}</p>
                        </div>
                    </div>
                ) : null}
                {isEdit && isCerrada ? (
                    <div className="flex items-center gap-2 rounded-md border border-emerald-200/60 bg-emerald-50/40 px-3 py-2 text-sm dark:border-emerald-800/30 dark:bg-emerald-950/20">
                        <ConsultaEstadoBadge cerradaAt={consulta.cerrada_at} />
                        <span className="text-muted-foreground">{t('form.cerrada_hint')}</span>
                    </div>
                ) : null}
                {!isCerrada ? (
                    <ConsultaDictationBar
                        disabled={fieldDisabled}
                        onFields={(fields) => applyDictationFields(fields)}
                    />
                ) : null}
                <FormSection
                    index={0}
                    title={t('form.section_main')}
                    columns={2}
                    className="gap-4"
                >
                    {!isEdit ? (
                        <FormField
                            id="hc-paciente"
                            label={t('form.paciente')}
                            required
                            error={errors.paciente_id}
                            className="min-w-0"
                        >
                            <Combobox
                                id="hc-paciente"
                                options={pacienteComboboxOptions}
                                value={data.paciente_id || null}
                                onChange={(v) => {
                                    setOwnerTouched(true);
                                    setData('paciente_id', v ?? '');
                                }}
                                placeholder={t('form.paciente_placeholder')}
                                searchPlaceholder={t('form.paciente_search')}
                                emptyMessage={t('form.paciente_empty')}
                                clearable={false}
                                className={`${controlClass} cursor-pointer`}
                                aria-invalid={ownerTouched && !data.paciente_id}
                                disabled={fieldDisabled}
                            />
                        </FormField>
                    ) : (
                        <FormField
                            id="hc-paciente-ro"
                            label={t('form.paciente')}
                            hint={t('form.paciente_locked_hint')}
                            className="min-w-0"
                        >
                            <Input
                                id="hc-paciente-ro"
                                readOnly
                                value={pacienteNombreEdit}
                                className={`${controlClass} cursor-not-allowed bg-muted/40`}
                            />
                        </FormField>
                    )}
                    <FormField
                        id="hc-atendido"
                        label={t('form.atendido_at')}
                        required
                        error={errors.atendido_at}
                        className="min-w-0"
                    >
                        <Input
                            id="hc-atendido"
                            key={isEdit ? `edit-${consulta?.id ?? ''}` : 'create'}
                            type="datetime-local"
                            value={data.atendido_at}
                            onChange={(e) => setData('atendido_at', e.target.value)}
                            className={controlClass}
                            disabled={fieldDisabled}
                        />
                    </FormField>
                    <FormField
                        id="hc-motivo"
                        label={t('form.motivo')}
                        error={errors.motivo}
                        className="min-w-0 sm:col-span-2"
                    >
                        <Textarea
                            id="hc-motivo"
                            value={data.motivo}
                            onChange={(e) => setData('motivo', e.target.value)}
                            placeholder={t('form.motivo_placeholder')}
                            rows={2}
                            className={`${controlClass} min-h-18 resize-y`}
                            disabled={fieldDisabled}
                        />
                    </FormField>
                    <FormField
                        id="hc-peso"
                        label={t('form.peso_kg')}
                        error={errors.peso_kg}
                        className="min-w-0"
                    >
                        <Input
                            id="hc-peso"
                            inputMode="decimal"
                            value={data.peso_kg}
                            onChange={(e) => setData('peso_kg', e.target.value)}
                            placeholder={t('form.peso_placeholder')}
                            className={controlClass}
                            disabled={fieldDisabled}
                        />
                    </FormField>
                    <FormField
                        id="hc-temp"
                        label={t('form.temperatura_c')}
                        error={errors.temperatura_c}
                        className="min-w-0"
                    >
                        <Input
                            id="hc-temp"
                            inputMode="decimal"
                            value={data.temperatura_c}
                            onChange={(e) => setData('temperatura_c', e.target.value)}
                            placeholder={t('form.vital_placeholder')}
                            className={controlClass}
                            disabled={fieldDisabled}
                        />
                    </FormField>
                    <FormField id="hc-fc" label={t('form.fc_lpm')} error={errors.fc_lpm} className="min-w-0">
                        <Input
                            id="hc-fc"
                            inputMode="numeric"
                            value={data.fc_lpm}
                            onChange={(e) => setData('fc_lpm', e.target.value)}
                            placeholder={t('form.vital_placeholder')}
                            className={controlClass}
                            disabled={fieldDisabled}
                        />
                    </FormField>
                    <FormField id="hc-fr" label={t('form.fr_rpm')} error={errors.fr_rpm} className="min-w-0">
                        <Input
                            id="hc-fr"
                            inputMode="numeric"
                            value={data.fr_rpm}
                            onChange={(e) => setData('fr_rpm', e.target.value)}
                            placeholder={t('form.vital_placeholder')}
                            className={controlClass}
                            disabled={fieldDisabled}
                        />
                    </FormField>
                    <FormField
                        id="hc-sub"
                        label={t('form.subjetivo')}
                        error={errors.subjetivo}
                        hint={t('form.soap_hint')}
                        className="min-w-0 sm:col-span-2"
                    >
                        <Textarea
                            id="hc-sub"
                            value={data.subjetivo}
                            onChange={(e) => setData('subjetivo', e.target.value)}
                            rows={3}
                            className={`${controlClass} min-h-22 resize-y`}
                            disabled={fieldDisabled}
                        />
                    </FormField>
                    <FormField
                        id="hc-obj"
                        label={t('form.objetivo')}
                        error={errors.objetivo}
                        className="min-w-0 sm:col-span-2"
                    >
                        <Textarea
                            id="hc-obj"
                            value={data.objetivo}
                            onChange={(e) => setData('objetivo', e.target.value)}
                            rows={3}
                            className={`${controlClass} min-h-22 resize-y`}
                            disabled={fieldDisabled}
                        />
                    </FormField>
                    <FormField
                        id="hc-ana"
                        label={t('form.analisis')}
                        error={errors.analisis}
                        className="min-w-0 sm:col-span-2"
                    >
                        <Textarea
                            id="hc-ana"
                            value={data.analisis}
                            onChange={(e) => setData('analisis', e.target.value)}
                            rows={3}
                            className={`${controlClass} min-h-22 resize-y`}
                            disabled={fieldDisabled}
                        />
                    </FormField>
                    <FormField
                        id="hc-plan"
                        label={t('form.plan')}
                        error={errors.plan}
                        className="min-w-0 sm:col-span-2"
                    >
                        <Textarea
                            id="hc-plan"
                            value={data.plan}
                            onChange={(e) => setData('plan', e.target.value)}
                            rows={3}
                            className={`${controlClass} min-h-22 resize-y`}
                            disabled={fieldDisabled}
                        />
                    </FormField>
                </FormSection>
            </div>
        </FormModal>
        </>
    );
}
