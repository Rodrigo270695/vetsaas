import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useMemo, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { enqueueIfOffline } from '@/lib/offline/enqueue-if-offline';
import { useOfflineSync } from '@/hooks/use-offline-sync';
import type { CategoriaParentOption, CategoriaProducto } from '../types';

type CategoriaFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    categoria: CategoriaProducto | null;
    parentOptions: readonly CategoriaParentOption[];
};

type FormData = {
    parent_id: string | null;
    nombre: string;
    slug: string;
    descripcion: string;
    orden: number;
    activo: boolean;
};

const empty: FormData = {
    parent_id: null,
    nombre: '',
    slug: '',
    descripcion: '',
    orden: 0,
    activo: true,
};

export function CategoriaFormModal({ open, onOpenChange, categoria, parentOptions }: CategoriaFormModalProps) {
    const { t } = useTranslation(['categorias-inventario', 'common', 'offline']);
    const { refreshPending } = useOfflineSync();
    const isEdit = categoria !== null;

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm<FormData>(empty);

    useEffect(() => {
        if (!open) {
            return;
        }
        if (!categoria) {
            reset();
            clearErrors();
            return;
        }
        setData({
            parent_id: categoria.parent_id,
            nombre: categoria.nombre,
            slug: categoria.slug ?? '',
            descripcion: categoria.descripcion ?? '',
            orden: categoria.orden,
            activo: categoria.activo,
        });
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, categoria?.id]);

    const allowedParents = useMemo(
        () => parentOptions.filter((p) => p.id !== categoria?.id),
        [parentOptions, categoria?.id],
    );

    const onSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        const onSuccess = () => {
            onOpenChange(false);
            reset();
            clearErrors();
        };

        const payload = {
            parent_id: data.parent_id,
            nombre: data.nombre.trim(),
            slug: data.slug.trim().toLowerCase() || null,
            descripcion: data.descripcion.trim() === '' ? null : data.descripcion.trim(),
            orden: data.orden,
            activo: data.activo,
        };

        if (isEdit && categoria) {
            put(`/inventario/categorias/${categoria.id}`, { preserveScroll: true, onSuccess });

            return;
        }

        void (async () => {
            const queued = await enqueueIfOffline('inventario.categoria.create', payload, {
                refreshPending,
                onSuccess,
                title: t('offline:categoria.queued_title'),
                description: t('offline:categoria.queued_body'),
            });

            if (queued) {
                return;
            }

            post('/inventario/categorias', { preserveScroll: true, onSuccess });
        })();
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('form.title_edit') : t('form.title_create')}
            description={t('description')}
            onSubmit={onSubmit}
            footer={
                <>
                    <Button type="button" variant="outline" disabled={processing} onClick={() => onOpenChange(false)}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" disabled={processing || data.nombre.trim() === ''} className="gap-2">
                        {processing && <Loader2 className="size-4 animate-spin" />}
                        {isEdit ? t('form.submit_edit') : t('form.submit_create')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4">
                <FormField id="cat-nombre" label={t('form.nombre')} error={errors.nombre} required>
                    <Input id="cat-nombre" value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} />
                </FormField>

                <FormField id="cat-parent" label={t('form.parent')}>
                    <Select value={data.parent_id ?? '__none__'} onValueChange={(v) => setData('parent_id', v === '__none__' ? null : v)}>
                        <SelectTrigger id="cat-parent">
                            <SelectValue placeholder={t('form.parent_placeholder')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__none__">{t('form.parent_none')}</SelectItem>
                            {allowedParents.map((p) => (
                                <SelectItem key={p.id} value={p.id}>
                                    {p.nombre}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField id="cat-slug" label={t('form.slug')} error={errors.slug}>
                        <Input id="cat-slug" value={data.slug} onChange={(e) => setData('slug', e.target.value)} />
                    </FormField>
                    <FormField id="cat-orden" label={t('form.orden')} error={errors.orden}>
                        <Input
                            id="cat-orden"
                            type="number"
                            min={0}
                            value={String(data.orden)}
                            onChange={(e) => setData('orden', Number(e.target.value || 0))}
                        />
                    </FormField>
                </div>

                <FormField id="cat-descripcion" label={t('form.descripcion')} error={errors.descripcion}>
                    <Textarea
                        id="cat-descripcion"
                        value={data.descripcion}
                        onChange={(e) => setData('descripcion', e.target.value)}
                        rows={3}
                    />
                </FormField>

                <FormField id="cat-activo" label={t('form.estado')}>
                    <label htmlFor="cat-activo" className="flex items-center gap-3 text-sm">
                        <Checkbox
                            id="cat-activo"
                            checked={data.activo}
                            onCheckedChange={(checked) => setData('activo', Boolean(checked))}
                        />
                        <span>{t('form.activo_label')}</span>
                    </label>
                </FormField>
            </div>
        </FormModal>
    );
}
