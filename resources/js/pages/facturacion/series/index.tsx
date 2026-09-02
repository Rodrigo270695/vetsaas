import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    CheckCircle2,
    FileText,
    Hash,
    Layers,
    Plus,
    Power,
    Trash2,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
    DataTable,
    EmptyState,
    FilterChips,
    PageHeader,
    StatBadge,
} from '@/components/data-page';
import type { DataTableColumn, FilterChip } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';

type TipoOption = {
    value: number;
    label: string;
    hint: string;
};

type SedeOpcion = {
    id: string;
    nombre: string;
    codigo: string;
};

type FelSerie = {
    id: string;
    sede_id: string;
    sede_nombre: string;
    tipo_comprobante: number;
    tipo_label: string;
    serie: string;
    ultimo_correlativo: number;
    activo: boolean;
    tiene_documentos: boolean;
};

type Props = {
    series: FelSerie[];
    tipos?: TipoOption[];
    sedes_opciones?: SedeOpcion[];
    filters?: {
        sede_id: string;
    };
};

const TIPOS_DEFAULT: TipoOption[] = [
    { value: 1, label: 'Factura', hint: 'F### (ej. F001)' },
    { value: 2, label: 'Boleta de Venta', hint: 'B### (ej. B001)' },
    { value: 3, label: 'Nota de Crédito', hint: 'FC## o BC## (ej. FC01)' },
    { value: 4, label: 'Nota de Débito', hint: 'FD## o BD## (ej. FD01)' },
    { value: 5, label: 'Guía de Remisión', hint: 'T### (ej. T001)' },
];

const TIPO_COLORS: Record<number, string> = {
    1: 'bg-blue-50 text-blue-700 ring-blue-200',
    2: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    3: 'bg-violet-50 text-violet-700 ring-violet-200',
    4: 'bg-orange-50 text-orange-700 ring-orange-200',
    5: 'bg-amber-50 text-amber-700 ring-amber-200',
};

const ALL_SEDES = '__all__';

function TipoBadge({ tipo, label }: { tipo: number; label: string }) {
    return (
        <span
            className={`inline-flex max-w-44 items-center truncate rounded-md px-2 py-0.5 text-[11px] font-medium leading-tight whitespace-nowrap ring-1 sm:max-w-none ${TIPO_COLORS[tipo] ?? 'bg-muted text-muted-foreground ring-border'}`}
        >
            {label}
        </span>
    );
}

function SerieRowActions({
    serie,
    canUpdate,
    canDelete,
    toggling,
    deleting,
    onToggle,
    onDelete,
}: {
    serie: FelSerie;
    canUpdate: boolean;
    canDelete: boolean;
    toggling: boolean;
    deleting: boolean;
    onToggle: (serie: FelSerie) => void;
    onDelete: (id: string) => void;
}) {
    if (!canUpdate && !canDelete && !serie.tiene_documentos) {
        return null;
    }

    return (
        <div className="flex shrink-0 items-center justify-end gap-1">
            {canUpdate ? (
                <button
                    type="button"
                    onClick={() => onToggle(serie)}
                    disabled={toggling}
                    title={serie.activo ? 'Desactivar serie' : 'Activar serie'}
                    className={`cursor-pointer rounded-md p-1.5 transition-colors ${
                        serie.activo
                            ? 'text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                    } disabled:cursor-not-allowed disabled:opacity-50`}
                >
                    <Power className="size-4" />
                </button>
            ) : null}

            {canDelete && !serie.tiene_documentos ? (
                <button
                    type="button"
                    onClick={() => onDelete(serie.id)}
                    disabled={deleting}
                    title="Eliminar serie"
                    className="cursor-pointer rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <Trash2 className="size-4" />
                </button>
            ) : null}

            {serie.tiene_documentos ? (
                <span
                    title="Tiene comprobantes emitidos — no se puede eliminar"
                    className="rounded-md p-1.5 text-amber-500"
                >
                    <AlertTriangle className="size-4" />
                </span>
            ) : null}
        </div>
    );
}

export default function Index({
    series = [],
    tipos,
    sedes_opciones: sedesOpciones = [],
    filters,
}: Props) {
    const tiposResolved = tipos && tipos.length > 0 ? tipos : TIPOS_DEFAULT;
    const { can } = usePermission();
    const canCreate = can('series.create');
    const canUpdate = can('series.update');
    const canDelete = can('series.delete');

    const { props } = usePage<{ flash?: { success?: string } }>();
    const flash = props.flash;

    const sedeFiltro = filters?.sede_id ?? '';
    const showSedeColumn = sedeFiltro === '';

    const [showForm, setShowForm] = useState(false);
    const [formSede, setFormSede] = useState('');
    const [formTipo, setFormTipo] = useState('');
    const [formSerie, setFormSerie] = useState('');
    const [formCorrelativo, setFormCorrelativo] = useState('0');
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);
    const [deletingId, setDeletingId] = useState<string | null>(null);
    const [togglingId, setTogglingId] = useState<string | null>(null);

    useEffect(() => {
        if (sedeFiltro) {
            setFormSede(sedeFiltro);
        }
    }, [sedeFiltro]);

    const selectedTipo = tiposResolved.find((t) => String(t.value) === formTipo);
    const selectedSedeNombre = useMemo(
        () => sedesOpciones.find((s) => s.id === formSede)?.nombre ?? '',
        [formSede, sedesOpciones],
    );

    const sedeFilterOptions: readonly FilterChip<string>[] = useMemo(
        () => [
            { value: ALL_SEDES, label: 'Todas las sedes' },
            ...sedesOpciones.map((sede) => ({
                value: sede.id,
                label: sede.codigo ? `${sede.nombre} · ${sede.codigo}` : sede.nombre,
                icon: <Building2 className="size-3.5" strokeWidth={2.25} />,
            })),
        ],
        [sedesOpciones],
    );

    const applySedeFilter = (sedeId: string) => {
        const query = sedeId ? { sede_id: sedeId } : {};
        router.get('/facturacion/series', query, {
            preserveState: true,
            preserveScroll: true,
            only: ['series', 'filters', 'sedes_opciones'],
        });
    };

    const openCreateForm = () => {
        if (sedeFiltro) {
            setFormSede(sedeFiltro);
        } else if (sedesOpciones.length === 1) {
            setFormSede(sedesOpciones[0].id);
        }
        setShowForm(true);
    };

    const handleStore = () => {
        const errors: Record<string, string> = {};
        if (!formSede) {
            errors.sede_id = 'Selecciona la sede.';
        }
        if (!formTipo) {
            errors.tipo_comprobante = 'Selecciona el tipo de comprobante.';
        }
        if (!formSerie.trim()) {
            errors.serie = 'Ingresa la serie.';
        }

        if (Object.keys(errors).length > 0) {
            setFormErrors(errors);
            return;
        }

        setSubmitting(true);
        router.post(
            '/facturacion/series',
            {
                sede_id: formSede,
                tipo_comprobante: Number(formTipo),
                serie: formSerie.trim().toUpperCase(),
                ultimo_correlativo: Number(formCorrelativo) || 0,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setShowForm(false);
                    setFormTipo('');
                    setFormSerie('');
                    setFormCorrelativo('0');
                    setFormErrors({});
                    if (!sedeFiltro) {
                        setFormSede('');
                    }
                },
                onError: (errs) => setFormErrors(errs as Record<string, string>),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const handleToggle = useCallback((serie: FelSerie) => {
        setTogglingId(serie.id);
        router.patch(
            `/facturacion/series/${serie.id}`,
            { activo: !serie.activo },
            {
                preserveScroll: true,
                onFinish: () => setTogglingId(null),
            },
        );
    }, []);

    const handleDelete = useCallback((id: string) => {
        setDeletingId(id);
        router.delete(`/facturacion/series/${id}`, {
            preserveScroll: true,
            onFinish: () => setDeletingId(null),
        });
    }, []);

    const activas = series.filter((s) => s.activo).length;

    const byTipo = tiposResolved.map((t) => ({
        ...t,
        count: series.filter((s) => s.tipo_comprobante === t.value).length,
    }));

    const columns = useMemo<DataTableColumn<FelSerie>[]>(() => {
        const cols: DataTableColumn<FelSerie>[] = [];

        if (showSedeColumn) {
            cols.push({
                key: 'sede',
                header: 'Sede',
                cell: (s) => (
                    <span className="font-medium text-foreground">
                        {s.sede_nombre || '—'}
                    </span>
                ),
            });
        }

        cols.push(
            {
                key: 'tipo',
                header: 'Tipo',
                cell: (s) => (
                    <TipoBadge tipo={s.tipo_comprobante} label={s.tipo_label} />
                ),
            },
            {
                key: 'serie',
                header: 'Serie',
                cell: (s) => (
                    <span className="font-mono text-sm font-bold tracking-widest">
                        {s.serie}
                    </span>
                ),
                className: 'w-24',
            },
            {
                key: 'correlativo',
                header: 'Correlativo',
                align: 'right',
                cell: (s) => (
                    <span className="inline-flex items-center gap-1.5 tabular-nums">
                        <Hash className="size-3 text-muted-foreground/50" />
                        <span className="font-mono font-semibold">
                            {s.ultimo_correlativo.toLocaleString()}
                        </span>
                    </span>
                ),
            },
            {
                key: 'estado',
                header: 'Estado',
                align: 'center',
                cell: (s) =>
                    s.activo ? (
                        <StatBadge label="Activa" value="" variant="success" />
                    ) : (
                        <StatBadge label="Inactiva" value="" variant="muted" />
                    ),
            },
        );

        if (canUpdate || canDelete) {
            cols.push({
                key: 'acciones',
                header: <span className="md:sr-only">Acciones</span>,
                align: 'right',
                className: 'w-20',
                cell: (s) => (
                    <SerieRowActions
                        serie={s}
                        canUpdate={canUpdate}
                        canDelete={canDelete}
                        toggling={togglingId === s.id}
                        deleting={deletingId === s.id}
                        onToggle={handleToggle}
                        onDelete={handleDelete}
                    />
                ),
            });
        }

        return cols;
    }, [
        canDelete,
        canUpdate,
        deletingId,
        handleDelete,
        handleToggle,
        showSedeColumn,
        togglingId,
    ]);

    return (
        <>
            <Head title="Series de comprobantes" />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title="Series de comprobantes"
                    description="Administra las series SUNAT autorizadas por sede. Cada venta usa las series de la sede de tu sesión de caja."
                    stats={[
                        {
                            label: 'Total',
                            value: String(series.length),
                            variant: 'muted',
                            icon: Layers,
                        },
                        {
                            label: 'Activas',
                            value: String(activas),
                            variant: activas > 0 ? 'success' : 'warning',
                            icon: CheckCircle2,
                        },
                    ]}
                    action={
                        canCreate && !showForm ? (
                            <Button
                                onClick={openCreateForm}
                                className="cursor-pointer gap-2"
                                size="sm"
                            >
                                <Plus className="size-4" />
                                Nueva serie
                            </Button>
                        ) : undefined
                    }
                />

                {sedesOpciones.length > 0 ? (
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <FilterChips
                            ariaLabel="Filtrar por sede"
                            value={sedeFiltro || ALL_SEDES}
                            onChange={(value) =>
                                applySedeFilter(value === ALL_SEDES ? '' : value)
                            }
                            options={sedeFilterOptions}
                            className="sm:min-w-56"
                        />
                        {sedeFiltro ? (
                            <StatBadge
                                label="Sede filtrada"
                                value={
                                    sedesOpciones.find((s) => s.id === sedeFiltro)?.nombre ?? '—'
                                }
                                variant="info"
                            />
                        ) : null}
                    </div>
                ) : null}

                {flash?.success ? (
                    <div className="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        <CheckCircle2 className="size-4 shrink-0" />
                        {flash.success}
                    </div>
                ) : null}

                {showForm ? (
                    <div className="rounded-xl border border-primary/20 bg-primary/5 p-4">
                        <div className="mb-3 flex items-center justify-between gap-3">
                            <p className="text-sm font-semibold">Nueva serie de comprobante</p>
                            <button
                                type="button"
                                onClick={() => {
                                    setShowForm(false);
                                    setFormErrors({});
                                }}
                                className="cursor-pointer rounded-md p-1 text-muted-foreground hover:text-foreground"
                            >
                                <X className="size-4" />
                            </button>
                        </div>

                        <div className="flex flex-col gap-3">
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)_7rem_7rem_auto] xl:items-end">
                                <div className="flex flex-col gap-1.5">
                                    <label
                                        htmlFor="serie-sede"
                                        className="text-xs font-medium text-muted-foreground"
                                    >
                                        Sede
                                    </label>
                                    <Select
                                        value={formSede || undefined}
                                        onValueChange={setFormSede}
                                        disabled={Boolean(sedeFiltro)}
                                    >
                                        <SelectTrigger id="serie-sede" className="h-9 w-full cursor-pointer">
                                            <SelectValue placeholder="Seleccionar sede…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {sedesOpciones.map((sede) => (
                                                <SelectItem key={sede.id} value={sede.id}>
                                                    {sede.nombre}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <label
                                        htmlFor="serie-tipo"
                                        className="text-xs font-medium text-muted-foreground"
                                    >
                                        Tipo de comprobante
                                    </label>
                                    <Select value={formTipo} onValueChange={setFormTipo}>
                                        <SelectTrigger id="serie-tipo" className="h-9 w-full cursor-pointer">
                                            <SelectValue placeholder="Seleccionar tipo…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {tiposResolved.map((t) => (
                                                <SelectItem key={t.value} value={String(t.value)}>
                                                    {t.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <label
                                        htmlFor="serie-codigo"
                                        className="text-xs font-medium text-muted-foreground"
                                    >
                                        Serie
                                    </label>
                                    <Input
                                        id="serie-codigo"
                                        value={formSerie}
                                        onChange={(e) =>
                                            setFormSerie(
                                                e.target.value
                                                    .toUpperCase()
                                                    .replace(/[^A-Z0-9]/g, '')
                                                    .slice(0, 4),
                                            )
                                        }
                                        placeholder="F001"
                                        maxLength={4}
                                        className="h-9 font-mono tracking-widest"
                                    />
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <label
                                        htmlFor="serie-correlativo"
                                        className="text-xs font-medium text-muted-foreground"
                                    >
                                        Correlativo
                                        <span className="font-normal text-muted-foreground/60"> (opc.)</span>
                                    </label>
                                    <Input
                                        id="serie-correlativo"
                                        type="number"
                                        value={formCorrelativo}
                                        onChange={(e) => setFormCorrelativo(e.target.value)}
                                        min={0}
                                        placeholder="0"
                                        className="h-9 font-mono tabular-nums"
                                    />
                                </div>

                                <Button
                                    type="button"
                                    onClick={handleStore}
                                    disabled={submitting}
                                    size="sm"
                                    className="h-9 w-full cursor-pointer gap-2 xl:w-auto xl:shrink-0"
                                >
                                    <Plus className="size-4" />
                                    Guardar
                                </Button>
                            </div>

                            {(formErrors.sede_id ||
                                formErrors.tipo_comprobante ||
                                formErrors.serie) && (
                                <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-destructive">
                                    {formErrors.sede_id ? <span>{formErrors.sede_id}</span> : null}
                                    {formErrors.tipo_comprobante ? (
                                        <span>{formErrors.tipo_comprobante}</span>
                                    ) : null}
                                    {formErrors.serie ? <span>{formErrors.serie}</span> : null}
                                </div>
                            )}

                            {(selectedTipo || formSerie || selectedSedeNombre) && (
                                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-primary/10 pt-3 text-xs text-muted-foreground">
                                    {selectedSedeNombre ? (
                                        <span>
                                            Sede:{' '}
                                            <span className="font-medium text-foreground">
                                                {selectedSedeNombre}
                                            </span>
                                        </span>
                                    ) : null}
                                    {selectedTipo ? (
                                        <span>
                                            Formato:{' '}
                                            <span className="font-medium text-foreground">
                                                {selectedTipo.hint}
                                            </span>
                                        </span>
                                    ) : null}
                                    {formSerie ? (
                                        <span>
                                            Próximo número:{' '}
                                            <span className="font-mono font-medium text-foreground">
                                                {formSerie}-
                                                {String((Number(formCorrelativo) || 0) + 1).padStart(8, '0')}
                                            </span>
                                        </span>
                                    ) : null}
                                </div>
                            )}
                        </div>
                    </div>
                ) : null}

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    {byTipo.map((t) => (
                        <div
                            key={t.value}
                            className="flex flex-col gap-1 rounded-xl border border-border/60 bg-card p-3 shadow-sm sm:p-4"
                        >
                            <span className="truncate text-xs font-medium text-muted-foreground">
                                {t.label}
                            </span>
                            <span className="text-2xl font-bold tabular-nums">{t.count}</span>
                            <span className="text-xs text-muted-foreground">
                                {t.count === 1 ? 'serie' : 'series'}
                            </span>
                        </div>
                    ))}
                </div>

                <DataTable
                    columns={columns}
                    data={series}
                    rowKey={(s) => s.id}
                    getRowClassName={(s) => (s.activo ? '' : 'opacity-60')}
                    mobileCard={(s) => (
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-mono text-base font-bold tracking-widest">
                                        {s.serie}
                                    </span>
                                    {s.activo ? (
                                        <StatBadge
                                            label="Activa"
                                            value=""
                                            variant="success"
                                        />
                                    ) : (
                                        <StatBadge
                                            label="Inactiva"
                                            value=""
                                            variant="muted"
                                        />
                                    )}
                                </div>
                                {showSedeColumn ? (
                                    <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                        {s.sede_nombre || '—'}
                                    </p>
                                ) : null}
                                <div className="mt-1.5 flex flex-wrap items-center gap-2">
                                    <TipoBadge
                                        tipo={s.tipo_comprobante}
                                        label={s.tipo_label}
                                    />
                                    <span className="font-mono text-xs tabular-nums text-muted-foreground">
                                        Corr. {s.ultimo_correlativo.toLocaleString()}
                                    </span>
                                </div>
                            </div>
                            <SerieRowActions
                                serie={s}
                                canUpdate={canUpdate}
                                canDelete={canDelete}
                                toggling={togglingId === s.id}
                                deleting={deletingId === s.id}
                                onToggle={handleToggle}
                                onDelete={handleDelete}
                            />
                        </div>
                    )}
                    emptyState={
                        <EmptyState
                            icon={FileText}
                            title="Sin series configuradas"
                            description={
                                sedeFiltro
                                    ? 'Crea al menos una serie para esta sede antes de emitir comprobantes.'
                                    : 'Crea una serie para cada sede y tipo de comprobante que vayas a emitir.'
                            }
                            action={
                                canCreate ? (
                                    <Button
                                        size="sm"
                                        onClick={openCreateForm}
                                        className="cursor-pointer gap-2"
                                    >
                                        <Plus className="size-4" />
                                        Nueva serie
                                    </Button>
                                ) : undefined
                            }
                        />
                    }
                />

                <div className="flex items-start gap-3 rounded-lg border border-border/60 bg-muted/20 p-4 text-xs text-muted-foreground">
                    <FileText className="mt-0.5 size-4 shrink-0" strokeWidth={1.5} />
                    <div className="flex flex-col gap-1">
                        <span className="font-medium text-foreground">Nomenclatura SUNAT</span>
                        <span>
                            Factura: <code className="font-mono">F###</code> · Boleta:{' '}
                            <code className="font-mono">B###</code> · Nota de crédito:{' '}
                            <code className="font-mono">FC##</code> / <code className="font-mono">BC##</code> · Nota
                            de débito: <code className="font-mono">FD##</code> /{' '}
                            <code className="font-mono">BD##</code> · Guía de remisión:{' '}
                            <code className="font-mono">T###</code>
                        </span>
                    </div>
                </div>
            </div>
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Facturación', href: '#' },
            { title: 'Series', href: '/facturacion/series' },
        ]}
    >
        {page}
    </AppLayout>
);
