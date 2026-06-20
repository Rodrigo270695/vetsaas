import { useForm, usePage } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useMemo, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection, SedeFormField } from '@/components/forms';
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
import { useOfflineSync } from '@/hooks/use-offline-sync';
import { formatAtendidoInAppTimezone } from '../../historias-clinicas/format-atendido';
import type {
    ConsultaHospitalizacionOpcion,
    InternamientoRow,
    PacienteHospitalizacionOpcion,
    SedeHospitalizacionOpcion,
} from '../types';

const controlClass = 'h-10 w-full min-w-0';

const ESTADOS_CREAR = ['activo'] as const;

const ESTADOS_EDITAR = ['activo', 'alta', 'cancelado'] as const;

function toDatetimeLocalValue(d: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function parseIsoToDatetimeLocal(iso: string | null | undefined): string {
    if (!iso) {
        return '';
    }

    const d = new Date(iso);

    if (Number.isNaN(d.getTime())) {
        return toDatetimeLocalValue(new Date());
    }

    return toDatetimeLocalValue(d);
}

function displayPropietario(p: PacienteHospitalizacionOpcion['propietario']): string {
    if (!p) {
        return '';
    }

    if (p.razon_social) {
        return p.razon_social;
    }

    return [p.nombres, p.apellidos].filter(Boolean).join(' ');
}

type FormShape = {
    paciente_id: string;
    consulta_id: string;
    ingreso_at: string;
    alta_at: string;
    estado: string;
    motivo_ingreso: string;
    ubicacion: string;
    diagnostico_ingreso: string;
    notas: string;
    veterinario_id: string | null;
    sede_id: string | null;
};

function emptyForm(
    defaultVetId: string | null,
    sedes: readonly SedeHospitalizacionOpcion[],
): FormShape {
    return {
        paciente_id: '',
        consulta_id: '',
        ingreso_at: toDatetimeLocalValue(new Date()),
        alta_at: '',
        estado: 'activo',
        motivo_ingreso: '',
        ubicacion: '',
        diagnostico_ingreso: '',
        notas: '',
        veterinario_id: defaultVetId,
        sede_id: resolveDefaultSedeId(sedes),
    };
}

function fromInternamiento(row: InternamientoRow, defaultVetId: string | null): FormShape {
    return {
        paciente_id: row.paciente_id,
        consulta_id: row.consulta_id ?? '',
        ingreso_at: parseIsoToDatetimeLocal(row.ingreso_at),
        alta_at: parseIsoToDatetimeLocal(row.alta_at),
        estado: row.estado,
        motivo_ingreso: row.motivo_ingreso,
        ubicacion: row.ubicacion ?? '',
        diagnostico_ingreso: row.diagnostico_ingreso ?? '',
        notas: row.notas ?? '',
        veterinario_id: row.veterinario_id ?? defaultVetId,
        sede_id: row.sede_id,
    };
}

function formsEqual(a: FormShape, b: FormShape): boolean {
    return JSON.stringify(a) === JSON.stringify(b);
}

export type InternamientoFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    internamiento: InternamientoRow | null;
    pacientesOpciones: readonly PacienteHospitalizacionOpcion[];
    sedesOpciones: readonly SedeHospitalizacionOpcion[];
    consultasOpciones: readonly ConsultaHospitalizacionOpcion[];
};

export function InternamientoFormModal({
    open,
    onOpenChange,
    internamiento,
    pacientesOpciones,
    sedesOpciones,
    consultasOpciones,
}: InternamientoFormModalProps) {
    const { t } = useTranslation(['hospitalizacion', 'common', 'offline']);
    const { refreshPending } = useOfflineSync();
    const authUser = usePage().props.auth?.user as { id?: string } | undefined;
    const { locale: appLocale, timezone: appTz } = usePage().props;
    const defaultVetId = authUser?.id ?? null;

    const { data, setData, post, put, processing, errors, clearErrors, transform, setDefaults, reset } =
        useForm<FormShape>(emptyForm(defaultVetId, sedesOpciones));

    const isEdit = internamiento !== null;
    const lockPaciente = isEdit;
    const requiereAltaAt = data.estado === 'alta';

    const initialSnapshotRef = useRef<FormShape>(emptyForm(null, []));

    useEffect(() => {
        transform((raw) => {
            const r = raw;

            return {
                paciente_id: r.paciente_id,
                consulta_id: r.consulta_id.trim() === '' ? null : r.consulta_id.trim(),
                ingreso_at: r.ingreso_at,
                alta_at: r.alta_at.trim() === '' ? null : r.alta_at,
                estado: r.estado,
                motivo_ingreso: r.motivo_ingreso.trim(),
                ubicacion: r.ubicacion.trim() === '' ? null : r.ubicacion.trim(),
                diagnostico_ingreso: r.diagnostico_ingreso.trim() === '' ? null : r.diagnostico_ingreso.trim(),
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
        const next =
            internamiento !== null
                ? fromInternamiento(internamiento, defaultVetId)
                : emptyForm(defaultVetId, sedesOpciones);
        initialSnapshotRef.current = structuredClone(next);
        setData(next);
        setDefaults();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, internamiento?.id, defaultVetId, internamiento, sedesOpciones]);

    useEffect(() => {
        if (data.estado === 'alta') {
            if (data.alta_at.trim() === '') {
                setData('alta_at', toDatetimeLocalValue(new Date()));
            }

            return;
        }

        if (data.alta_at.trim() !== '') {
            setData('alta_at', '');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.estado]);

    const pacienteComboboxOptions = useMemo<ComboboxOption[]>(
        () =>
            pacientesOpciones.map((p) => ({
                value: p.id,
                label: `${p.nombre} · ${displayPropietario(p.propietario) || '—'}`,
            })),
        [pacientesOpciones],
    );

    const consultasBase = useMemo(() => {
        const list = [...consultasOpciones];

        if (
            internamiento?.consulta_id &&
            internamiento.consulta &&
            !list.some((c) => c.id === internamiento.consulta_id)
        ) {
            list.unshift({
                id: internamiento.consulta.id,
                atendido_at: internamiento.consulta.atendido_at,
                historia_clinica_id: internamiento.consulta.historia_clinica_id ?? '',
                historia_clinica: internamiento.consulta.historia_clinica ?? null,
            });
        }

        return list;
    }, [consultasOpciones, internamiento]);

    const consultasFiltradas = useMemo(() => {
        if (!data.paciente_id) {
            return [] as ConsultaHospitalizacionOpcion[];
        }

        return consultasBase.filter((c) => c.historia_clinica?.paciente_id === data.paciente_id);
    }, [consultasBase, data.paciente_id]);

    useEffect(() => {
        if (!data.consulta_id) {
            return;
        }

        const ok = consultasFiltradas.some((c) => c.id === data.consulta_id);

        if (!ok) {
            setData('consulta_id', '');
        }
    }, [consultasFiltradas, data.consulta_id, setData]);

    const labelConsulta = (c: ConsultaHospitalizacionOpcion): string => {
        const px = c.historia_clinica?.paciente?.nombre ?? '—';
        const fecha = formatAtendidoInAppTimezone(
            c.atendido_at,
            String(appLocale ?? 'es'),
            String(appTz ?? 'UTC'),
        );

        return `${fecha} · ${px}`;
    };

    const confirmDiscard = (): boolean => {
        if (formsEqual(initialSnapshotRef.current, data)) {
            return true;
        }

        return window.confirm(t('common:form.unsaved_changes'));
    };

    const handleClose = (next: boolean) => {
        if (!next) {
            if (!confirmDiscard()) {
                return;
            }

            reset();
            clearErrors();
        }

        onOpenChange(next);
    };

    const canSubmit =
        data.paciente_id.trim().length > 0 &&
        data.motivo_ingreso.trim().length > 0 &&
        data.ingreso_at.trim().length > 0 &&
        (!requiereAltaAt || data.alta_at.trim().length > 0) &&
        !processing;

    const buildCreatePayload = (raw: FormShape): Record<string, unknown> => ({
        paciente_id: raw.paciente_id,
        consulta_id: raw.consulta_id.trim() === '' ? null : raw.consulta_id.trim(),
        ingreso_at: raw.ingreso_at,
        alta_at: raw.alta_at.trim() === '' ? null : raw.alta_at,
        estado: raw.estado,
        motivo_ingreso: raw.motivo_ingreso.trim(),
        ubicacion: raw.ubicacion.trim() === '' ? null : raw.ubicacion.trim(),
        diagnostico_ingreso: raw.diagnostico_ingreso.trim() === '' ? null : raw.diagnostico_ingreso.trim(),
        notas: raw.notas.trim() === '' ? null : raw.notas.trim(),
        veterinario_id:
            raw.veterinario_id != null && raw.veterinario_id !== '' ? raw.veterinario_id : null,
        sede_id: raw.sede_id != null && raw.sede_id !== '' ? raw.sede_id : null,
    });

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const onSuccess = () => {
            reset();
            clearErrors();
            onOpenChange(false);
        };

        if (isEdit && internamiento) {
            put(`/clinica/hospitalizacion/${internamiento.id}`, {
                preserveScroll: true,
                onSuccess,
            });

            return;
        }

        void (async () => {
            const queued = await enqueueIfOffline(
                'clinica.internamiento.create',
                buildCreatePayload(data),
                {
                    refreshPending,
                    onSuccess,
                    title: t('offline:internamiento.queued_title'),
                    description: t('offline:internamiento.queued_body'),
                },
            );

            if (queued) {
                return;
            }

            post('/clinica/hospitalizacion', {
                preserveScroll: true,
                onSuccess,
            });
        })();
    };

    const estadoOptions = isEdit ? ESTADOS_EDITAR : ESTADOS_CREAR;

    const err = (key: string): string | undefined => {
        const v = (errors as Record<string, string | undefined>)[key];

        return typeof v === 'string' ? v : undefined;
    };

    return (
        <FormModal
            open={open}
            onOpenChange={handleClose}
            title={isEdit ? t('form.title_edit') : t('form.title_create')}
            description={isEdit ? undefined : t('description')}
            size="lg"
            onSubmit={onSubmit}
            footer={
                <div className="flex w-full flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => handleClose(false)}
                            disabled={processing}
                            className="cursor-pointer"
                        >
                            {t('common:actions.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            disabled={!canSubmit}
                            className="cursor-pointer gap-2 disabled:cursor-not-allowed"
                        >
                            {processing && (
                                <Loader2 className="size-4 animate-spin" aria-hidden="true" />
                            )}
                            {isEdit ? t('form.submit_edit') : t('form.submit_create')}
                        </Button>
                    </div>
                </div>
            }
        >
            <div className="flex flex-col gap-5">
                <FormSection
                    index={0}
                    title={t('form.section_general')}
                    description={t('form.section_general_hint')}
                    columns={2}
                >
                    <FormField
                        id="int-paciente"
                        label={t('form.paciente')}
                        required
                        error={err('paciente_id')}
                        className="sm:col-span-2"
                    >
                        <Combobox
                            id="int-paciente"
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
                        id="int-consulta"
                        label={t('form.consulta')}
                        hint={t('form.consulta_hint')}
                        error={err('consulta_id')}
                        className="sm:col-span-2"
                    >
                        <Select
                            value={data.consulta_id === '' ? '__none__' : data.consulta_id}
                            onValueChange={(v) => setData('consulta_id', v === '__none__' ? '' : v)}
                            disabled={processing || !data.paciente_id}
                        >
                            <SelectTrigger id="int-consulta" className={`${controlClass} cursor-pointer`}>
                                <SelectValue placeholder={t('form.consulta_placeholder')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none__">{t('form.consulta_placeholder')}</SelectItem>
                                {consultasFiltradas.map((c) => (
                                    <SelectItem key={c.id} value={c.id}>
                                        {labelConsulta(c)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField
                        id="int-ingreso"
                        label={t('form.ingreso_at')}
                        required
                        error={err('ingreso_at')}
                    >
                        <Input
                            id="int-ingreso"
                            type="datetime-local"
                            className={controlClass}
                            value={data.ingreso_at}
                            onChange={(e) => setData('ingreso_at', e.target.value)}
                            disabled={processing}
                            aria-invalid={Boolean(errors.ingreso_at)}
                        />
                    </FormField>

                    <FormField id="int-estado" label={t('form.estado')} required error={err('estado')}>
                        <Select
                            value={data.estado}
                            onValueChange={(v) => setData('estado', v)}
                            disabled={processing}
                        >
                            <SelectTrigger id="int-estado" className={`${controlClass} cursor-pointer`}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {estadoOptions.map((st) => (
                                    <SelectItem key={st} value={st}>
                                        {t(`estado.${st}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    {requiereAltaAt ? (
                        <FormField
                            id="int-alta"
                            label={t('form.alta_at')}
                            required
                            hint={t('form.alta_at_hint')}
                            error={err('alta_at')}
                            className="sm:col-span-2"
                        >
                            <Input
                                id="int-alta"
                                type="datetime-local"
                                className={controlClass}
                                value={data.alta_at}
                                onChange={(e) => setData('alta_at', e.target.value)}
                                disabled={processing}
                                aria-invalid={Boolean(errors.alta_at)}
                            />
                        </FormField>
                    ) : null}

                    <FormField
                        id="int-motivo"
                        label={t('form.motivo_ingreso')}
                        required
                        error={err('motivo_ingreso')}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="int-motivo"
                            className={controlClass}
                            value={data.motivo_ingreso}
                            onChange={(e) => setData('motivo_ingreso', e.target.value)}
                            placeholder={t('form.motivo_ingreso_placeholder')}
                            disabled={processing}
                            maxLength={500}
                            aria-invalid={Boolean(errors.motivo_ingreso)}
                        />
                    </FormField>

                    <FormField id="int-ubicacion" label={t('form.ubicacion')} error={err('ubicacion')}>
                        <Input
                            id="int-ubicacion"
                            className={controlClass}
                            value={data.ubicacion}
                            onChange={(e) => setData('ubicacion', e.target.value)}
                            placeholder={t('form.ubicacion_placeholder')}
                            disabled={processing}
                            maxLength={120}
                        />
                    </FormField>

                    <FormField
                        id="int-dx"
                        label={t('form.diagnostico_ingreso')}
                        error={err('diagnostico_ingreso')}
                        className="sm:col-span-2"
                    >
                        <Textarea
                            id="int-dx"
                            className="min-h-20 w-full min-w-0"
                            value={data.diagnostico_ingreso}
                            onChange={(e) => setData('diagnostico_ingreso', e.target.value)}
                            disabled={processing}
                        />
                    </FormField>

                    <FormField
                        id="int-notas"
                        label={t('form.notas')}
                        error={err('notas')}
                        className="sm:col-span-2"
                    >
                        <Textarea
                            id="int-notas"
                            className="min-h-20 w-full min-w-0"
                            value={data.notas}
                            onChange={(e) => setData('notas', e.target.value)}
                            disabled={processing}
                        />
                    </FormField>
                </FormSection>

                <FormSection
                    index={1}
                    title={t('form.section_context')}
                    description={t('form.section_context_hint')}
                    columns={1}
                >
                    <SedeFormField
                        id="int-sede"
                        label={t('form.sede')}
                        sedes={sedesOpciones}
                        value={data.sede_id}
                        onChange={(sedeId) => setData('sede_id', sedeId)}
                        error={err('sede_id')}
                        disabled={processing}
                        noneLabel={t('form.sede_placeholder')}
                        controlClassName={`${controlClass} cursor-pointer`}
                    />
                </FormSection>
            </div>
        </FormModal>
    );
}
