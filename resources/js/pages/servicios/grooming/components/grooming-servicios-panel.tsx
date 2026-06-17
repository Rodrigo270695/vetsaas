import { router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
import { DataTable } from '@/components/data-page';
import type { DataTableColumn } from '@/components/data-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import type { GroomingServicioRow } from '../types';

type Props = {
    servicios: readonly GroomingServicioRow[];
};

type FormState = {
    nombre: string;
    categoria: string;
    precio_lista: string;
    duracion_minutos: string;
    activo: boolean;
};

const emptyForm = (): FormState => ({
    nombre: '',
    categoria: '',
    precio_lista: '',
    duracion_minutos: '60',
    activo: true,
});

export function GroomingServiciosPanel({ servicios }: Props) {
    const { t } = useTranslation(['grooming', 'common']);
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<GroomingServicioRow | null>(null);
    const [form, setForm] = useState<FormState>(emptyForm);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);
    const [deletingId, setDeletingId] = useState<string | null>(null);

    const openCreate = () => {
        setEditing(null);
        setForm(emptyForm());
        setErrors({});
        setOpen(true);
    };

    const openEdit = (row: GroomingServicioRow) => {
        setEditing(row);
        setForm({
            nombre: row.nombre,
            categoria: row.categoria ?? '',
            precio_lista: row.precio_lista,
            duracion_minutos: String(row.duracion_minutos),
            activo: row.activo,
        });
        setErrors({});
        setOpen(true);
    };

    const submit = () => {
        setSubmitting(true);
        const payload = {
            nombre: form.nombre,
            categoria: form.categoria || null,
            precio_lista: form.precio_lista,
            duracion_minutos: Number(form.duracion_minutos) || 60,
            activo: form.activo,
        };

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
            router.put(`/servicios/grooming/servicios/${editing.id}`, payload, opts);
        } else {
            router.post('/servicios/grooming/servicios', payload, opts);
        }
    };

    const destroy = (row: GroomingServicioRow) => {
        if (!window.confirm(t('servicios.delete_confirm', { nombre: row.nombre }))) {
            return;
        }

        setDeletingId(row.id);
        router.delete(`/servicios/grooming/servicios/${row.id}`, {
            preserveScroll: true,
            onFinish: () => setDeletingId(null),
        });
    };

    const columns = useMemo<DataTableColumn<GroomingServicioRow>[]>(
        () => [
            {
                key: 'nombre',
                header: t('servicios.columns.nombre'),
                cell: (row) => (
                    <div className="flex min-w-0 flex-col gap-0.5">
                        <span className="font-medium">{row.nombre}</span>
                        {row.categoria ? (
                            <span className="text-xs text-muted-foreground">{row.categoria}</span>
                        ) : null}
                    </div>
                ),
            },
            {
                key: 'precio',
                header: t('servicios.columns.precio'),
                cell: (row) => {
                    const n = Number(row.precio_lista);
                    const cur = row.moneda === 'USD' ? 'USD' : 'PEN';

                    return Number.isNaN(n)
                        ? row.precio_lista
                        : new Intl.NumberFormat(undefined, { style: 'currency', currency: cur }).format(n);
                },
            },
            {
                key: 'duracion',
                header: t('servicios.columns.duracion'),
                cell: (row) => `${row.duracion_minutos} min`,
            },
            {
                key: 'activo',
                header: t('servicios.columns.activo'),
                cell: (row) => (
                    <Badge variant={row.activo ? 'default' : 'secondary'}>
                        {row.activo ? t('servicios.activo_si') : t('servicios.activo_no')}
                    </Badge>
                ),
            },
            {
                key: 'acciones',
                header: t('columns.acciones'),
                align: 'right',
                cell: (row) => (
                    <div className="flex justify-end gap-1">
                        <Can permission="grooming.update">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-8"
                                onClick={() => openEdit(row)}
                            >
                                <Pencil className="size-4" aria-hidden />
                            </Button>
                        </Can>
                        <Can permission="grooming.delete">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-8 text-destructive"
                                disabled={deletingId === row.id}
                                onClick={() => destroy(row)}
                            >
                                <Trash2 className="size-4" aria-hidden />
                            </Button>
                        </Can>
                    </div>
                ),
            },
        ],
        [deletingId, t],
    );

    return (
        <>
            <Card className="border-border/60">
                <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3 pb-3">
                    <div>
                        <CardTitle className="text-base">{t('servicios.title')}</CardTitle>
                        <CardDescription>{t('servicios.description')}</CardDescription>
                    </div>
                    <Can permission="grooming.create">
                        <Button type="button" size="sm" className="gap-1.5" onClick={openCreate}>
                            <Plus className="size-4" aria-hidden />
                            {t('servicios.add')}
                        </Button>
                    </Can>
                </CardHeader>
                <CardContent className="px-0 pb-0">
                    {servicios.length === 0 ? (
                        <p className="px-4 pb-4 text-sm text-muted-foreground">{t('servicios.empty')}</p>
                    ) : (
                        <DataTable
                            columns={columns}
                            data={[...servicios]}
                            rowKey={(row) => row.id}
                            ariaLiveMessage={t('servicios.count', { count: servicios.length })}
                        />
                    )}
                </CardContent>
            </Card>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? t('servicios.edit_title') : t('servicios.create_title')}
                        </DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="gs-nombre">{t('servicios.form.nombre')}</Label>
                            <Input
                                id="gs-nombre"
                                value={form.nombre}
                                onChange={(e) => setForm((f) => ({ ...f, nombre: e.target.value }))}
                            />
                            {errors.nombre ? (
                                <p className="text-xs text-destructive">{errors.nombre}</p>
                            ) : null}
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="gs-categoria">{t('servicios.form.categoria')}</Label>
                            <Input
                                id="gs-categoria"
                                value={form.categoria}
                                onChange={(e) => setForm((f) => ({ ...f, categoria: e.target.value }))}
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-1.5">
                                <Label htmlFor="gs-precio">{t('servicios.form.precio')}</Label>
                                <Input
                                    id="gs-precio"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={form.precio_lista}
                                    onChange={(e) => setForm((f) => ({ ...f, precio_lista: e.target.value }))}
                                />
                                {errors.precio_lista ? (
                                    <p className="text-xs text-destructive">{errors.precio_lista}</p>
                                ) : null}
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="gs-duracion">{t('servicios.form.duracion')}</Label>
                                <Input
                                    id="gs-duracion"
                                    type="number"
                                    min={5}
                                    max={480}
                                    value={form.duracion_minutos}
                                    onChange={(e) =>
                                        setForm((f) => ({ ...f, duracion_minutos: e.target.value }))
                                    }
                                />
                            </div>
                        </div>
                        <div className="flex items-center gap-2 rounded-md border border-border/60 px-3 py-2">
                            <Checkbox
                                id="gs-activo"
                                checked={form.activo}
                                onCheckedChange={(v) => setForm((f) => ({ ...f, activo: v === true }))}
                            />
                            <Label htmlFor="gs-activo">{t('servicios.form.activo')}</Label>
                        </div>
                    </div>
                    <DialogFooter className="gap-2">
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            {t('common:actions.cancel')}
                        </Button>
                        <Button type="button" disabled={submitting} onClick={submit}>
                            {t('common:actions.save')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
