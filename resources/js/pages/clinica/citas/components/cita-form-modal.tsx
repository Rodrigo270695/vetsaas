import { useForm, usePage } from '@inertiajs/react';
import { CalendarDays, Clock, Loader2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, SedeFormField } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import type { ComboboxOption } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { resolveDefaultSedeId } from '@/lib/default-sede';
import { enqueueIfOffline } from '@/lib/offline/enqueue-if-offline';
import { cn } from '@/lib/utils';
import { useOfflineSync } from '@/hooks/use-offline-sync';
import clinica from '@/routes/clinica';
import type { CitaFormPrefill, CitaRow, PacienteCitaOpcion, SedeCitaOpcion } from '../types';
import { formatDateOnlyLabel } from '../../historias-clinicas/format-atendido';

const controlClass = 'h-10 w-full min-w-0';

const CITA_ESTADOS = ['programada', 'confirmada', 'completada', 'cancelada', 'no_asistio'] as const;

const QUICK_TIMES = [
    '08:00',
    '08:30',
    '09:00',
    '09:30',
    '10:00',
    '10:30',
    '11:00',
    '11:30',
    '12:00',
    '14:00',
    '15:00',
    '16:00',
    '17:00',
    '18:00',
] as const;

function toDatetimeLocalValue(d: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function isDatetimeLocalPast(value: string): boolean {
    if (!value) {
        return false;
    }

    const d = new Date(value);
    if (Number.isNaN(d.getTime())) {
        return false;
    }

    const now = new Date();
    const floorMin = (x: Date) =>
        new Date(x.getFullYear(), x.getMonth(), x.getDate(), x.getHours(), x.getMinutes()).getTime();

    // Comparar al minuto: el minuto actual sigue siendo válido.
    return floorMin(d) < floorMin(now);
}

function isHoraPastOnDate(fecha: string, hora: string): boolean {
    if (!fecha || !hora) {
        return false;
    }

    return isDatetimeLocalPast(`${fecha}T${hora}`);
}

function parseIsoToDatetimeLocal(iso: string): string {
    const d = new Date(iso);

    if (Number.isNaN(d.getTime())) {
        return toDatetimeLocalValue(new Date());
    }

    return toDatetimeLocalValue(d);
}

function displayPropietario(p: PacienteCitaOpcion['propietario']): string {
    if (!p) {
        return '';
    }

    if (p.razon_social) {
        return p.razon_social;
    }

    return [p.nombres, p.apellidos].filter(Boolean).join(' ');
}

export type CitaFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    cita: CitaRow | null;
    prefill?: CitaFormPrefill | null;
    pacientesOpciones: readonly PacienteCitaOpcion[];
    sedesOpciones: readonly SedeCitaOpcion[];
};

type FormShape = {
    paciente_id: string;
    inicio_at: string;
    duracion_minutos: string;
    estado: string;
    motivo: string;
    notas: string;
    veterinario_id: string | null;
    sede_id: string | null;
};

function emptyForm(
    defaultVetId: string | null,
    sedes: readonly SedeCitaOpcion[],
): FormShape {
    return {
        paciente_id: '',
        inicio_at: toDatetimeLocalValue(new Date()),
        duracion_minutos: '30',
        estado: 'programada',
        motivo: '',
        notas: '',
        veterinario_id: defaultVetId,
        sede_id: resolveDefaultSedeId(sedes),
    };
}

function fromCita(c: CitaRow, defaultVetId: string | null): FormShape {
    return {
        paciente_id: c.paciente_id,
        inicio_at: parseIsoToDatetimeLocal(c.inicio_at),
        duracion_minutos: String(c.duracion_minutos),
        estado: c.estado,
        motivo: c.motivo ?? '',
        notas: c.notas ?? '',
        veterinario_id: c.veterinario_id ?? defaultVetId,
        sede_id: c.sede_id,
    };
}

export function CitaFormModal({
    open,
    onOpenChange,
    cita,
    prefill,
    pacientesOpciones,
    sedesOpciones,
}: CitaFormModalProps) {
    const { t, i18n } = useTranslation(['citas', 'common', 'offline']);
    const { refreshPending } = useOfflineSync();
    const authUser = usePage().props.auth?.user as { id?: string } | undefined;
    const defaultVetId = authUser?.id ?? null;

    const { data, setData, post, put, processing, errors, clearErrors, transform, setDefaults, setError } =
        useForm<FormShape>(emptyForm(defaultVetId, sedesOpciones));

    const [hora, setHora] = useState('09:00');

    const isEdit = cita !== null;
    const lockPaciente = isEdit;
    const lockedDate = !isEdit && prefill?.fecha ? prefill.fecha : null;

    const lockedDateLabel = useMemo(() => {
        if (!lockedDate) {
            return null;
        }

        return formatDateOnlyLabel(lockedDate, i18n.language);
    }, [lockedDate, i18n.language]);

    useEffect(() => {
        transform((raw) => {
            const r = raw;
            const dm = r.duracion_minutos.trim();
            const dmVal = dm === '' ? NaN : Number.parseInt(dm, 10);

            return {
                paciente_id: r.paciente_id,
                inicio_at: r.inicio_at,
                duracion_minutos: Number.isNaN(dmVal) ? 30 : dmVal,
                estado: r.estado,
                motivo: r.motivo.trim() === '' ? null : r.motivo.trim(),
                notas: r.notas.trim() === '' ? null : r.notas.trim(),
                veterinario_id:
                    r.veterinario_id != null && r.veterinario_id !== '' ? r.veterinario_id : null,
                sede_id: r.sede_id != null && r.sede_id !== '' ? r.sede_id : null,
            };
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }

        clearErrors();

        if (cita !== null) {
            setData(fromCita(cita, defaultVetId));
            setHora(fromCita(cita, defaultVetId).inicio_at.slice(11, 16));
        } else if (prefill?.fecha) {
            const nextHora = prefill.hora ?? '09:00';
            setHora(nextHora);
            setData({
                ...emptyForm(defaultVetId, sedesOpciones),
                inicio_at: `${prefill.fecha}T${nextHora}`,
            });
        } else {
            setData(emptyForm(defaultVetId, sedesOpciones));
            setHora('09:00');
        }

        setDefaults();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, cita?.id, defaultVetId, cita, prefill?.fecha, prefill?.hora, sedesOpciones]);

    const applyHora = (nextHora: string) => {
        if (lockedDate && isHoraPastOnDate(lockedDate, nextHora)) {
            return;
        }

        setHora(nextHora);

        if (lockedDate) {
            setData('inicio_at', `${lockedDate}T${nextHora}`);
        }
    };

    const minDatetimeLocal = useMemo(() => toDatetimeLocalValue(new Date()), [open]);

    const requiresFutureInicio =
        !isEdit || data.estado === 'programada' || data.estado === 'confirmada';

    const pacienteComboboxOptions: ComboboxOption[] = pacientesOpciones.map((p) => ({
        value: p.id,
        label: `${p.nombre} · ${displayPropietario(p.propietario) || '—'}`,
    }));

    const buildCreatePayload = (raw: FormShape): Record<string, unknown> => {
        const dm = raw.duracion_minutos.trim();
        const dmVal = dm === '' ? NaN : Number.parseInt(dm, 10);

        return {
            paciente_id: raw.paciente_id,
            inicio_at: raw.inicio_at,
            duracion_minutos: Number.isNaN(dmVal) ? 30 : dmVal,
            motivo: raw.motivo.trim() === '' ? null : raw.motivo.trim(),
            notas: raw.notas.trim() === '' ? null : raw.notas.trim(),
            veterinario_id:
                raw.veterinario_id != null && raw.veterinario_id !== '' ? raw.veterinario_id : null,
            sede_id: raw.sede_id != null && raw.sede_id !== '' ? raw.sede_id : null,
        };
    };

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();

        if (requiresFutureInicio && isDatetimeLocalPast(data.inicio_at)) {
            setError('inicio_at', t('validation.inicio_pasado'));

            return;
        }

        if (isEdit && cita) {
            put(clinica.citas.update({ cita: cita.id }).url, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });

            return;
        }

        void (async () => {
            const queued = await enqueueIfOffline(
                'clinica.cita.create',
                buildCreatePayload(data),
                {
                    refreshPending,
                    onSuccess: () => onOpenChange(false),
                    title: t('offline:cita.queued_title'),
                    description: t('offline:cita.queued_body'),
                },
            );

            if (queued) {
                return;
            }

            post(clinica.citas.store().url, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        })();
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('form.title_edit') : lockedDate ? t('form.title_create_day') : t('form.title_create')}
            description={
                isEdit ? undefined : lockedDate ? t('form.description_create_day') : t('description')
            }
            onSubmit={onSubmit}
            footer={
                <>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={processing}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" disabled={processing} className="gap-2">
                        {processing && <Loader2 className="size-4 animate-spin" aria-hidden />}
                        {isEdit ? t('form.submit_edit') : t('form.submit_create')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4">
                <FormField
                    id="cf-paciente"
                    label={t('form.paciente')}
                    required
                    error={errors.paciente_id as string | undefined}
                >
                    <Combobox
                        id="cf-paciente"
                        options={pacienteComboboxOptions}
                        value={data.paciente_id === '' ? null : data.paciente_id}
                        onChange={(v) => setData('paciente_id', v ?? '')}
                        placeholder={t('form.paciente_placeholder')}
                        searchPlaceholder={t('form.paciente_search')}
                        emptyMessage={t('form.paciente_empty')}
                        disabled={lockPaciente || processing}
                        aria-invalid={Boolean(errors.paciente_id)}
                    />
                </FormField>

                <FormField
                    id="cf-inicio"
                    label={lockedDate ? t('form.hora') : t('form.inicio_at')}
                    required
                    error={errors.inicio_at as string | undefined}
                >
                    {lockedDate ? (
                        <div className="space-y-3">
                            <div className="flex items-center gap-2 rounded-xl border border-primary/25 bg-primary/[0.06] px-3 py-2.5">
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/15 text-primary">
                                    <CalendarDays className="size-4" />
                                </span>
                                <div>
                                    <p className="text-[0.65rem] font-medium uppercase tracking-wide text-muted-foreground">
                                        {t('form.fecha_seleccionada')}
                                    </p>
                                    <p className="text-sm font-semibold capitalize text-foreground">
                                        {lockedDateLabel}
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                    <Clock className="size-4" />
                                </span>
                                <Input
                                    id="cf-inicio"
                                    type="time"
                                    className={cn(controlClass, 'max-w-[9rem]')}
                                    value={hora}
                                    onChange={(e) => applyHora(e.target.value)}
                                    aria-invalid={Boolean(errors.inicio_at)}
                                    disabled={processing}
                                    min={
                                        lockedDate &&
                                        lockedDate === toDatetimeLocalValue(new Date()).slice(0, 10)
                                            ? toDatetimeLocalValue(new Date()).slice(11, 16)
                                            : undefined
                                    }
                                />
                            </div>

                            <div className="flex flex-wrap gap-1.5">
                                {QUICK_TIMES.map((slot) => {
                                    const past = Boolean(
                                        lockedDate && isHoraPastOnDate(lockedDate, slot),
                                    );

                                    return (
                                        <Button
                                            key={slot}
                                            type="button"
                                            size="sm"
                                            variant={hora === slot ? 'default' : 'outline'}
                                            className="h-7 cursor-pointer px-2.5 text-xs tabular-nums"
                                            onClick={() => applyHora(slot)}
                                            disabled={processing || past}
                                        >
                                            {slot}
                                        </Button>
                                    );
                                })}
                            </div>
                        </div>
                    ) : (
                        <Input
                            id="cf-inicio"
                            type="datetime-local"
                            className={controlClass}
                            value={data.inicio_at}
                            min={!isEdit || requiresFutureInicio ? minDatetimeLocal : undefined}
                            onChange={(e) => setData('inicio_at', e.target.value)}
                            aria-invalid={Boolean(errors.inicio_at)}
                            disabled={processing}
                        />
                    )}
                </FormField>

                <FormField
                    id="cf-duracion"
                    label={t('form.duracion_minutos')}
                    required
                    error={errors.duracion_minutos as string | undefined}
                >
                    <Input
                        id="cf-duracion"
                        type="number"
                        min={5}
                        max={480}
                        className={controlClass}
                        value={data.duracion_minutos}
                        onChange={(e) => setData('duracion_minutos', e.target.value)}
                        aria-invalid={Boolean(errors.duracion_minutos)}
                        disabled={processing}
                    />
                </FormField>
                <p className="text-xs text-muted-foreground">{t('form.duracion_hint')}</p>

                {isEdit ? (
                    <FormField
                        id="cf-estado"
                        label={t('form.estado')}
                        required
                        error={errors.estado as string | undefined}
                    >
                        <Select
                            value={data.estado}
                            onValueChange={(v) => setData('estado', v)}
                            disabled={processing}
                        >
                            <SelectTrigger id="cf-estado" className={controlClass}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {CITA_ESTADOS.map((st) => (
                                    <SelectItem key={st} value={st}>
                                        {t(`estado.${st}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                ) : null}

                <FormField id="cf-motivo" label={t('form.motivo')} error={errors.motivo as string | undefined}>
                    <Textarea
                        id="cf-motivo"
                        rows={2}
                        className="resize-y text-sm"
                        value={data.motivo}
                        onChange={(e) => setData('motivo', e.target.value)}
                        aria-invalid={Boolean(errors.motivo)}
                        disabled={processing}
                    />
                </FormField>

                <FormField id="cf-notas" label={t('form.notas')} error={errors.notas as string | undefined}>
                    <Textarea
                        id="cf-notas"
                        rows={2}
                        className="resize-y text-sm"
                        value={data.notas}
                        onChange={(e) => setData('notas', e.target.value)}
                        aria-invalid={Boolean(errors.notas)}
                        disabled={processing}
                    />
                </FormField>

                <SedeFormField
                    id="cf-sede"
                    label={t('form.sede')}
                    sedes={sedesOpciones}
                    value={data.sede_id}
                    onChange={(sedeId) => setData('sede_id', sedeId)}
                    error={errors.sede_id as string | undefined}
                    disabled={processing}
                    noneLabel={t('form.sede_placeholder')}
                    controlClassName={controlClass}
                />
            </div>
        </FormModal>
    );
}
