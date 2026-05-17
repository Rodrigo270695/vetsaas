import { useForm } from '@inertiajs/react';
import { Loader2, Package, Pencil, Pill, Plus, Trash2 } from 'lucide-react';
import { flushSync } from 'react-dom';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField } from '@/components/forms';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import clinica from '@/routes/clinica';
import type { ConsultaPlanTratamientoResumen } from '../types';
import {
    ProductoMedicamentoPicker,
    type ProductoMedicamentoOption,
} from './producto-medicamento-picker';

const inputSm = 'h-9 w-full min-w-0 text-sm';

const PLAN_AUTOSAVE_MS = 850;

type PlanLineaForm = {
    producto_id: string | null;
    cantidad: string;
    medicamento: string;
    dosis: string;
    unidad: string;
    via: string;
    frecuencia: string;
    lote: string;
    notas: string;
    /** `yyyy-MM-dd` — cuándo se registró la línea en el plan. */
    anadido_en: string;
};

type PlanMedicacionFormData = {
    fecha_inicio: string;
    fecha_fin: string;
    indicaciones: string;
    estado: 'activo' | 'completado' | 'suspendido';
    lineas: PlanLineaForm[];
};

function todayYmd(): string {
    const n = new Date();
    const pad = (x: number) => String(x).padStart(2, '0');
    return `${n.getFullYear()}-${pad(n.getMonth() + 1)}-${pad(n.getDate())}`;
}

function emptyPlanLinea(): PlanLineaForm {
    return {
        producto_id: null,
        cantidad: '',
        medicamento: '',
        dosis: '',
        unidad: '',
        via: '',
        frecuencia: '',
        lote: '',
        notas: '',
        anadido_en: todayYmd(),
    };
}

function defaultPlanForm(): PlanMedicacionFormData {
    return {
        fecha_inicio: '',
        fecha_fin: '',
        indicaciones: '',
        estado: 'activo',
        lineas: [],
    };
}

/** `type="date"` solo admite `yyyy-MM-dd`; el API puede devolver ISO (`…T00:00:00.000000Z`). */
function toDateInputValue(value: string | null | undefined): string {
    if (value == null || value === '') {
        return '';
    }
    const fromIsoPrefix = value.match(/^(\d{4}-\d{2}-\d{2})/);
    if (fromIsoPrefix) {
        return fromIsoPrefix[1];
    }
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) {
        return '';
    }
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function mapPlanApiToForm(p: ConsultaPlanTratamientoResumen): PlanMedicacionFormData {
    const estado =
        p.estado === 'completado' || p.estado === 'suspendido' ? p.estado : 'activo';
    const lineas = p.lineas.map((ln) => ({
        producto_id: ln.producto_id ?? null,
        cantidad: ln.cantidad != null && String(ln.cantidad).trim() !== '' ? String(ln.cantidad) : '',
        medicamento: ln.medicamento,
        dosis: ln.dosis ?? '',
        unidad: ln.unidad ?? '',
        via: ln.via ?? '',
        frecuencia: ln.frecuencia ?? '',
        lote: ln.lote ?? '',
        notas: ln.notas ?? '',
        anadido_en: toDateInputValue(ln.anadido_en),
    }));

    return {
        fecha_inicio: toDateInputValue(p.fecha_inicio),
        fecha_fin: toDateInputValue(p.fecha_fin),
        indicaciones: p.indicaciones ?? '',
        estado,
        lineas,
    };
}

export type PlanMedicacionEditorProps = {
    consultaId: string;
    initialPlan: ConsultaPlanTratamientoResumen | null;
};

export function PlanMedicacionEditor({ consultaId, initialPlan }: PlanMedicacionEditorProps) {
    const { t, i18n } = useTranslation(['historias-clinicas', 'common']);
    const { data, setData, put, processing, errors, clearErrors, transform } =
        useForm<PlanMedicacionFormData>(
            initialPlan !== null ? mapPlanApiToForm(initialPlan) : defaultPlanForm(),
        );

    const initialKey = initialPlan?.id ?? 'new';

    const [sheetOpen, setSheetOpen] = useState(false);
    const [sheetMode, setSheetMode] = useState<'new' | 'edit'>('new');
    const [sheetIndex, setSheetIndex] = useState<number | null>(null);
    const [draft, setDraft] = useState<PlanLineaForm>(emptyPlanLinea());
    const [draftError, setDraftError] = useState<string | null>(null);
    /** Existencia en sede (solo referencia; el plan no mueve inventario). */
    const [stockSedeHint, setStockSedeHint] = useState<string | null>(null);
    /** Índice de la línea a eliminar tras confirmar (`null` = diálogo cerrado). */
    const [deleteConfirmIndex, setDeleteConfirmIndex] = useState<number | null>(null);

    /** Solo guardado diferido de cabecera del plan (fechas / indicaciones / estado). */
    const dirtyPlanHeaderRef = useRef(false);

    const formatLineDate = (ymd: string): string => {
        const trimmed = ymd.trim();
        if (trimmed === '') {
            return '';
        }
        const m = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!m) {
            return trimmed;
        }
        const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
        return d.toLocaleDateString(i18n.language, {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    };

    useEffect(() => {
        transform((raw) => ({
            fecha_inicio: raw.fecha_inicio.trim() === '' ? null : raw.fecha_inicio.trim(),
            fecha_fin: raw.fecha_fin.trim() === '' ? null : raw.fecha_fin.trim(),
            indicaciones: raw.indicaciones.trim() === '' ? null : raw.indicaciones.trim(),
            estado: raw.estado,
            lineas: raw.lineas.map((ln) => ({
                producto_id:
                    ln.producto_id != null &&
                    typeof ln.producto_id === 'string' &&
                    ln.producto_id.trim() !== ''
                        ? ln.producto_id.trim()
                        : null,
                cantidad:
                    String(ln.cantidad ?? '').trim() === '' ? null : String(ln.cantidad).trim(),
                medicamento: ln.medicamento.trim(),
                dosis: ln.dosis.trim() === '' ? null : ln.dosis.trim(),
                unidad: ln.unidad.trim() === '' ? null : ln.unidad.trim(),
                via: ln.via.trim() === '' ? null : ln.via.trim(),
                frecuencia: ln.frecuencia.trim() === '' ? null : ln.frecuencia.trim(),
                lote: ln.lote.trim() === '' ? null : ln.lote.trim(),
                notas: ln.notas.trim() === '' ? null : ln.notas.trim(),
                anadido_en: ln.anadido_en.trim() === '' ? null : ln.anadido_en.trim(),
            })),
        }));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        clearErrors();
        dirtyPlanHeaderRef.current = false;
        setSheetOpen(false);
        setDraftError(null);
        setDeleteConfirmIndex(null);
        setStockSedeHint(null);
        if (initialPlan !== null) {
            setData(mapPlanApiToForm(initialPlan));
        } else {
            setData(defaultPlanForm());
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [initialKey]);

    const planUpdateUrl = clinica.historiasClinicas.consultas.planTratamiento.update.url(consultaId);

    useEffect(() => {
        if (!dirtyPlanHeaderRef.current) {
            return;
        }
        const id = window.setTimeout(() => {
            put(planUpdateUrl, {
                preserveScroll: true,
                onSuccess: () => {
                    clearErrors();
                    dirtyPlanHeaderRef.current = false;
                },
            });
        }, PLAN_AUTOSAVE_MS);
        return () => window.clearTimeout(id);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- solo cabecera del plan; líneas guardan con put inmediato
    }, [data.fecha_inicio, data.fecha_fin, data.indicaciones, data.estado]);

    useEffect(() => {
        if (!sheetOpen) {
            return;
        }
        if (draft.producto_id == null || draft.producto_id === '') {
            setStockSedeHint(null);

            return;
        }
        const pid = draft.producto_id;
        const controller = new AbortController();
        void fetch(
            clinica.historiasClinicas.productosMedicamento.url({
                query: { for_product_id: pid },
            }),
            {
                method: 'GET',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: controller.signal,
            },
        )
            .then(async (res) => {
                const body = (await res.json()) as { data?: ProductoMedicamentoOption[] };
                if (!res.ok || !Array.isArray(body.data)) {
                    return;
                }
                const row = body.data[0];
                if (row?.stock_sede != null && String(row.stock_sede).trim() !== '') {
                    setStockSedeHint(String(row.stock_sede));
                }
            })
            .catch(() => {
                /* abort o red */
            });

        return () => controller.abort();
    }, [sheetOpen, draft.producto_id]);

    const markPlanHeaderDirty = () => {
        dirtyPlanHeaderRef.current = true;
    };

    const openSheetNew = () => {
        setSheetMode('new');
        setSheetIndex(null);
        setDraft(emptyPlanLinea());
        setDraftError(null);
        setStockSedeHint(null);
        setSheetOpen(true);
    };

    const openSheetEdit = (index: number) => {
        const row = data.lineas[index];
        if (!row) {
            return;
        }
        setSheetMode('edit');
        setSheetIndex(index);
        setDraft({ ...row });
        setDraftError(null);
        setStockSedeHint(null);
        setSheetOpen(true);
    };

    const commitDraft = () => {
        const med = draft.medicamento.trim();
        if (med === '') {
            setDraftError(t('plan.lineas_sheet.medicamento_required'));
            return;
        }
        setDraftError(null);
        const anadido =
            draft.anadido_en.trim() === '' ? todayYmd() : toDateInputValue(draft.anadido_en);
        const row: PlanLineaForm = {
            ...draft,
            medicamento: med,
            anadido_en: anadido,
        };

        const nextLineas =
            sheetMode === 'new'
                ? [...data.lineas, row]
                : sheetMode === 'edit' && sheetIndex !== null
                  ? data.lineas.map((r, i) => (i === sheetIndex ? row : r))
                  : data.lineas;

        flushSync(() => {
            setData('lineas', nextLineas);
        });

        put(planUpdateUrl, {
            preserveScroll: true,
            onSuccess: () => {
                clearErrors();
                setSheetOpen(false);
            },
        });
    };

    const confirmRemoveLinea = () => {
        if (deleteConfirmIndex === null) {
            return;
        }
        const idx = deleteConfirmIndex;
        setDeleteConfirmIndex(null);

        if (sheetOpen && sheetMode === 'edit' && sheetIndex !== null) {
            if (sheetIndex === idx) {
                setSheetOpen(false);
                setSheetIndex(null);
            } else if (sheetIndex > idx) {
                setSheetIndex(sheetIndex - 1);
            }
        }

        const nextLineas = data.lineas.filter((_, i) => i !== idx);
        flushSync(() => {
            setData('lineas', nextLineas);
        });

        put(planUpdateUrl, {
            preserveScroll: true,
            onSuccess: () => {
                clearErrors();
            },
        });
    };

    const sheetMedicamentoServerError =
        sheetMode === 'edit' && sheetIndex !== null
            ? (errors as Record<string, string | undefined>)[
                  `lineas.${sheetIndex}.medicamento`
              ]
            : undefined;

    const sheetProductoServerError =
        sheetMode === 'edit' && sheetIndex !== null
            ? (errors as Record<string, string | undefined>)[
                  `lineas.${sheetIndex}.producto_id`
              ]
            : undefined;

    const sheetCantidadServerError =
        sheetMode === 'edit' && sheetIndex !== null
            ? (errors as Record<string, string | undefined>)[`lineas.${sheetIndex}.cantidad`]
            : undefined;

    const applyProductoInventario = (opt: ProductoMedicamentoOption | null) => {
        if (opt === null) {
            setStockSedeHint(null);
        } else if (opt.stock_sede != null && String(opt.stock_sede).trim() !== '') {
            setStockSedeHint(String(opt.stock_sede));
        } else {
            setStockSedeHint(null);
        }
        setDraft((d) => {
            if (opt === null) {
                return { ...d, producto_id: null };
            }
            const unidad =
                d.unidad.trim() === '' && opt.unidad != null && opt.unidad.trim() !== ''
                    ? opt.unidad
                    : d.unidad;

            return {
                ...d,
                producto_id: opt.id,
                medicamento: opt.nombre,
                unidad,
            };
        });
        setDraftError(null);
    };

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                <p className="max-w-xl leading-relaxed">{t('plan.lineas_editor.autosave_hint')}</p>
                {processing && (
                    <span className="inline-flex shrink-0 items-center gap-1.5 text-primary">
                        <Loader2 className="size-3.5 animate-spin" aria-hidden />
                        {t('plan.lineas_editor.saving')}
                    </span>
                )}
            </div>

            <div className="overflow-hidden rounded-xl border border-primary/20 bg-card shadow-sm ring-1 ring-primary/10 dark:ring-primary/20">
                <div className="flex items-start gap-2.5 border-b border-primary/10 bg-gradient-to-r from-primary/[0.08] to-muted/40 px-3 py-2.5 sm:gap-3 sm:px-4 dark:from-primary/15">
                    <span
                        className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/15 text-primary shadow-sm ring-1 ring-primary/20"
                        aria-hidden
                    >
                        <Pill className="size-5" strokeWidth={2} />
                    </span>
                    <div className="min-w-0 flex-1 space-y-0.5">
                        <h3 className="text-sm font-semibold tracking-tight text-foreground">
                            {t('form.plan_tratamiento_section')}
                        </h3>
                        <p className="text-xs leading-relaxed text-muted-foreground">
                            {t('form.plan_tratamiento_hint')}
                        </p>
                    </div>
                </div>

                <div className="space-y-3 p-3 sm:space-y-4 sm:p-4">
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <FormField
                            id="pt-inicio"
                            label={t('form.plan_tratamiento_fecha_inicio')}
                            error={errors.fecha_inicio}
                            className="min-w-0"
                        >
                            <Input
                                id="pt-inicio"
                                type="date"
                                value={data.fecha_inicio}
                                onChange={(e) => {
                                    markPlanHeaderDirty();
                                    setData('fecha_inicio', e.target.value);
                                }}
                                className={inputSm}
                            />
                        </FormField>
                        <FormField
                            id="pt-fin"
                            label={t('form.plan_tratamiento_fecha_fin')}
                            error={errors.fecha_fin}
                            className="min-w-0"
                        >
                            <Input
                                id="pt-fin"
                                type="date"
                                value={data.fecha_fin}
                                onChange={(e) => {
                                    markPlanHeaderDirty();
                                    setData('fecha_fin', e.target.value);
                                }}
                                className={inputSm}
                            />
                        </FormField>
                        <FormField
                            id="pt-estado"
                            label={t('form.plan_tratamiento_estado')}
                            error={errors.estado}
                            className="min-w-0 lg:col-span-2"
                        >
                            <Select
                                value={data.estado}
                                onValueChange={(v) => {
                                    markPlanHeaderDirty();
                                    setData('estado', v as PlanMedicacionFormData['estado']);
                                }}
                            >
                                <SelectTrigger
                                    id="pt-estado"
                                    className={cn(inputSm, 'cursor-pointer')}
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="activo">{t('plan.estado.activo')}</SelectItem>
                                    <SelectItem value="completado">
                                        {t('plan.estado.completado')}
                                    </SelectItem>
                                    <SelectItem value="suspendido">
                                        {t('plan.estado.suspendido')}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>
                    </div>

                    <FormField
                        id="pt-ind"
                        label={t('form.plan_tratamiento_indicaciones')}
                        error={errors.indicaciones}
                        className="min-w-0"
                    >
                        <Textarea
                            id="pt-ind"
                            value={data.indicaciones}
                            onChange={(e) => {
                                markPlanHeaderDirty();
                                setData('indicaciones', e.target.value);
                            }}
                            rows={2}
                            className="min-h-[4.25rem] resize-y text-sm"
                        />
                    </FormField>

                    <div className="space-y-2 border-t border-border/60 pt-3">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-primary/10 pb-2">
                            <div className="flex items-center gap-1.5">
                                <Pill
                                    className="size-3.5 shrink-0 text-primary"
                                    strokeWidth={2.25}
                                    aria-hidden
                                />
                                <h4 className="text-[0.65rem] font-semibold uppercase tracking-wide text-primary/90">
                                    {t('form.plan_tratamiento_lineas_title')}
                                </h4>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="default"
                                className="h-7 cursor-pointer gap-1 bg-primary px-2.5 text-xs shadow-sm"
                                onClick={openSheetNew}
                            >
                                <Plus className="size-3.5" strokeWidth={2.5} />
                                {t('plan.lineas_editor.add_open')}
                            </Button>
                        </div>

                        {data.lineas.length === 0 ? (
                            <button
                                type="button"
                                onClick={openSheetNew}
                                className="flex w-full flex-col items-center gap-1 rounded-md border border-dashed border-primary/25 bg-muted/20 px-3 py-3 text-center transition-colors hover:border-primary/40 hover:bg-muted/35"
                            >
                                <span className="text-sm font-medium text-foreground">
                                    {t('plan.lineas_editor.dashed_title')}
                                </span>
                                <span className="max-w-sm text-xs text-muted-foreground">
                                    {t('plan.lineas_editor.dashed_hint')}
                                </span>
                            </button>
                        ) : (
                            <ul
                                className="overflow-hidden rounded-md border border-border/60 bg-card"
                                role="list"
                            >
                                {data.lineas.map((ln, idx) => (
                                    <li
                                        key={idx}
                                        className={cn(
                                            'flex items-center gap-2 border-border/50 px-2 py-1 transition-colors sm:gap-2.5 sm:px-2.5 sm:py-1',
                                            'hover:bg-muted/35',
                                            idx !== 0 && 'border-t',
                                        )}
                                    >
                                        <span
                                            className="size-1 shrink-0 rounded-full bg-primary/80"
                                            aria-hidden
                                        />
                                        <div className="min-w-0 flex-1">
                                            <button
                                                type="button"
                                                title={t('plan.lineas_editor.row_open_edit_title')}
                                                className="flex w-full min-w-0 flex-col gap-0.5 text-left sm:flex-row sm:items-baseline sm:gap-2"
                                                onClick={() => openSheetEdit(idx)}
                                            >
                                                <span className="inline-flex min-w-0 items-center gap-1.5">
                                                    {ln.producto_id ? (
                                                        <span
                                                            className="shrink-0 text-primary"
                                                            title={t('plan.linea.inventario_badge')}
                                                        >
                                                            <Package
                                                                className="size-3.5"
                                                                strokeWidth={2}
                                                                aria-hidden
                                                            />
                                                        </span>
                                                    ) : null}
                                                    <span className="truncate text-sm font-medium text-foreground underline-offset-2 hover:underline">
                                                        {ln.medicamento}
                                                    </span>
                                                </span>
                                                {ln.anadido_en.trim() !== '' ? (
                                                    <span className="shrink-0 text-xs tabular-nums text-muted-foreground">
                                                        {formatLineDate(ln.anadido_en)}
                                                    </span>
                                                ) : null}
                                            </button>
                                        </div>
                                        <div className="flex shrink-0 items-center">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-7 text-muted-foreground hover:text-primary"
                                                onClick={() => openSheetEdit(idx)}
                                                aria-label={t('plan.lineas_sheet.edit_aria')}
                                            >
                                                <Pencil className="size-3.5" strokeWidth={2} />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-7 text-muted-foreground hover:text-destructive"
                                                onClick={() => setDeleteConfirmIndex(idx)}
                                                aria-label={t('form.plan_tratamiento_remove_linea')}
                                            >
                                                <Trash2 className="size-3.5" strokeWidth={2} />
                                            </Button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            </div>

            <Sheet
                open={sheetOpen}
                onOpenChange={(o) => {
                    setSheetOpen(o);
                    if (!o) {
                        setDraftError(null);
                        setStockSedeHint(null);
                    }
                }}
            >
                <SheetContent
                    side="right"
                    className="flex w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-lg"
                >
                    <SheetHeader className="border-b border-primary/10 bg-gradient-to-r from-primary/[0.08] to-muted/40 px-4 pb-3 pt-10 sm:pr-12 dark:from-primary/15">
                        <SheetTitle className="text-left text-base">
                            {sheetMode === 'new'
                                ? t('plan.lineas_sheet.title_new')
                                : t('plan.lineas_sheet.title_edit')}
                        </SheetTitle>
                    </SheetHeader>

                    <div className="flex flex-1 flex-col gap-4 overflow-y-auto px-4 py-4">
                        <FormField
                            id="sheet-producto-inv"
                            label={t('plan.producto_picker.placeholder')}
                            error={sheetProductoServerError}
                            className="min-w-0"
                        >
                            <ProductoMedicamentoPicker
                                id="sheet-producto-inv"
                                value={draft.producto_id}
                                labelResolved={
                                    draft.producto_id != null && draft.medicamento.trim() !== ''
                                        ? draft.medicamento
                                        : null
                                }
                                onSelect={applyProductoInventario}
                                disabled={processing}
                                aria-invalid={Boolean(sheetProductoServerError)}
                            />
                        </FormField>

                        {draft.producto_id != null && draft.producto_id !== '' ? (
                            <div className="rounded-md border border-border/60 bg-muted/20 px-3 py-2 text-xs leading-relaxed text-muted-foreground">
                                <p className="font-medium text-foreground">
                                    {t('plan.linea.stock_sede_referencia')}
                                </p>
                                <p className="mt-0.5 tabular-nums text-sm text-foreground">
                                    {stockSedeHint ?? '—'}
                                </p>
                                <p className="mt-1 text-[0.7rem] leading-snug">
                                    {t('plan.linea.stock_sede_nota')}
                                </p>
                            </div>
                        ) : null}

                        <FormField
                            id="sheet-med"
                            label={t('plan.linea.medicamento')}
                            required
                            error={draftError ?? sheetMedicamentoServerError}
                            className="min-w-0"
                        >
                            <Input
                                id="sheet-med"
                                value={draft.medicamento}
                                onChange={(e) => {
                                    setDraft((d) => ({ ...d, medicamento: e.target.value }));
                                    setDraftError(null);
                                }}
                                className={inputSm}
                                autoFocus
                            />
                        </FormField>
                        <p className="-mt-2 text-xs leading-relaxed text-muted-foreground">
                            {t('plan.linea.medicamento_hint')}
                        </p>

                        <FormField
                            id="sheet-anadido"
                            label={t('plan.linea.anadido_en')}
                            className="min-w-0"
                        >
                            <Input
                                id="sheet-anadido"
                                type="date"
                                value={draft.anadido_en}
                                onChange={(e) =>
                                    setDraft((d) => ({ ...d, anadido_en: e.target.value }))
                                }
                                className={inputSm}
                            />
                        </FormField>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <FormField
                                id="sheet-dosis"
                                label={t('plan.linea.dosis')}
                                className="min-w-0"
                            >
                                <Input
                                    id="sheet-dosis"
                                    value={draft.dosis}
                                    onChange={(e) =>
                                        setDraft((d) => ({ ...d, dosis: e.target.value }))
                                    }
                                    className={inputSm}
                                />
                            </FormField>
                            <FormField
                                id="sheet-unidad"
                                label={t('plan.linea.unidad')}
                                className="min-w-0"
                            >
                                <Input
                                    id="sheet-unidad"
                                    value={draft.unidad}
                                    onChange={(e) =>
                                        setDraft((d) => ({ ...d, unidad: e.target.value }))
                                    }
                                    className={inputSm}
                                />
                            </FormField>
                            <FormField id="sheet-via" label={t('plan.linea.via')} className="min-w-0">
                                <Input
                                    id="sheet-via"
                                    value={draft.via}
                                    onChange={(e) => setDraft((d) => ({ ...d, via: e.target.value }))}
                                    className={inputSm}
                                />
                            </FormField>
                            <FormField
                                id="sheet-freq"
                                label={t('plan.linea.frecuencia')}
                                className="min-w-0"
                            >
                                <Input
                                    id="sheet-freq"
                                    value={draft.frecuencia}
                                    onChange={(e) =>
                                        setDraft((d) => ({ ...d, frecuencia: e.target.value }))
                                    }
                                    className={inputSm}
                                />
                            </FormField>
                            <FormField id="sheet-lote" label={t('plan.linea.lote')} className="min-w-0">
                                <Input
                                    id="sheet-lote"
                                    value={draft.lote}
                                    onChange={(e) => setDraft((d) => ({ ...d, lote: e.target.value }))}
                                    className={inputSm}
                                />
                            </FormField>
                            <FormField
                                id="sheet-cantidad-inv"
                                label={t('plan.linea.cantidad_inventario')}
                                hint={
                                    draft.producto_id != null && draft.producto_id !== ''
                                        ? t('plan.linea.cantidad_inventario_hint')
                                        : undefined
                                }
                                error={sheetCantidadServerError}
                                className="min-w-0 sm:col-span-2"
                            >
                                <Input
                                    id="sheet-cantidad-inv"
                                    inputMode="decimal"
                                    value={draft.cantidad}
                                    onChange={(e) =>
                                        setDraft((d) => ({ ...d, cantidad: e.target.value }))
                                    }
                                    className={inputSm}
                                    aria-invalid={Boolean(sheetCantidadServerError)}
                                />
                            </FormField>
                        </div>

                        <FormField id="sheet-notas" label={t('plan.linea.notas')} className="min-w-0">
                            <Textarea
                                id="sheet-notas"
                                value={draft.notas}
                                onChange={(e) => setDraft((d) => ({ ...d, notas: e.target.value }))}
                                rows={3}
                                className="resize-y text-sm"
                            />
                        </FormField>
                    </div>

                    <SheetFooter className="mt-0 flex-row flex-wrap justify-end gap-2 border-t border-border/80 bg-muted/20 px-4 py-3">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="cursor-pointer"
                            onClick={() => setSheetOpen(false)}
                        >
                            {t('common:actions.cancel')}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            className="cursor-pointer gap-1.5 shadow-sm"
                            disabled={processing}
                            onClick={commitDraft}
                        >
                            {processing && <Loader2 className="size-3.5 animate-spin" aria-hidden />}
                            {t('plan.lineas_sheet.save')}
                        </Button>
                    </SheetFooter>
                </SheetContent>
            </Sheet>

            <Dialog
                open={deleteConfirmIndex !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteConfirmIndex(null);
                    }
                }}
            >
                <DialogContent className="gap-4 sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{t('plan.lineas_editor.delete_confirm_title')}</DialogTitle>
                        <DialogDescription>
                            {t('plan.lineas_editor.delete_confirm_description', {
                                medicamento:
                                    deleteConfirmIndex !== null
                                        ? data.lineas[deleteConfirmIndex]?.medicamento?.trim() ||
                                          '—'
                                        : '',
                            })}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button
                            type="button"
                            variant="outline"
                            className="cursor-pointer"
                            onClick={() => setDeleteConfirmIndex(null)}
                        >
                            {t('common:actions.cancel')}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            className="cursor-pointer"
                            disabled={processing}
                            onClick={confirmRemoveLinea}
                        >
                            {t('plan.lineas_editor.delete_confirm_action')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
