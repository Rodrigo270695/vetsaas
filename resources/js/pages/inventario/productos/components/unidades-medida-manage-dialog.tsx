import { router, useForm } from '@inertiajs/react';
import { Loader2, Pencil, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
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
import { Label } from '@/components/ui/label';
import type { ProductoUnidadOption } from '../types';

type UnidadesMedidaManageDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    unidadOptions: readonly ProductoUnidadOption[];
    canEdit: boolean;
    canCreate: boolean;
    /** Tras crear una unidad, seleccionarla en el formulario de producto. */
    onUnidadMedidaCreated: (codigo: string) => void;
    /** Si se elimina la unidad actualmente seleccionada en el producto. */
    unidadSeleccionadaCodigo: string;
    onUnidadMedidaEliminada: (codigoEliminado: string) => void;
};

export function UnidadesMedidaManageDialog({
    open,
    onOpenChange,
    unidadOptions,
    canEdit,
    canCreate,
    onUnidadMedidaCreated,
    unidadSeleccionadaCodigo,
    onUnidadMedidaEliminada,
}: UnidadesMedidaManageDialogProps) {
    const { t } = useTranslation(['productos-inventario', 'common']);

    const personalizadas = useMemo(
        () => unidadOptions.filter((u) => !u.es_sistema).sort((a, b) => a.nombre.localeCompare(b.nombre, 'es')),
        [unidadOptions],
    );

    const createForm = useForm({ nombre: '', codigo: '' });
    const editForm = useForm({ nombre: '' });

    const [editingId, setEditingId] = useState<string | null>(null);
    const [pendingDelete, setPendingDelete] = useState<ProductoUnidadOption | null>(null);
    const [deleting, setDeleting] = useState(false);

    const resetForms = () => {
        createForm.reset();
        createForm.clearErrors();
        editForm.reset();
        editForm.clearErrors();
        setEditingId(null);
        setPendingDelete(null);
    };

    const handleOpenChange = (next: boolean) => {
        if (!next) {
            resetForms();
        }
        onOpenChange(next);
    };

    const startEdit = (u: ProductoUnidadOption) => {
        setEditingId(u.id);
        editForm.setData('nombre', u.nombre);
        editForm.clearErrors();
        setPendingDelete(null);
    };

    const cancelEdit = () => {
        setEditingId(null);
        editForm.reset();
        editForm.clearErrors();
    };

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        const antes = new Set(unidadOptions.map((o) => o.codigo));

        createForm.post('/inventario/unidades-medida', {
            preserveScroll: true,
            preserveState: true,
            only: ['unidadOptions'],
            onSuccess: (page) => {
                const props = page.props as { unidadOptions?: ProductoUnidadOption[] };
                const next = props.unidadOptions ?? [];
                const created = next.find((o) => !antes.has(o.codigo));
                if (created) {
                    onUnidadMedidaCreated(created.codigo);
                }
                createForm.reset();
                createForm.clearErrors();
            },
        });
    };

    const submitEdit = (e: React.FormEvent, id: string) => {
        e.preventDefault();
        editForm.patch(`/inventario/unidades-medida/${id}`, {
            preserveScroll: true,
            preserveState: true,
            only: ['unidadOptions'],
            onSuccess: () => {
                cancelEdit();
            },
        });
    };

    const runDelete = () => {
        if (!pendingDelete) {
            return;
        }
        const eliminada = pendingDelete;
        setDeleting(true);
        router.delete(`/inventario/unidades-medida/${eliminada.id}`, {
            preserveScroll: true,
            preserveState: true,
            only: ['unidadOptions'],
            onFinish: () => setDeleting(false),
            onSuccess: () => {
                if (eliminada.codigo === unidadSeleccionadaCodigo) {
                    onUnidadMedidaEliminada(eliminada.codigo);
                }
                setPendingDelete(null);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="text-base">{t('unidades_dialog.title')}</DialogTitle>
                    <DialogDescription className="text-sm">{t('unidades_dialog.description')}</DialogDescription>
                </DialogHeader>

                {canCreate && (
                    <form onSubmit={submitCreate} className="space-y-3 rounded-lg border bg-muted/30 p-3">
                        <p className="text-sm font-medium">{t('unidades_dialog.nueva_title')}</p>
                        <div className="space-y-2">
                            <Label htmlFor="um-nombre">{t('unidades_dialog.nombre')}</Label>
                            <Input
                                id="um-nombre"
                                value={createForm.data.nombre}
                                onChange={(e) => createForm.setData('nombre', e.target.value)}
                                placeholder={t('unidades_dialog.nombre_placeholder')}
                                aria-invalid={Boolean(createForm.errors.nombre)}
                            />
                            {createForm.errors.nombre && (
                                <p className="text-destructive text-xs">{createForm.errors.nombre}</p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="um-codigo">{t('unidades_dialog.codigo_opcional')}</Label>
                            <Input
                                id="um-codigo"
                                value={createForm.data.codigo}
                                onChange={(e) => createForm.setData('codigo', e.target.value.toUpperCase())}
                                placeholder={t('unidades_dialog.codigo_placeholder')}
                                maxLength={20}
                                aria-invalid={Boolean(createForm.errors.codigo)}
                            />
                            {createForm.errors.codigo && (
                                <p className="text-destructive text-xs">{createForm.errors.codigo}</p>
                            )}
                            <p className="text-muted-foreground text-xs">{t('unidades_dialog.codigo_hint')}</p>
                        </div>
                        <Button type="submit" size="sm" disabled={createForm.processing} className="gap-2">
                            {createForm.processing && <Loader2 className="size-4 animate-spin" />}
                            {t('unidades_dialog.crear')}
                        </Button>
                    </form>
                )}

                <div className="space-y-2">
                    <p className="text-sm font-medium">{t('unidades_dialog.lista_title')}</p>
                    {personalizadas.length === 0 ? (
                        <p className="text-muted-foreground text-sm">{t('unidades_dialog.lista_vacia')}</p>
                    ) : (
                        <ul className="space-y-2">
                            {personalizadas.map((u) => (
                                <li key={u.id} className="rounded-lg border p-3">
                                    {editingId === u.id ? (
                                        <form onSubmit={(e) => submitEdit(e, u.id)} className="space-y-2">
                                            <Label htmlFor={`um-edit-${u.id}`}>{t('unidades_dialog.nombre')}</Label>
                                            <Input
                                                id={`um-edit-${u.id}`}
                                                value={editForm.data.nombre}
                                                onChange={(e) => editForm.setData('nombre', e.target.value)}
                                                aria-invalid={Boolean(editForm.errors.nombre)}
                                            />
                                            {editForm.errors.nombre && (
                                                <p className="text-destructive text-xs">{editForm.errors.nombre}</p>
                                            )}
                                            <div className="flex flex-wrap gap-2">
                                                <Button type="submit" size="sm" disabled={editForm.processing} className="gap-1">
                                                    {editForm.processing && <Loader2 className="size-3.5 animate-spin" />}
                                                    {t('common:actions.save')}
                                                </Button>
                                                <Button type="button" size="sm" variant="outline" onClick={cancelEdit}>
                                                    {t('common:actions.cancel')}
                                                </Button>
                                            </div>
                                        </form>
                                    ) : (
                                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <div className="min-w-0">
                                                <div className="truncate font-medium">{u.nombre}</div>
                                                <div className="text-muted-foreground font-mono text-xs">{u.codigo}</div>
                                            </div>
                                            {canEdit && (
                                                <div className="flex shrink-0 gap-1">
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        className="size-8"
                                                        onClick={() => startEdit(u)}
                                                        aria-label={t('unidades_dialog.editar')}
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        className="text-destructive hover:text-destructive size-8"
                                                        onClick={() => setPendingDelete(u)}
                                                        aria-label={t('unidades_dialog.eliminar')}
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                {pendingDelete && (
                    <div className="rounded-lg border border-destructive/30 bg-destructive/5 p-3">
                        <p className="text-sm">{t('unidades_dialog.confirm_delete', { nombre: pendingDelete.nombre })}</p>
                        <div className="mt-2 flex flex-wrap gap-2">
                            <Button type="button" size="sm" variant="destructive" disabled={deleting} onClick={runDelete} className="gap-1">
                                {deleting && <Loader2 className="size-3.5 animate-spin" />}
                                {t('common:actions.delete')}
                            </Button>
                            <Button type="button" size="sm" variant="outline" disabled={deleting} onClick={() => setPendingDelete(null)}>
                                {t('common:actions.cancel')}
                            </Button>
                        </div>
                    </div>
                )}

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => handleOpenChange(false)}>
                        {t('common:actions.close')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
