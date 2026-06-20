import { useForm, usePage } from '@inertiajs/react';
import { Loader2, Plus, Printer, Trash2 } from 'lucide-react';
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
import { usePermission } from '@/hooks/use-permission';
import { resolveDefaultSedeId } from '@/lib/default-sede';
import { enqueueIfOffline } from '@/lib/offline/enqueue-if-offline';
import { useOfflineSync } from '@/hooks/use-offline-sync';
import clinica from '@/routes/clinica';
import { formatAtendidoInAppTimezone } from '../../historias-clinicas/format-atendido';
import type {
    ConsultaRecetaOpcion,
    PacienteRecetaOpcion,
    RecetaLineaRow,
    RecetaRow,
    SedeRecetaOpcion,
} from '../types';
import { RecetaProductoPicker } from './receta-producto-picker';
import type { RecetaProductoOption } from './receta-producto-picker';

const controlClass = 'h-10 w-full min-w-0';

const ESTADOS_CREAR = ['borrador', 'emitida'] as const;

const ESTADOS_EDITAR = ['borrador', 'emitida', 'anulada'] as const;

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

function displayPropietario(p: PacienteRecetaOpcion['propietario']): string {
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
    producto_id: string | null;
    nombre_medicamento: string;
    posologia: string;
    duracion_dias: string;
    instrucciones: string;
};

type FormShape = {
    paciente_id: string;
    consulta_id: string;
    emitida_at: string;
    estado: string;
    observaciones: string;
    veterinario_id: string | null;
    sede_id: string | null;
    lineas: LineFormRow[];
};

function emptyLine(): LineFormRow {
    return {
        rowKey: newRowKey(),
        producto_id: null,
        nombre_medicamento: '',
        posologia: '',
        duracion_dias: '',
        instrucciones: '',
    };
}

function emptyForm(
    defaultVetId: string | null,
    sedes: readonly SedeRecetaOpcion[],
): FormShape {
    return {
        paciente_id: '',
        consulta_id: '',
        emitida_at: toDatetimeLocalValue(new Date()),
        estado: 'borrador',
        observaciones: '',
        veterinario_id: defaultVetId,
        sede_id: resolveDefaultSedeId(sedes),
        lineas: [emptyLine()],
    };
}

function fromReceta(r: RecetaRow, defaultVetId: string | null): FormShape {
    const lineas = (r.lineas ?? []).map((ln: RecetaLineaRow) => ({
        rowKey: newRowKey(),
        producto_id: ln.producto_id,
        nombre_medicamento: ln.nombre_medicamento,
        posologia: ln.posologia ?? '',
        duracion_dias: ln.duracion_dias != null ? String(ln.duracion_dias) : '',
        instrucciones: ln.instrucciones ?? '',
    }));

    return {
        paciente_id: r.paciente_id,
        consulta_id: r.consulta_id ?? '',
        emitida_at: parseIsoToDatetimeLocal(r.emitida_at),
        estado: r.estado,
        observaciones: r.observaciones ?? '',
        veterinario_id: r.veterinario_id ?? defaultVetId,
        sede_id: r.sede_id,
        lineas: lineas.length > 0 ? lineas : [emptyLine()],
    };
}

function stripLineasForCompare(lineas: LineFormRow[]): Omit<LineFormRow, 'rowKey'>[] {
    return lineas.map((ln) => ({
        producto_id: ln.producto_id,
        nombre_medicamento: ln.nombre_medicamento,
        posologia: ln.posologia,
        duracion_dias: ln.duracion_dias,
        instrucciones: ln.instrucciones,
    }));
}

function formsEqual(a: FormShape, b: FormShape): boolean {
    if (
        a.paciente_id !== b.paciente_id ||
        a.consulta_id !== b.consulta_id ||
        a.emitida_at !== b.emitida_at ||
        a.estado !== b.estado ||
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

export type RecetaFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    receta: RecetaRow | null;
    pacientesOpciones: readonly PacienteRecetaOpcion[];
    sedesOpciones: readonly SedeRecetaOpcion[];
    consultasOpciones: readonly ConsultaRecetaOpcion[];
};

/**
 * Modal crear/editar receta (mismo patrón que `sede-form-modal`: FormModal + FormSection, rejilla 2 columnas).
 */
export function RecetaFormModal({
    open,
    onOpenChange,
    receta,
    pacientesOpciones,
    sedesOpciones,
    consultasOpciones,
}: RecetaFormModalProps) {
    const { t } = useTranslation(['recetas', 'common', 'offline']);
    const { refreshPending } = useOfflineSync();
    const { can } = usePermission();
    const canPrintPdf = can('recetas.view');
    const authUser = usePage().props.auth?.user as { id?: string } | undefined;
    const { locale: appLocale, timezone: appTz } = usePage().props;
    const defaultVetId = authUser?.id ?? null;

    const { data, setData, post, put, processing, errors, clearErrors, transform, setDefaults, reset } =
        useForm<FormShape>(emptyForm(defaultVetId, sedesOpciones));

    const isEdit = receta !== null;
    const lockPaciente = isEdit;

    const initialSnapshotRef = useRef<FormShape>(emptyForm(null, []));

    useEffect(() => {
        transform((raw) => {
            const r = raw;
            const lineasOut = r.lineas.map((ln, idx) => {
                const dd = ln.duracion_dias.trim();
                const ddVal = dd === '' ? null : Number.parseInt(dd, 10);

                return {
                    producto_id: ln.producto_id && ln.producto_id !== '' ? ln.producto_id : null,
                    nombre_medicamento: ln.nombre_medicamento.trim(),
                    posologia: ln.posologia.trim() === '' ? null : ln.posologia.trim(),
                    duracion_dias: dd === '' || ddVal === null || Number.isNaN(ddVal) ? null : ddVal,
                    instrucciones: ln.instrucciones.trim() === '' ? null : ln.instrucciones.trim(),
                    orden: idx,
                };
            });

            return {
                paciente_id: r.paciente_id,
                consulta_id: r.consulta_id.trim() === '' ? null : r.consulta_id.trim(),
                emitida_at: r.emitida_at,
                estado: r.estado,
                observaciones: r.observaciones.trim() === '' ? null : r.observaciones.trim(),
                veterinario_id:
                    r.veterinario_id != null && r.veterinario_id !== '' ? r.veterinario_id : null,
                sede_id: r.sede_id != null && r.sede_id !== '' ? r.sede_id : null,
                lineas: lineasOut,
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
            receta !== null
                ? fromReceta(receta, defaultVetId)
                : emptyForm(defaultVetId, sedesOpciones);
        initialSnapshotRef.current = structuredClone(next);
        setData(next);
        setDefaults();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, receta?.id, defaultVetId, receta, sedesOpciones]);

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
            receta?.consulta_id &&
            receta.consulta &&
            !list.some((c) => c.id === receta.consulta_id)
        ) {
            list.unshift({
                id: receta.consulta.id,
                atendido_at: receta.consulta.atendido_at,
                historia_clinica_id: receta.consulta.historia_clinica_id ?? '',
                historia_clinica: receta.consulta.historia_clinica ?? null,
            });
        }

        return list;
    }, [consultasOpciones, receta]);

    const consultasFiltradas = useMemo(() => {
        if (!data.paciente_id) {
            return [] as ConsultaRecetaOpcion[];
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

    const labelConsulta = (c: ConsultaRecetaOpcion): string => {
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

    const onProductSelect = (index: number, opt: RecetaProductoOption | null) => {
        if (opt === null) {
            updateLine(index, { producto_id: null });

            return;
        }

        updateLine(index, {
            producto_id: opt.id,
            nombre_medicamento:
                data.lineas[index]?.nombre_medicamento?.trim() === ''
                    ? opt.nombre
                    : (data.lineas[index]?.nombre_medicamento ?? opt.nombre),
        });
    };

    const lineLabel = (index: number, row: LineFormRow): string | null => {
        if (row.producto_id != null && row.producto_id !== '') {
            const name = row.nombre_medicamento.trim();

            return name !== '' ? name : null;
        }

        return null;
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
        data.lineas.some((ln) => ln.nombre_medicamento.trim().length > 0) &&
        !processing;

    const buildCreatePayload = (raw: FormShape): Record<string, unknown> => {
        const lineasOut = raw.lineas.map((ln, idx) => {
            const dd = ln.duracion_dias.trim();
            const ddVal = dd === '' ? null : Number.parseInt(dd, 10);

            return {
                producto_id: ln.producto_id && ln.producto_id !== '' ? ln.producto_id : null,
                nombre_medicamento: ln.nombre_medicamento.trim(),
                posologia: ln.posologia.trim() === '' ? null : ln.posologia.trim(),
                duracion_dias: dd === '' || ddVal === null || Number.isNaN(ddVal) ? null : ddVal,
                instrucciones: ln.instrucciones.trim() === '' ? null : ln.instrucciones.trim(),
                orden: idx,
            };
        });

        return {
            paciente_id: raw.paciente_id,
            consulta_id: raw.consulta_id.trim() === '' ? null : raw.consulta_id.trim(),
            emitida_at: raw.emitida_at,
            estado: raw.estado,
            observaciones: raw.observaciones.trim() === '' ? null : raw.observaciones.trim(),
            veterinario_id:
                raw.veterinario_id != null && raw.veterinario_id !== '' ? raw.veterinario_id : null,
            sede_id: raw.sede_id != null && raw.sede_id !== '' ? raw.sede_id : null,
            lineas: lineasOut,
        };
    };

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const onSuccess = () => {
            reset();
            clearErrors();
            onOpenChange(false);
        };

        if (isEdit && receta) {
            put(clinica.recetas.update({ receta: receta.id }).url, {
                preserveScroll: true,
                onSuccess,
            });

            return;
        }

        void (async () => {
            const queued = await enqueueIfOffline(
                'clinica.receta.create',
                buildCreatePayload(data),
                {
                    refreshPending,
                    onSuccess,
                    title: t('offline:receta.queued_title'),
                    description: t('offline:receta.queued_body'),
                },
            );

            if (queued) {
                return;
            }

            post(clinica.recetas.store().url, {
                preserveScroll: true,
                onSuccess,
            });
        })();
    };

    const err = (key: string): string | undefined => {
        const v = (errors as Record<string, string | undefined>)[key];

        return typeof v === 'string' ? v : undefined;
    };

    const openRecetaPdf = () => {
        if (!receta) {
            return;
        }

        window.open(
            clinica.recetas.pdf.url({ receta: receta.id }),
            '_blank',
            'noopener,noreferrer',
        );
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
                <div className="flex w-full flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        {isEdit && receta !== null && canPrintPdf ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={openRecetaPdf}
                                disabled={processing}
                                className="cursor-pointer gap-2"
                            >
                                <Printer className="size-4" strokeWidth={2.25} aria-hidden="true" />
                                {t('actions.print_pdf')}
                            </Button>
                        ) : null}
                    </div>
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
                        id="rf-paciente"
                        label={t('form.paciente')}
                        required
                        error={errors.paciente_id as string | undefined}
                        className="sm:col-span-2"
                    >
                        <Combobox
                            id="rf-paciente"
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
                        id="rf-consulta"
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
                            <SelectTrigger id="rf-consulta" className={controlClass}>
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
                        id="rf-emitida"
                        label={t('form.emitida_at')}
                        required
                        error={errors.emitida_at as string | undefined}
                    >
                        <Input
                            id="rf-emitida"
                            type="datetime-local"
                            className={controlClass}
                            value={data.emitida_at}
                            onChange={(e) => setData('emitida_at', e.target.value)}
                            aria-invalid={Boolean(errors.emitida_at)}
                            disabled={processing}
                        />
                    </FormField>

                    <FormField
                        id="rf-estado"
                        label={t('form.estado')}
                        required
                        error={errors.estado as string | undefined}
                    >
                        <Select
                            value={data.estado}
                            onValueChange={(v) => setData('estado', v)}
                            disabled={processing}
                        >
                            <SelectTrigger id="rf-estado" className={controlClass}>
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
                        id="rf-obs"
                        label={t('form.observaciones')}
                        error={errors.observaciones as string | undefined}
                        className="sm:col-span-2"
                    >
                        <Textarea
                            id="rf-obs"
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
                    <SedeFormField
                        id="rf-sede"
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
                                        id={`rf-lin-${index}-prod`}
                                        label={t('producto_picker.placeholder')}
                                        error={err(`lineas.${index}.producto_id`)}
                                        className="sm:col-span-2"
                                    >
                                        <RecetaProductoPicker
                                            id={`rf-lin-${index}-prod`}
                                            value={row.producto_id}
                                            labelResolved={lineLabel(index, row)}
                                            onSelect={(opt) => onProductSelect(index, opt)}
                                            disabled={processing}
                                            aria-invalid={Boolean(err(`lineas.${index}.producto_id`))}
                                        />
                                    </FormField>

                                    <FormField
                                        id={`rf-lin-${index}-nom`}
                                        label={t('form.linea_nombre')}
                                        required
                                        error={err(`lineas.${index}.nombre_medicamento`)}
                                    >
                                        <Input
                                            id={`rf-lin-${index}-nom`}
                                            className={controlClass}
                                            value={row.nombre_medicamento}
                                            onChange={(e) =>
                                                updateLine(index, { nombre_medicamento: e.target.value })
                                            }
                                            aria-invalid={Boolean(err(`lineas.${index}.nombre_medicamento`))}
                                            disabled={processing}
                                        />
                                    </FormField>

                                    <FormField
                                        id={`rf-lin-${index}-dur`}
                                        label={t('form.linea_duracion')}
                                        error={err(`lineas.${index}.duracion_dias`)}
                                    >
                                        <Input
                                            id={`rf-lin-${index}-dur`}
                                            type="number"
                                            min={1}
                                            max={999}
                                            className={controlClass}
                                            value={row.duracion_dias}
                                            onChange={(e) =>
                                                updateLine(index, { duracion_dias: e.target.value })
                                            }
                                            aria-invalid={Boolean(err(`lineas.${index}.duracion_dias`))}
                                            disabled={processing}
                                        />
                                    </FormField>

                                    <FormField
                                        id={`rf-lin-${index}-pos`}
                                        label={t('form.linea_posologia')}
                                        error={err(`lineas.${index}.posologia`)}
                                    >
                                        <Textarea
                                            id={`rf-lin-${index}-pos`}
                                            rows={2}
                                            className="resize-none text-sm"
                                            value={row.posologia}
                                            onChange={(e) => updateLine(index, { posologia: e.target.value })}
                                            aria-invalid={Boolean(err(`lineas.${index}.posologia`))}
                                            disabled={processing}
                                        />
                                    </FormField>

                                    <FormField
                                        id={`rf-lin-${index}-ins`}
                                        label={t('form.linea_instrucciones')}
                                        error={err(`lineas.${index}.instrucciones`)}
                                    >
                                        <Textarea
                                            id={`rf-lin-${index}-ins`}
                                            rows={2}
                                            className="resize-none text-sm"
                                            value={row.instrucciones}
                                            onChange={(e) =>
                                                updateLine(index, { instrucciones: e.target.value })
                                            }
                                            aria-invalid={Boolean(err(`lineas.${index}.instrucciones`))}
                                            disabled={processing}
                                        />
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
