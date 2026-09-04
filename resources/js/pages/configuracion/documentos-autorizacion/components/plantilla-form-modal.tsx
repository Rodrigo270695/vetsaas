import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

export type PlantillaAutorizacion = {
    id: string;
    nombre: string;
    descripcion: string | null;
    cuerpo: string;
    activo: boolean;
};

const VARS = ['paciente', 'propietario', 'documento', 'fecha', 'clinica', 'veterinario'] as const;

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    plantilla: PlantillaAutorizacion | null;
    cuerpoDefault: string;
};

export function DocumentoAutorizacionPlantillaFormModal({
    open,
    onOpenChange,
    plantilla,
    cuerpoDefault,
}: Props) {
    const { t } = useTranslation(['documentos-autorizacion', 'common']);
    const isEdit = plantilla !== null;
    const form = useForm({
        nombre: '',
        descripcion: '',
        cuerpo: cuerpoDefault,
        activo: true,
    });

    useEffect(() => {
        if (!open) {
            return;
        }
        form.setData({
            nombre: plantilla?.nombre ?? '',
            descripcion: plantilla?.descripcion ?? '',
            cuerpo: plantilla?.cuerpo ?? cuerpoDefault,
            activo: plantilla?.activo ?? true,
        });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, plantilla?.id, cuerpoDefault]);

    const insertVar = (key: string) => {
        form.setData('cuerpo', `${form.data.cuerpo}{{${key}}}`);
    };

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();
        const opts = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };
        if (isEdit && plantilla) {
            form.put(`/configuracion/documentos-autorizacion/${plantilla.id}`, opts);
            return;
        }
        form.post('/configuracion/documentos-autorizacion', opts);
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('title_edit') : t('title_create')}
            size="xl"
            onSubmit={onSubmit}
            footer={
                <>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" disabled={form.processing} className="gap-2">
                        {form.processing ? <Loader2 className="size-4 animate-spin" /> : null}
                        {isEdit ? t('submit_edit') : t('submit_create')}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-4">
                <FormField id="tpl-nombre" label={t('nombre')} required error={form.errors.nombre}>
                    <Input
                        id="tpl-nombre"
                        value={form.data.nombre}
                        onChange={(e) => form.setData('nombre', e.target.value)}
                    />
                </FormField>
                <FormField id="tpl-desc" label={t('descripcion_field')} error={form.errors.descripcion}>
                    <Input
                        id="tpl-desc"
                        value={form.data.descripcion}
                        onChange={(e) => form.setData('descripcion', e.target.value)}
                    />
                </FormField>
                <FormField
                    id="tpl-cuerpo"
                    label={t('cuerpo')}
                    required
                    error={form.errors.cuerpo}
                    hint={t('cuerpo_hint')}
                >
                    <div className="mb-2 flex flex-wrap gap-1.5">
                        {VARS.map((key) => (
                            <Button
                                key={key}
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-7 cursor-pointer px-2 text-xs"
                                onClick={() => insertVar(key)}
                            >
                                {key}
                            </Button>
                        ))}
                    </div>
                    <Textarea
                        id="tpl-cuerpo"
                        rows={12}
                        value={form.data.cuerpo}
                        onChange={(e) => form.setData('cuerpo', e.target.value)}
                    />
                </FormField>
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                        checked={form.data.activo}
                        onCheckedChange={(c) => form.setData('activo', c === true)}
                    />
                    {t('activo')}
                </label>
            </div>
        </FormModal>
    );
}
