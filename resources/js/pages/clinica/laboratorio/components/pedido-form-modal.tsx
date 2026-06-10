import { useForm, usePage } from '@inertiajs/react';
import { CalendarDays, FlaskConical, Loader2, Plus, Save, Trash2, UserCheck } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useMemo, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';
import { FormField, FormModal, FormSection, SedeFormField } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    ConsultaLaboratorioOpcion,
    PacienteLaboratorioOpcion,
    PedidoLaboratorioLineaRow,
    PedidoLaboratorioRow,
    SedeLaboratorioOpcion,
    UsuarioLaboratorioOpcion,
} from '../types';

const controlClass = 'h-10 w-full min-w-0';

const ESTADOS_CREAR = ['borrador', 'solicitado'] as const;

const ESTADOS_EDITAR = ['borrador', 'solicitado', 'en_proceso', 'completado', 'cancelado'] as const;

function newRowKey(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return String(Date.now());
}

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

function displayPropietario(p: PacienteLaboratorioOpcion['propietario']): string {
    if (!p) {
        return '';
    }

    if (p.razon_social) {
        return p.razon_social;
    }

    return [p.nombres, p.apellidos].filter(Boolean).join(' ');
}

type LineFormRow = {
    rowKey: string;
    nombre_examen: string;
    indicaciones: string;
    resultado: string;
    resultado_at: string;
    resultado_archivo: File | null;
    clear_resultado_archivo: boolean;
    resultado_archivo_existente_nombre: string | null;
    resultado_archivo_url: string | null;
};

type FormShape = {
    paciente_id: string;
    consulta_id: string;
    solicitado_at: string;
    estado: string;
    laboratorio_destino: string;
    observaciones: string;
    veterinario_id: string | null;
    sede_id: string | null;
    lineas: LineFormRow[];
};

function emptyLine(): LineFormRow {
    return {
        rowKey: newRowKey(),
        nombre_examen: '',
        indicaciones: '',
        resultado: '',
        resultado_at: '',
        resultado_archivo: null,
        clear_resultado_archivo: false,
        resultado_archivo_existente_nombre: null,
        resultado_archivo_url: null,
    };
}

function emptyForm(
    defaultVetId: string | null,
    sedes: readonly SedeLaboratorioOpcion[],
): FormShape {
    return {
        paciente_id: '',
        consulta_id: '',
        solicitado_at: toDatetimeLocalValue(new Date()),
        estado: 'borrador',
        laboratorio_destino: '',
        observaciones: '',
        veterinario_id: defaultVetId,
        sede_id: resolveDefaultSedeId(sedes),
        lineas: [emptyLine()],
    };
}

function fromPedido(p: PedidoLaboratorioRow, defaultVetId: string | null): FormShape {
    const lineas = (p.lineas ?? []).map((ln: PedidoLaboratorioLineaRow) => ({
        rowKey: newRowKey(),
        nombre_examen: ln.nombre_examen,
        indicaciones: ln.indicaciones ?? '',
        resultado: ln.resultado ?? '',
        resultado_at:
            ln.resultado_at != null && ln.resultado_at !== ''
                ? parseIsoToDatetimeLocal(ln.resultado_at)
                : '',
        resultado_archivo: null,
        clear_resultado_archivo: false,
        resultado_archivo_existente_nombre: ln.resultado_archivo_original_name ?? null,
        resultado_archivo_url: ln.resultado_archivo_url ?? null,
    }));

    return {
        paciente_id: p.paciente_id,
        consulta_id: p.consulta_id ?? '',
        solicitado_at: parseIsoToDatetimeLocal(p.solicitado_at),
        estado: p.estado,
        laboratorio_destino: p.laboratorio_destino ?? '',
        observaciones: p.observaciones ?? '',
        veterinario_id: p.veterinario_id ?? defaultVetId,
        sede_id: p.sede_id,
        lineas: lineas.length > 0 ? lineas : [emptyLine()],
    };
}

function stripLineasForCompare(lineas: LineFormRow[]): Omit<LineFormRow, 'rowKey' | 'resultado_archivo'>[] {
    return lineas.map((ln) => ({
        nombre_examen: ln.nombre_examen,
        indicaciones: ln.indicaciones,
        resultado: ln.resultado,
        resultado_at: ln.resultado_at,
        clear_resultado_archivo: ln.clear_resultado_archivo,
        resultado_archivo_existente_nombre: ln.resultado_archivo_existente_nombre,
        resultado_archivo_url: ln.resultado_archivo_url,
    }));
}

function formsEqual(a: FormShape, b: FormShape): boolean {
    if (
        a.paciente_id !== b.paciente_id ||
        a.consulta_id !== b.consulta_id ||
        a.solicitado_at !== b.solicitado_at ||
        a.estado !== b.estado ||
        a.laboratorio_destino !== b.laboratorio_destino ||
        a.observaciones !== b.observaciones ||
        a.veterinario_id !== b.veterinario_id ||
        a.sede_id !== b.sede_id
    ) {
        return false;
    }

    return (
        JSON.stringify(stripLineasForCompare(a.lineas)) === JSON.stringify(stripLineasForCompare(b.lineas))
    );
}

export type PedidoFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    pedido: PedidoLaboratorioRow | null;
    pacientesOpciones: readonly PacienteLaboratorioOpcion[];
    usuariosOpciones: readonly UsuarioLaboratorioOpcion[];
    sedesOpciones: readonly SedeLaboratorioOpcion[];
    consultasOpciones: readonly ConsultaLaboratorioOpcion[];
};

export function PedidoFormModal({
    open,
    onOpenChange,
    pedido,
    pacientesOpciones,
    usuariosOpciones,
    sedesOpciones,
    consultasOpciones,
}: PedidoFormModalProps) {
    const { t } = useTranslation(['laboratorio', 'common']);
    const authUser = usePage().props.auth?.user as { id?: string } | undefined;
    const { locale: appLocale, timezone: appTz } = usePage().props;
    const defaultVetId = authUser?.id ?? null;

    const { data, setData, post, processing, errors, clearErrors, transform, setDefaults, reset } =
        useForm<FormShape>(emptyForm(defaultVetId, sedesOpciones));

    const isEdit = pedido !== null;
    const lockPaciente = isEdit;
    const isEditRef = useRef(isEdit);

    const initialSnapshotRef = useRef<FormShape>(emptyForm(null, []));

    useEffect(() => {
        isEditRef.current = isEdit;
    }, [isEdit]);

    useEffect(() => {
        transform((raw) => {
            const r = raw;
            const lineasOut = r.lineas.map((ln, idx) => {
                const rat = ln.resultado_at.trim();
                const row: Record<string, unknown> = {
                    nombre_examen: ln.nombre_examen.trim(),
                    indicaciones: ln.indicaciones.trim() === '' ? null : ln.indicaciones.trim(),
                    resultado: ln.resultado.trim() === '' ? null : ln.resultado.trim(),
                    resultado_at: rat === '' ? null : rat,
                    clear_resultado_archivo: ln.clear_resultado_archivo ? 1 : 0,
                    orden: idx,
                };

                if (ln.resultado_archivo instanceof File) {
                    row.resultado_archivo = ln.resultado_archivo;
                }

                return row;
            });

            const payload: Record<string, unknown> = {
                paciente_id: r.paciente_id,
                consulta_id: r.consulta_id.trim() === '' ? null : r.consulta_id.trim(),
                solicitado_at: r.solicitado_at,
                estado: r.estado,
                laboratorio_destino:
                    r.laboratorio_destino.trim() === '' ? null : r.laboratorio_destino.trim(),
                observaciones: r.observaciones.trim() === '' ? null : r.observaciones.trim(),
                veterinario_id:
                    r.veterinario_id != null && r.veterinario_id !== '' ? r.veterinario_id : null,
                sede_id: r.sede_id != null && r.sede_id !== '' ? r.sede_id : null,
                lineas: lineasOut,
            };

            // PHP no parsea multipart en PUT; con archivos usamos POST + _method=PUT.
            if (isEditRef.current) {
                payload._method = 'put';
            }

            return payload;
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }

        clearErrors();
        const next =
            pedido !== null
                ? fromPedido(pedido, defaultVetId)
                : emptyForm(defaultVetId, sedesOpciones);
        initialSnapshotRef.current = structuredClone(next);
        setData(next);
        setDefaults();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, pedido?.id, defaultVetId, pedido, sedesOpciones]);

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
            pedido?.consulta_id &&
            pedido.consulta &&
            !list.some((c) => c.id === pedido.consulta_id)
        ) {
            list.unshift({
                id: pedido.consulta.id,
                atendido_at: pedido.consulta.atendido_at,
                historia_clinica_id: pedido.consulta.historia_clinica_id ?? '',
                historia_clinica: pedido.consulta.historia_clinica ?? null,
            });
        }

        return list;
    }, [consultasOpciones, pedido]);

    const consultasFiltradas = useMemo(() => {
        if (!data.paciente_id) {
            return [] as ConsultaLaboratorioOpcion[];
        }

        return consultasBase.filter(
            (c) => c.historia_clinica?.paciente_id === data.paciente_id,
        );
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

    const labelConsulta = (c: ConsultaLaboratorioOpcion): string => {
        const px = c.historia_clinica?.paciente?.nombre ?? '—';
        const fecha = formatAtendidoInAppTimezone(
            c.atendido_at,
            String(appLocale ?? 'es'),
            String(appTz ?? 'UTC'),
        );

        return `${fecha} · ${px}`;
    };

    const updateLine = (index: number, patch: Partial<LineFormRow>) => {
        setData(
            'lineas',
            data.lineas.map((row, j) => (j === index ? { ...row, ...patch } : row)),
        );
    };

    const addLine = () => {
        setData('lineas', [...data.lineas, emptyLine()]);
    };

    const removeLine = (index: number) => {
        if (data.lineas.length <= 1) {
            return;
        }

        setData(
            'lineas',
            data.lineas.filter((_, j) => j !== index),
        );
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
        data.lineas.some((ln) => ln.nombre_examen.trim().length > 0) &&
        !processing;

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const onSuccess = () => {
            reset();
            clearErrors();
            onOpenChange(false);
        };

        const hasArchivos = data.lineas.some((ln) => ln.resultado_archivo instanceof File);

        const submitOpts = {
            preserveScroll: true,
            forceFormData: hasArchivos || isEdit,
            onSuccess,
        } as const;

        if (isEdit && pedido) {
            post(clinica.laboratorio.update({ pedido_laboratorio: pedido.id }).url, submitOpts);

            return;
        }

        post(clinica.laboratorio.store().url, submitOpts);
    };

    const err = (key: string): string | undefined => {
        const v = (errors as Record<string, string | undefined>)[key];

        return typeof v === 'string' ? v : undefined;
    };

    const estadoBadgeClass: Record<string, string> = {
        borrador: 'border-border/70 bg-muted/40 text-muted-foreground',
        solicitado: 'border-sky-400/40 bg-sky-400/10 text-sky-700 dark:text-sky-400',
        en_proceso: 'border-amber-400/40 bg-amber-400/10 text-amber-700 dark:text-amber-400',
        completado: 'border-emerald-400/40 bg-emerald-400/10 text-emerald-700 dark:text-emerald-400',
        cancelado: 'border-red-400/40 bg-red-400/10 text-red-700 dark:text-red-400',
    };

    const estadoOptions = isEdit ? ESTADOS_EDITAR : ESTADOS_CREAR;

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
                            {processing ? (
                                <Loader2 className="size-4 animate-spin" aria-hidden="true" />
                            ) : (
                                <Save className="size-4" strokeWidth={2} />
                            )}
                            {isEdit ? t('form.submit_edit') : t('form.submit_create')}
                        </Button>
                    </div>
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                {/* ── Sección 1: Datos generales ── */}
                <FormSection
                    index={0}
                    title={t('form.section_general')}
                    description={t('form.section_general_hint')}
                    icon={CalendarDays}
                    columns={2}
                >
                    {/* Paciente */}
                    <FormField
                        id="lab-paciente"
                        label={t('form.paciente')}
                        required
                        error={errors.paciente_id as string | undefined}
                        className="sm:col-span-2"
                    >
                        <Combobox
                            id="lab-paciente"
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

                    {/* Consulta */}
                    <FormField
                        id="lab-consulta"
                        label={t('form.consulta')}
                        hint={t('form.consulta_hint')}
                        error={errors.consulta_id as string | undefined}
                        className="sm:col-span-2"
                    >
                        <Select
                            value={data.consulta_id === '' ? '__none__' : data.consulta_id}
                            onValueChange={(v) => setData('consulta_id', v === '__none__' ? '' : v)}
                            disabled={processing || !data.paciente_id}
                        >
                            <SelectTrigger id="lab-consulta" className={controlClass}>
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

                    {/* Fecha */}
                    <FormField
                        id="lab-solicitado"
                        label={t('form.solicitado_at')}
                        required
                        error={errors.solicitado_at as string | undefined}
                    >
                        <Input
                            id="lab-solicitado"
                            type="datetime-local"
                            className={controlClass}
                            value={data.solicitado_at}
                            onChange={(e) => setData('solicitado_at', e.target.value)}
                            aria-invalid={Boolean(errors.solicitado_at)}
                            disabled={processing}
                        />
                    </FormField>

                    {/* Estado — chips visuales */}
                    <FormField
                        id="lab-estado"
                        label={t('form.estado')}
                        required
                        error={errors.estado as string | undefined}
                    >
                        <div className="flex flex-wrap gap-1.5 pt-0.5">
                            {estadoOptions.map((st) => (
                                <button
                                    key={st}
                                    type="button"
                                    disabled={processing}
                                    onClick={() => setData('estado', st)}
                                    className={cn(
                                        'rounded-full border px-3 py-1 text-xs font-medium transition-all duration-150 cursor-pointer',
                                        data.estado === st
                                            ? cn('ring-2 ring-offset-1 ring-primary', estadoBadgeClass[st] ?? '')
                                            : 'border-border/50 bg-transparent text-muted-foreground hover:bg-muted/50 hover:text-foreground',
                                    )}
                                >
                                    {t(`estado.${st}`)}
                                </button>
                            ))}
                        </div>
                    </FormField>

                    {/* Destino */}
                    <FormField
                        id="lab-destino"
                        label={t('form.laboratorio_destino')}
                        error={errors.laboratorio_destino as string | undefined}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="lab-destino"
                            className={controlClass}
                            value={data.laboratorio_destino}
                            onChange={(e) => setData('laboratorio_destino', e.target.value)}
                            placeholder={t('form.laboratorio_destino_placeholder')}
                            aria-invalid={Boolean(errors.laboratorio_destino)}
                            disabled={processing}
                        />
                    </FormField>

                    {/* Observaciones */}
                    <FormField
                        id="lab-obs"
                        label={t('form.observaciones')}
                        error={errors.observaciones as string | undefined}
                        className="sm:col-span-2"
                    >
                        <Textarea
                            id="lab-obs"
                            rows={2}
                            className="resize-none text-sm"
                            value={data.observaciones}
                            onChange={(e) => setData('observaciones', e.target.value)}
                            aria-invalid={Boolean(errors.observaciones)}
                            disabled={processing}
                        />
                    </FormField>
                </FormSection>

                {/* Separador visual */}
                <div className="h-px bg-border/50" />

                {/* ── Sección 2: Profesional y sede ── */}
                <FormSection
                    index={1}
                    title={t('form.section_context')}
                    description={t('form.section_context_hint')}
                    icon={UserCheck}
                    columns={2}
                >
                    <FormField
                        id="lab-vet"
                        label={t('form.veterinario')}
                        error={errors.veterinario_id as string | undefined}
                    >
                        <Select
                            value={data.veterinario_id ?? '__none__'}
                            onValueChange={(v) => setData('veterinario_id', v === '__none__' ? null : v)}
                            disabled={processing}
                        >
                            <SelectTrigger id="lab-vet" className={controlClass}>
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
                        id="lab-sede"
                        label={t('form.sede')}
                        sedes={sedesOpciones}
                        value={data.sede_id}
                        onChange={(sedeId) => setData('sede_id', sedeId)}
                        error={errors.sede_id as string | undefined}
                        disabled={processing}
                        noneLabel={t('form.sede_placeholder')}
                        controlClassName={controlClass}
                    />
                </FormSection>

                {/* Separador visual */}
                <div className="h-px bg-border/50" />

                {/* ── Sección 3: Exámenes ── */}
                <FormSection
                    index={2}
                    title={t('form.section_lineas')}
                    description={t('form.section_lineas_hint')}
                    icon={FlaskConical}
                    columns={1}
                >
                    {err('lineas') ? (
                        <p className="text-sm text-destructive" role="alert">
                            {err('lineas')}
                        </p>
                    ) : null}

                    <div className="flex flex-col gap-3">
                        {data.lineas.map((row, index) => (
                            <div
                                key={row.rowKey}
                                className="rounded-xl border border-border/60 bg-muted/20 p-4 transition-colors hover:bg-muted/30"
                            >
                                {/* Header del examen */}
                                <div className="mb-3 flex items-start gap-2.5">
                                    <span className="mt-8 flex size-6 shrink-0 items-center justify-center rounded-full bg-primary/15 text-[11px] font-bold text-primary">
                                        {index + 1}
                                    </span>
                                    <FormField
                                        id={`lab-lin-${index}-nom`}
                                        label={t('form.linea_examen')}
                                        required
                                        error={err(`lineas.${index}.nombre_examen`)}
                                        className="min-w-0 flex-1"
                                    >
                                        <Input
                                            id={`lab-lin-${index}-nom`}
                                            className={controlClass}
                                            value={row.nombre_examen}
                                            onChange={(e) =>
                                                updateLine(index, { nombre_examen: e.target.value })
                                            }
                                            placeholder={t('form.linea_examen_placeholder')}
                                            aria-invalid={Boolean(err(`lineas.${index}.nombre_examen`))}
                                            disabled={processing}
                                        />
                                    </FormField>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="mt-7 size-8 shrink-0 text-muted-foreground/60 hover:text-destructive"
                                        disabled={processing || data.lineas.length <= 1}
                                        onClick={() => removeLine(index)}
                                        aria-label={t('form.remove_line')}
                                    >
                                        <Trash2 className="size-3.5" strokeWidth={2.25} />
                                    </Button>
                                </div>

                                {/* Separador interno */}
                                <div className="mb-3 h-px bg-border/40" />

                                {/* Indicaciones */}
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <FormField
                                        id={`lab-lin-${index}-ind`}
                                        label={t('form.linea_indicaciones')}
                                        error={err(`lineas.${index}.indicaciones`)}
                                        className={isEdit ? '' : 'sm:col-span-2'}
                                    >
                                        <Textarea
                                            id={`lab-lin-${index}-ind`}
                                            rows={2}
                                            className="resize-none text-sm"
                                            value={row.indicaciones}
                                            onChange={(e) =>
                                                updateLine(index, { indicaciones: e.target.value })
                                            }
                                            aria-invalid={Boolean(err(`lineas.${index}.indicaciones`))}
                                            disabled={processing}
                                        />
                                    </FormField>

                                    {/* Campos de resultado solo en edición */}
                                    {isEdit && (
                                        <FormField
                                            id={`lab-lin-${index}-res`}
                                            label={t('form.linea_resultado')}
                                            error={err(`lineas.${index}.resultado`)}
                                        >
                                            <Textarea
                                                id={`lab-lin-${index}-res`}
                                                rows={2}
                                                className="resize-none text-sm"
                                                value={row.resultado}
                                                onChange={(e) =>
                                                    updateLine(index, { resultado: e.target.value })
                                                }
                                                aria-invalid={Boolean(err(`lineas.${index}.resultado`))}
                                                disabled={processing}
                                            />
                                        </FormField>
                                    )}
                                </div>

                                {isEdit && (
                                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                        <FormField
                                            id={`lab-lin-${index}-rat`}
                                            label={t('form.linea_resultado_at')}
                                            error={err(`lineas.${index}.resultado_at`)}
                                        >
                                            <Input
                                                id={`lab-lin-${index}-rat`}
                                                type="datetime-local"
                                                className={controlClass}
                                                value={row.resultado_at}
                                                onChange={(e) =>
                                                    updateLine(index, { resultado_at: e.target.value })
                                                }
                                                aria-invalid={Boolean(err(`lineas.${index}.resultado_at`))}
                                                disabled={processing}
                                            />
                                        </FormField>

                                        <FormField
                                            id={`lab-lin-${index}-arch`}
                                            label={t('form.linea_resultado_archivo')}
                                            error={err(`lineas.${index}.resultado_archivo`)}
                                        >
                                            <div className="flex flex-col gap-2">
                                                {row.resultado_archivo_url &&
                                                row.resultado_archivo_existente_nombre &&
                                                !row.clear_resultado_archivo &&
                                                !row.resultado_archivo ? (
                                                    <div className="flex flex-wrap items-center gap-2 rounded-lg border border-border/60 bg-muted/30 px-3 py-2 text-sm">
                                                        <span className="text-muted-foreground">
                                                            {t('form.linea_resultado_archivo_actual')}:
                                                        </span>
                                                        <a
                                                            href={row.resultado_archivo_url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="font-medium text-primary underline-offset-4 hover:underline"
                                                        >
                                                            {row.resultado_archivo_existente_nombre}
                                                        </a>
                                                    </div>
                                                ) : null}

                                                <Input
                                                    id={`lab-lin-${index}-arch`}
                                                    type="file"
                                                    accept=".pdf,.png,.jpg,.jpeg,.webp,application/pdf,image/png,image/jpeg,image/webp"
                                                    disabled={processing}
                                                    className="h-10 cursor-pointer pt-1.5 file:mr-3 file:cursor-pointer"
                                                    onChange={(e) => {
                                                        const f = e.target.files?.[0] ?? null;
                                                        updateLine(index, {
                                                            resultado_archivo: f,
                                                            clear_resultado_archivo: false,
                                                        });
                                                    }}
                                                />
                                                <p className="text-xs text-muted-foreground">
                                                    {t('form.linea_resultado_archivo_help')}
                                                </p>

                                                {row.resultado_archivo_url &&
                                                row.resultado_archivo_existente_nombre ? (
                                                    <label
                                                        htmlFor={`lab-lin-${index}-clear-arch`}
                                                        className="flex cursor-pointer items-center gap-2 text-sm text-muted-foreground"
                                                    >
                                                        <Checkbox
                                                            id={`lab-lin-${index}-clear-arch`}
                                                            checked={row.clear_resultado_archivo}
                                                            disabled={processing || row.resultado_archivo !== null}
                                                            onCheckedChange={(checked) =>
                                                                updateLine(index, {
                                                                    clear_resultado_archivo: checked === true,
                                                                    resultado_archivo: null,
                                                                })
                                                            }
                                                        />
                                                        <span>{t('form.linea_resultado_archivo_quitar')}</span>
                                                    </label>
                                                ) : null}
                                            </div>
                                        </FormField>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>

                    {/* Botón añadir examen */}
                    <button
                        type="button"
                        onClick={addLine}
                        disabled={processing}
                        className="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-border/60 py-3 text-sm font-medium text-muted-foreground transition-colors hover:border-primary/40 hover:bg-primary/5 hover:text-primary disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Plus className="size-4" strokeWidth={2.5} />
                        {t('actions.add_line')}
                    </button>
                </FormSection>
            </div>
        </FormModal>
    );
}
