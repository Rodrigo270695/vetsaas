import { router } from '@inertiajs/react';
import { Loader2, Package, Plus, Stethoscope } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DataTable, StatBadge } from '@/components/data-page';
import type { DataTableColumn } from '@/components/data-page';
import { FormField, FormModal } from '@/components/forms';
import {
    CategoriaServicioClinicoCombobox,
    type CategoriaServicioClinicoOption,
} from '@/components/tarifas/categoria-servicio-clinico-combobox';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox, type ComboboxOption } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { GroomingInsumosModal } from './grooming-insumos-modal';
import { ServiciosClinicosList } from './servicios-clinicos-list';
import { TarifaDeleteDialog } from './tarifa-delete-dialog';
import { TarifaRowActions } from './tarifa-row-actions';
import type { CatalogoClinicaRow } from '../types';

type CatalogoKind = 'grooming' | 'hotel' | 'clinica';

type CatalogoClinicaPanelProps = {
    kind: CatalogoKind;
    rows: readonly CatalogoClinicaRow[];
    canCreate: boolean;
    canUpdate: boolean;
    canDelete: boolean;
    /** Rutas CRUD: tarifas (configuración) o servicios (módulo operativo). */
    routesBase?: 'tarifas' | 'servicios';
    categoriaOptions?: readonly CategoriaServicioClinicoOption[];
};

type FormState = {
    nombre: string;
    categoria: string;
    categoria_id: string | null;
    precio_lista: string;
    moneda: string;
    duracion_minutos: string;
    activo: boolean;
};

const MONEDA_OPTIONS: ComboboxOption[] = [
    { value: 'PEN', label: 'PEN — Soles' },
    { value: 'USD', label: 'USD — Dólares' },
];

const emptyForm = (kind: CatalogoKind): FormState => ({
    nombre: '',
    categoria: '',
    categoria_id: null,
    precio_lista: '',
    moneda: 'PEN',
    duracion_minutos: kind === 'grooming' ? '60' : '',
    activo: true,
});

function formatPrecio(amount: string, moneda: string) {
    const n = Number(amount);
    const cur = moneda === 'USD' ? 'USD' : 'PEN';

    return Number.isNaN(n)
        ? amount
        : new Intl.NumberFormat(undefined, { style: 'currency', currency: cur }).format(n);
}

function routesFor(kind: CatalogoKind, base: 'tarifas' | 'servicios') {
    if (base === 'servicios') {
        if (kind === 'grooming') {
            return {
                store: '/servicios/grooming/servicios',
                update: (id: string) => `/servicios/grooming/servicios/${id}`,
                destroy: (id: string) => `/servicios/grooming/servicios/${id}`,
            };
        }

        return {
            store: '/servicios/hotel/tipos',
            update: (id: string) => `/servicios/hotel/tipos/${id}`,
            destroy: (id: string) => `/servicios/hotel/tipos/${id}`,
        };
    }

    if (kind === 'grooming') {
        return {
            store: '/configuracion/tarifas/grooming',
            update: (id: string) => `/configuracion/tarifas/grooming/${id}`,
            destroy: (id: string) => `/configuracion/tarifas/grooming/${id}`,
        };
    }

    if (kind === 'clinica') {
        return {
            store: '/configuracion/tarifas/clinica',
            update: (id: string) => `/configuracion/tarifas/clinica/${id}`,
            destroy: (id: string) => `/configuracion/tarifas/clinica/${id}`,
        };
    }

    return {
        store: '/configuracion/tarifas/hotel',
        update: (id: string) => `/configuracion/tarifas/hotel/${id}`,
        destroy: (id: string) => `/configuracion/tarifas/hotel/${id}`,
    };
}

export function CatalogoClinicaPanel({
    kind,
    rows,
    canCreate,
    canUpdate,
    canDelete,
    routesBase = 'tarifas',
    categoriaOptions: categoriaOptionsProp = [],
}: CatalogoClinicaPanelProps) {
    const { t } = useTranslation(['tarifas-servicios', 'common']);
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<CatalogoClinicaRow | null>(null);
    const [deleteRow, setDeleteRow] = useState<CatalogoClinicaRow | null>(null);
    const [form, setForm] = useState<FormState>(() => emptyForm(kind));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);
    const [insumosServicio, setInsumosServicio] = useState<CatalogoClinicaRow | null>(null);
    const [categoriaOptions, setCategoriaOptions] = useState(categoriaOptionsProp);

    useEffect(() => {
        setCategoriaOptions(categoriaOptionsProp);
    }, [categoriaOptionsProp]);

    const routes = routesFor(kind, routesBase);
    const isGrooming = kind === 'grooming';
    const isClinica = kind === 'clinica';
    const showDuracion = isGrooming || isClinica;

    const openCreate = () => {
        setEditing(null);
        setForm(emptyForm(kind));
        setErrors({});
        setOpen(true);
    };

    const openEdit = (row: CatalogoClinicaRow) => {
        setEditing(row);
        setForm({
            nombre: row.nombre,
            categoria: row.categoria ?? '',
            categoria_id: row.categoria_id ?? null,
            precio_lista: row.precio_lista,
            moneda: row.moneda,
            duracion_minutos: row.duracion_minutos != null ? String(row.duracion_minutos) : '',
            activo: row.activo,
        });
        setErrors({});
        setOpen(true);
    };

    const submit = () => {
        setSubmitting(true);
        const payload: Record<string, unknown> = {
            nombre: form.nombre,
            precio_lista: form.precio_lista,
            moneda: form.moneda,
            activo: form.activo,
        };

        if (isClinica) {
            payload.categoria_id = form.categoria_id;
            payload.duracion_minutos = form.duracion_minutos.trim() === '' ? null : Number(form.duracion_minutos);
        } else {
            payload.categoria = form.categoria || null;
        }

        if (isGrooming) {
            payload.duracion_minutos = Number(form.duracion_minutos) || 60;
        }

        const opts = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                setErrors({});
            },
            onError: (errs: Record<string, string>) => setErrors(errs),
            onFinish: () => setSubmitting(false),
        };

        if (editing) {
            router.put(routes.update(editing.id), payload, opts);
        } else {
            router.post(routes.store, payload, opts);
        }
    };

    const confirmDelete = () => {
        if (!deleteRow) {
            return;
        }

        router.delete(routes.destroy(deleteRow.id), {
            preserveScroll: true,
            onSuccess: () => setDeleteRow(null),
        });
    };

    const titleKey = isGrooming
        ? 'catalogo.grooming_title'
        : isClinica
          ? 'catalogo.clinica_title'
          : 'catalogo.hotel_title';
    const descriptionKey = isGrooming
        ? 'catalogo.grooming_description'
        : isClinica
          ? 'catalogo.clinica_description'
          : 'catalogo.hotel_description';
    const addKey = isGrooming
        ? 'catalogo.add_grooming'
        : isClinica
          ? 'catalogo.add_clinica'
          : 'catalogo.add_hotel';
    const emptyKey = isGrooming
        ? 'catalogo.empty_grooming'
        : isClinica
          ? 'catalogo.empty_clinica'
          : 'catalogo.empty_hotel';
    const createTitleKey = isGrooming
        ? 'catalogo.create_grooming'
        : isClinica
          ? 'catalogo.create_clinica'
          : 'catalogo.create_hotel';
    const formDescKey = isGrooming
        ? 'catalogo.form_description_grooming'
        : isClinica
          ? 'catalogo.form_description_clinica'
          : 'catalogo.form_description_hotel';

    const columns = useMemo<DataTableColumn<CatalogoClinicaRow>[]>(() => {
        const base: DataTableColumn<CatalogoClinicaRow>[] = [
            {
                key: 'activo',
                header: t('columns.activo'),
                className: 'w-28',
                cell: (row) =>
                    row.activo ? (
                        <StatBadge label={t('common:filters.active')} value="" variant="success" />
                    ) : (
                        <StatBadge label={t('common:filters.inactive')} value="" variant="muted" />
                    ),
            },
            {
                key: 'nombre',
                header: kind === 'hotel' ? t('columns.tipo') : t('columns.servicio'),
                cell: (row) => (
                    <div className="flex min-w-0 flex-col gap-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="font-medium text-foreground">{row.nombre}</span>
                            {row.codigo_legacy ? (
                                <Badge variant="outline" className="text-[0.65rem] font-normal">
                                    {t('catalogo.badge_plantilla')}
                                </Badge>
                            ) : null}
                        </div>
                        {row.categoria ? (
                            <span className="text-xs text-muted-foreground">{row.categoria}</span>
                        ) : null}
                    </div>
                ),
            },
            {
                key: 'precio',
                header: t('columns.precio'),
                className: 'w-36',
                cell: (row) => (
                    <span className="tabular-nums font-semibold">{formatPrecio(row.precio_lista, row.moneda)}</span>
                ),
            },
        ];

        if (showDuracion) {
            base.push({
                key: 'duracion',
                header: t('catalogo.columns.duracion'),
                className: 'w-28',
                cell: (row) => (row.duracion_minutos != null ? `${row.duracion_minutos} min` : '—'),
            });
        }

        if (isGrooming) {
            base.push({
                key: 'insumos',
                header: t('insumos.column'),
                className: 'w-52',
                cell: (row) => {
                    const count = row.insumos_count ?? 0;
                    const total = Number(row.insumos_total ?? 0) || 0;

                    return (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setInsumosServicio(row)}
                            className="h-auto cursor-pointer justify-start gap-2 py-1.5"
                        >
                            <Package className="size-4 text-muted-foreground" />
                            <span className="flex flex-col items-start leading-tight">
                                <span className="text-xs font-medium">{t('insumos.count', { count })}</span>
                                {count > 0 ? (
                                    <span className="text-[0.7rem] tabular-nums text-muted-foreground">
                                        {formatPrecio(String(total), row.moneda)}
                                    </span>
                                ) : (
                                    <span className="text-[0.7rem] text-muted-foreground">
                                        {t('insumos.assign_cta')}
                                    </span>
                                )}
                            </span>
                        </Button>
                    );
                },
            });
        }

        if (canUpdate || canDelete) {
            base.push({
                key: 'acciones',
                header: <span className="md:sr-only">{t('columns.acciones')}</span>,
                align: 'right',
                className: 'w-24',
                cell: (row) => (
                    <TarifaRowActions
                        canUpdate={canUpdate}
                        canDelete={canDelete}
                        onEdit={() => openEdit(row)}
                        onDelete={() => setDeleteRow(row)}
                    />
                ),
            });
        }

        return base;
    }, [t, kind, showDuracion, isGrooming, canUpdate, canDelete]);

    return (
        <>
            <div
                className={cn(
                    'flex flex-col gap-4 border-b border-border/60 px-4 py-4 sm:px-6',
                    isClinica && 'bg-linear-to-br from-sky-500/[0.07] via-transparent to-transparent',
                )}
            >
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex gap-3">
                        {isClinica ? (
                            <div className="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl bg-sky-500/12 text-sky-700 dark:text-sky-300">
                                <Stethoscope className="size-5" strokeWidth={1.75} />
                            </div>
                        ) : null}
                        <div className="min-w-0 space-y-1">
                            <h2 className="text-base font-semibold text-foreground">{t(titleKey)}</h2>
                            <p className="max-w-2xl text-sm text-muted-foreground">{t(descriptionKey)}</p>
                        </div>
                    </div>
                    {canCreate ? (
                        <Button
                            type="button"
                            className="h-10 w-full cursor-pointer gap-2 sm:h-9 sm:w-auto sm:self-start"
                            onClick={openCreate}
                        >
                            <Plus className="size-4" strokeWidth={2.5} />
                            {t(addKey)}
                        </Button>
                    ) : null}
                </div>
            </div>

            {isClinica ? (
                <ServiciosClinicosList
                    rows={rows}
                    canCreate={canCreate}
                    canUpdate={canUpdate}
                    canDelete={canDelete}
                    onCreate={openCreate}
                    onEdit={openEdit}
                    onDelete={setDeleteRow}
                />
            ) : rows.length === 0 ? (
                <p className="px-4 py-10 text-center text-sm text-muted-foreground sm:px-6">{t(emptyKey)}</p>
            ) : (
                <DataTable
                    columns={columns}
                    data={[...rows]}
                    rowKey={(row) => row.id}
                    ariaLiveMessage={t('catalogo.count', { count: rows.length })}
                />
            )}

            <FormModal
                open={open}
                onOpenChange={setOpen}
                size="md"
                title={editing ? t('catalogo.edit_title') : t(createTitleKey)}
                description={t(formDescKey)}
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                footer={
                    <>
                        <Button type="button" variant="outline" disabled={submitting} onClick={() => setOpen(false)}>
                            {t('form.cancelar')}
                        </Button>
                        <Button
                            type="submit"
                            disabled={submitting || form.nombre.trim() === '' || form.precio_lista.trim() === ''}
                            className="gap-2"
                        >
                            {submitting ? <Loader2 className="size-4 animate-spin" /> : null}
                            {t('form.guardar')}
                        </Button>
                    </>
                }
            >
                <div className="grid gap-5">
                    <FormField id="cat-nombre" label={t('catalogo.form.nombre')} error={errors.nombre} required>
                        <Input
                            id="cat-nombre"
                            value={form.nombre}
                            onChange={(e) => setForm((f) => ({ ...f, nombre: e.target.value }))}
                        />
                    </FormField>

                    {isClinica ? (
                        <FormField
                            id="cat-categoria"
                            label={t('catalogo.form.categoria')}
                            error={errors.categoria_id ?? errors.categoria}
                        >
                            <CategoriaServicioClinicoCombobox
                                id="cat-categoria"
                                value={form.categoria_id}
                                onChange={(categoriaId) => setForm((f) => ({ ...f, categoria_id: categoriaId }))}
                                options={categoriaOptions}
                                onOptionsChange={setCategoriaOptions}
                                canCreate={canCreate || canUpdate}
                                aria-invalid={Boolean(errors.categoria_id ?? errors.categoria)}
                            />
                        </FormField>
                    ) : (
                        <FormField id="cat-categoria" label={t('catalogo.form.categoria')} error={errors.categoria}>
                            <Input
                                id="cat-categoria"
                                value={form.categoria}
                                onChange={(e) => setForm((f) => ({ ...f, categoria: e.target.value }))}
                                placeholder={t('catalogo.form.categoria_placeholder')}
                            />
                        </FormField>
                    )}

                    <div className="grid gap-5 sm:grid-cols-2">
                        <FormField id="cat-precio" label={t('form.precio_lista')} error={errors.precio_lista} required>
                            <Input
                                id="cat-precio"
                                type="number"
                                min={0}
                                step="0.01"
                                value={form.precio_lista}
                                onChange={(e) => setForm((f) => ({ ...f, precio_lista: e.target.value }))}
                                className="tabular-nums"
                            />
                        </FormField>

                        <FormField id="cat-moneda" label={t('form.moneda')} error={errors.moneda} required>
                            <Combobox
                                id="cat-moneda"
                                options={MONEDA_OPTIONS}
                                value={form.moneda || null}
                                onChange={(value) => setForm((f) => ({ ...f, moneda: value ?? 'PEN' }))}
                                clearable={false}
                            />
                        </FormField>
                    </div>

                    {showDuracion ? (
                        <FormField
                            id="cat-duracion"
                            label={t('catalogo.form.duracion')}
                            error={errors.duracion_minutos}
                            required={isGrooming}
                        >
                            <Input
                                id="cat-duracion"
                                type="number"
                                min={5}
                                max={480}
                                value={form.duracion_minutos}
                                onChange={(e) => setForm((f) => ({ ...f, duracion_minutos: e.target.value }))}
                                placeholder={isClinica ? t('catalogo.form.duracion_optional') : undefined}
                            />
                        </FormField>
                    ) : null}

                    <FormField id="cat-activo" label={t('form.estado')}>
                        <label
                            htmlFor="cat-activo"
                            className="flex cursor-pointer items-center gap-3 rounded-lg border border-border/60 bg-muted/25 px-4 py-3 text-sm"
                        >
                            <Checkbox
                                id="cat-activo"
                                checked={form.activo}
                                onCheckedChange={(checked) => setForm((f) => ({ ...f, activo: checked === true }))}
                            />
                            <span>{t('catalogo.form.activo')}</span>
                        </label>
                    </FormField>
                </div>
            </FormModal>

            <TarifaDeleteDialog
                open={deleteRow !== null}
                onOpenChange={(openDialog) => !openDialog && setDeleteRow(null)}
                kind={kind}
                tarifa={null}
                nombre={deleteRow?.nombre ?? ''}
                onConfirm={confirmDelete}
            />

            {isGrooming ? (
                <GroomingInsumosModal
                    open={insumosServicio !== null}
                    onOpenChange={(openModal) => !openModal && setInsumosServicio(null)}
                    servicio={
                        insumosServicio
                            ? {
                                  id: insumosServicio.id,
                                  nombre: insumosServicio.nombre,
                                  moneda: insumosServicio.moneda,
                              }
                            : null
                    }
                    canUpdate={canUpdate}
                />
            ) : null}
        </>
    );
}
