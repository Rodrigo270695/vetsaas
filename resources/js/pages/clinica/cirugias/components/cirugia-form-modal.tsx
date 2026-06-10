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
import clinica from '@/routes/clinica';
import { formatAtendidoInAppTimezone } from '../../historias-clinicas/format-atendido';
import type {
    CirugiaRow,
    ConsultaCirugiaOpcion,
    PacienteCirugiaOpcion,
    SedeCirugiaOpcion,
    UsuarioCirugiaOpcion,
} from '../types';

const controlClass = 'h-10 w-full min-w-0';

const ESTADOS_CREAR = ['borrador', 'programada'] as const;

const ESTADOS_EDITAR = [
    'borrador',
    'programada',
    'en_proceso',
    'completada',
    'cancelada',
] as const;

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

function displayPropietario(p: PacienteCirugiaOpcion['propietario']): string {
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
    programada_at: string;
    estado: string;
    nombre_procedimiento: string;
    tipo_anestesia: string;
    observaciones: string;
    veterinario_id: string | null;
    sede_id: string | null;
};

function emptyForm(
    defaultVetId: string | null,
    sedes: readonly SedeCirugiaOpcion[],
): FormShape {
    return {
        paciente_id: '',
        consulta_id: '',
        programada_at: toDatetimeLocalValue(new Date()),
        estado: 'borrador',
        nombre_procedimiento: '',
        tipo_anestesia: '',
        observaciones: '',
        veterinario_id: defaultVetId,
        sede_id: resolveDefaultSedeId(sedes),
    };
}

function fromCirugia(c: CirugiaRow, defaultVetId: string | null): FormShape {
    return {
        paciente_id: c.paciente_id,
        consulta_id: c.consulta_id ?? '',
        programada_at: parseIsoToDatetimeLocal(c.programada_at),
        estado: c.estado,
        nombre_procedimiento: c.nombre_procedimiento,
        tipo_anestesia: c.tipo_anestesia ?? '',
        observaciones: c.observaciones ?? '',
        veterinario_id: c.veterinario_id ?? defaultVetId,
        sede_id: c.sede_id,
    };
}

function formsEqual(a: FormShape, b: FormShape): boolean {
    return JSON.stringify(a) === JSON.stringify(b);
}

export type CirugiaFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    cirugia: CirugiaRow | null;
    pacientesOpciones: readonly PacienteCirugiaOpcion[];
    usuariosOpciones: readonly UsuarioCirugiaOpcion[];
    sedesOpciones: readonly SedeCirugiaOpcion[];
    consultasOpciones: readonly ConsultaCirugiaOpcion[];
};

export function CirugiaFormModal({
    open,
    onOpenChange,
    cirugia,
    pacientesOpciones,
    usuariosOpciones,
    sedesOpciones,
    consultasOpciones,
}: CirugiaFormModalProps) {
    const { t } = useTranslation(['cirugia', 'common']);
    const authUser = usePage().props.auth?.user as { id?: string } | undefined;
    const { locale: appLocale, timezone: appTz } = usePage().props;
    const defaultVetId = authUser?.id ?? null;

    const { data, setData, post, put, processing, errors, clearErrors, transform, setDefaults, reset } =
        useForm<FormShape>(emptyForm(defaultVetId, sedesOpciones));

    const isEdit = cirugia !== null;
    const lockPaciente = isEdit;

    const initialSnapshotRef = useRef<FormShape>(emptyForm(null, []));

    useEffect(() => {
        transform((raw) => {
            const r = raw;

            return {
                paciente_id: r.paciente_id,
                consulta_id: r.consulta_id.trim() === '' ? null : r.consulta_id.trim(),
                programada_at: r.programada_at,
                estado: r.estado,
                nombre_procedimiento: r.nombre_procedimiento.trim(),
                tipo_anestesia: r.tipo_anestesia.trim() === '' ? null : r.tipo_anestesia.trim(),
                observaciones: r.observaciones.trim() === '' ? null : r.observaciones.trim(),
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
            cirugia !== null
                ? fromCirugia(cirugia, defaultVetId)
                : emptyForm(defaultVetId, sedesOpciones);
        initialSnapshotRef.current = structuredClone(next);
        setData(next);
        setDefaults();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, cirugia?.id, defaultVetId, cirugia, sedesOpciones]);

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
            cirugia?.consulta_id &&
            cirugia.consulta &&
            !list.some((c) => c.id === cirugia.consulta_id)
        ) {
            list.unshift({
                id: cirugia.consulta.id,
                atendido_at: cirugia.consulta.atendido_at,
                historia_clinica_id: cirugia.consulta.historia_clinica_id ?? '',
                historia_clinica: cirugia.consulta.historia_clinica ?? null,
            });
        }

        return list;
    }, [consultasOpciones, cirugia]);

    const consultasFiltradas = useMemo(() => {
        if (!data.paciente_id) {
            return [] as ConsultaCirugiaOpcion[];
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

    const labelConsulta = (c: ConsultaCirugiaOpcion): string => {
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
        data.nombre_procedimiento.trim().length > 0 &&
        data.programada_at.trim().length > 0 &&
        !processing;

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const onSuccess = () => {
            reset();
            clearErrors();
            onOpenChange(false);
        };

        if (isEdit && cirugia) {
            put(clinica.cirugias.update({ cirugia: cirugia.id }).url, {
                preserveScroll: true,
                onSuccess,
            });

            return;
        }

        post(clinica.cirugias.store().url, {
            preserveScroll: true,
            onSuccess,
        });
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
                        id="cirugia-paciente"
                        label={t('form.paciente')}
                        required
                        error={err('paciente_id')}
                        className="sm:col-span-2"
                    >
                        <Combobox
                            id="cirugia-paciente"
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
                        id="cirugia-consulta"
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
                            <SelectTrigger id="cirugia-consulta" className={`${controlClass} cursor-pointer`}>
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
                        id="cirugia-programada"
                        label={t('form.programada_at')}
                        required
                        error={err('programada_at')}
                    >
                        <Input
                            id="cirugia-programada"
                            type="datetime-local"
                            className={controlClass}
                            value={data.programada_at}
                            onChange={(e) => setData('programada_at', e.target.value)}
                            disabled={processing}
                            aria-invalid={Boolean(errors.programada_at)}
                        />
                    </FormField>

                    <FormField id="cirugia-estado" label={t('form.estado')} required error={err('estado')}>
                        <Select
                            value={data.estado}
                            onValueChange={(v) => setData('estado', v)}
                            disabled={processing}
                        >
                            <SelectTrigger id="cirugia-estado" className={`${controlClass} cursor-pointer`}>
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

                    <FormField
                        id="cirugia-procedimiento"
                        label={t('form.nombre_procedimiento')}
                        required
                        error={err('nombre_procedimiento')}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="cirugia-procedimiento"
                            className={controlClass}
                            value={data.nombre_procedimiento}
                            onChange={(e) => setData('nombre_procedimiento', e.target.value)}
                            placeholder={t('form.nombre_procedimiento_placeholder')}
                            disabled={processing}
                            maxLength={500}
                            aria-invalid={Boolean(errors.nombre_procedimiento)}
                        />
                    </FormField>

                    <FormField
                        id="cirugia-anestesia"
                        label={t('form.tipo_anestesia')}
                        error={err('tipo_anestesia')}
                    >
                        <Input
                            id="cirugia-anestesia"
                            className={controlClass}
                            value={data.tipo_anestesia}
                            onChange={(e) => setData('tipo_anestesia', e.target.value)}
                            placeholder={t('form.tipo_anestesia_placeholder')}
                            disabled={processing}
                            maxLength={120}
                        />
                    </FormField>

                    <FormField
                        id="cirugia-obs"
                        label={t('form.observaciones')}
                        error={err('observaciones')}
                        className="sm:col-span-2"
                    >
                        <Textarea
                            id="cirugia-obs"
                            className="min-h-24 w-full min-w-0"
                            value={data.observaciones}
                            onChange={(e) => setData('observaciones', e.target.value)}
                            disabled={processing}
                        />
                    </FormField>
                </FormSection>

                <FormSection
                    index={1}
                    title={t('form.section_context')}
                    description={t('form.section_context_hint')}
                    columns={2}
                >
                    <FormField id="cirugia-vet" label={t('form.veterinario')} error={err('veterinario_id')}>
                        <Select
                            value={data.veterinario_id ?? '__none__'}
                            onValueChange={(v) =>
                                setData('veterinario_id', v === '__none__' ? null : v)
                            }
                            disabled={processing}
                        >
                            <SelectTrigger id="cirugia-vet" className={`${controlClass} cursor-pointer`}>
                                <SelectValue placeholder={t('form.veterinario_placeholder')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none__">{t('form.veterinario_placeholder')}</SelectItem>
                                {usuariosOpciones.map((u) => (
                                    <SelectItem key={u.id} value={u.id}>
                                        {u.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <SedeFormField
                        id="cirugia-sede"
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
