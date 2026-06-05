import { useForm, usePage } from '@inertiajs/react';
import { Loader2, Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useMemo, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection } from '@/components/forms';
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

function emptyForm(defaultVetId: string | null): FormShape {
    return {
        paciente_id: '',
        consulta_id: '',
        solicitado_at: toDatetimeLocalValue(new Date()),
        estado: 'borrador',
        laboratorio_destino: '',
        observaciones: '',
        veterinario_id: defaultVetId,
        sede_id: null,
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
        useForm<FormShape>(emptyForm(defaultVetId));

    const isEdit = pedido !== null;
    const lockPaciente = isEdit;
    const isEditRef = useRef(isEdit);

    const initialSnapshotRef = useRef<FormShape>(emptyForm(null));

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
        const next = pedido !== null ? fromPedido(pedido, defaultVetId) : emptyForm(defaultVetId);
        initialSnapshotRef.current = structuredClone(next);
        setData(next);
        setDefaults();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, pedido?.id, defaultVetId, pedido]);

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

                    <FormField
                        id="lab-estado"
                        label={t('form.estado')}
                        required
                        error={errors.estado as string | undefined}
                    >
                        <Select
                            value={data.estado}
                            onValueChange={(v) => setData('estado', v)}
                            disabled={processing}
                        >
                            <SelectTrigger id="lab-estado" className={controlClass}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {(isEdit ? ESTADOS_EDITAR : ESTADOS_CREAR).map((st) => (
                                    <SelectItem key={st} value={st}>
                                        {t(`estado.${st}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

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

                <FormSection
                    index={1}
                    title={t('form.section_context')}
                    description={t('form.section_context_hint')}
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

                    <FormField id="lab-sede" label={t('form.sede')} error={errors.sede_id as string | undefined}>
                        <Select
                            value={data.sede_id ?? '__none__'}
                            onValueChange={(v) => setData('sede_id', v === '__none__' ? null : v)}
                            disabled={processing}
                        >
                            <SelectTrigger id="lab-sede" className={controlClass}>
                                <SelectValue placeholder={t('form.sede_placeholder')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none__">{t('form.sede_placeholder')}</SelectItem>
                                {sedesOpciones.map((s) => (
                                    <SelectItem key={s.id} value={s.id}>
                                        {s.codigo ? `${s.nombre} (${s.codigo})` : s.nombre}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                </FormSection>

                <FormSection
                    index={2}
                    title={t('form.section_lineas')}
                    description={t('form.section_lineas_hint')}
                    columns={1}
                >
                    <div className="flex justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="cursor-pointer gap-1.5"
                            onClick={addLine}
                            disabled={processing}
                        >
                            <Plus className="size-3.5" strokeWidth={2.5} />
                            {t('actions.add_line')}
                        </Button>
                    </div>

                    {err('lineas') ? (
                        <p className="text-sm text-destructive" role="alert">
                            {err('lineas')}
                        </p>
                    ) : null}

                    <div className="flex flex-col gap-4">
                        {data.lineas.map((row, index) => (
                            <div
                                key={row.rowKey}
                                className="rounded-lg border border-border/60 bg-card/30 p-4 sm:p-5"
                            >
                                <div className="mb-4 flex items-center justify-between gap-3 border-b border-border/50 pb-3">
                                    <span className="text-sm font-semibold text-foreground">
                                        {t('form.section_lineas')} · #{index + 1}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-9 shrink-0 text-muted-foreground hover:text-destructive"
                                        disabled={processing || data.lineas.length <= 1}
                                        onClick={() => removeLine(index)}
                                        aria-label={t('form.remove_line')}
                                    >
                                        <Trash2 className="size-4" strokeWidth={2.25} />
                                    </Button>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2 sm:gap-x-5">
                                    <FormField
                                        id={`lab-lin-${index}-nom`}
                                        label={t('form.linea_examen')}
                                        required
                                        error={err(`lineas.${index}.nombre_examen`)}
                                        className="sm:col-span-2"
                                    >
                                        <Input
                                            id={`lab-lin-${index}-nom`}
                                            className={controlClass}
                                            value={row.nombre_examen}
                                            onChange={(e) =>
                                                updateLine(index, { nombre_examen: e.target.value })
                                            }
                                            aria-invalid={Boolean(err(`lineas.${index}.nombre_examen`))}
                                            disabled={processing}
                                        />
                                    </FormField>

                                    <FormField
                                        id={`lab-lin-${index}-ind`}
                                        label={t('form.linea_indicaciones')}
                                        error={err(`lineas.${index}.indicaciones`)}
                                        className="sm:col-span-2"
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

                                    <FormField
                                        id={`lab-lin-${index}-res`}
                                        label={t('form.linea_resultado')}
                                        error={err(`lineas.${index}.resultado`)}
                                        className="sm:col-span-2"
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
                                        className="sm:col-span-2"
                                    >
                                        <div className="flex flex-col gap-3">
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
                            </div>
                        ))}
                    </div>
                </FormSection>
            </div>
        </FormModal>
    );
}
