import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection } from '@/components/forms';
import {
    GeoCascadeFields,
    type GeoCascadeValue,
} from '@/components/geo/geo-cascade-fields';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ClinicaAsesorada, GeoOption } from '../types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    clinica: ClinicaAsesorada | null;
    departamentos: readonly GeoOption[];
};

type FormData = {
    nombre: string;
    ruc: string;
    direccion: string;
    distrito_id: number | null;
    activo: boolean;
};

const emptyForm: FormData = {
    nombre: '',
    ruc: '',
    direccion: '',
    distrito_id: null,
    activo: true,
};

function initialGeo(clinica: ClinicaAsesorada | null): GeoCascadeValue {
    if (!clinica?.distrito_id) {
        return { departamento_id: null, provincia_id: null, distrito_id: null };
    }

    return {
        departamento_id: null,
        provincia_id: null,
        distrito_id: clinica.distrito_id,
    };
}

export function ClinicaAsesoradaFormModal({
    open,
    onOpenChange,
    clinica,
    departamentos,
}: Props) {
    const { t } = useTranslation(['clinicas-asesoradas', 'common']);
    const isEdit = clinica !== null;
    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<FormData>(emptyForm);
    const [geo, setGeo] = useState<GeoCascadeValue>(initialGeo(null));

    useEffect(() => {
        if (!open) {
            return;
        }
        clearErrors();
        if (clinica) {
            setData({
                nombre: clinica.nombre,
                ruc: clinica.ruc ?? '',
                direccion: clinica.direccion ?? '',
                distrito_id: clinica.distrito_id,
                activo: clinica.activo,
            });
            setGeo(initialGeo(clinica));
        } else {
            reset();
            setData(emptyForm);
            setGeo(initialGeo(null));
        }
    }, [open, clinica, clearErrors, reset, setData]);

    const canSave = useMemo(() => data.nombre.trim() !== '', [data.nombre]);

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        if (!canSave) {
            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (isEdit && clinica) {
            put(`/clinica/clinicas-asesoradas/${clinica.id}`, options);
            return;
        }

        post('/clinica/clinicas-asesoradas', {
            ...options,
            onSuccess: () => {
                onOpenChange(false);
                reset();
            },
        });
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={
                isEdit
                    ? t('form.edit_title')
                    : t('form.create_title')
            }
            description={t('form.description')}
            size="lg"
            onSubmit={onSubmit}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={processing}
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" disabled={processing || !canSave}>
                        {processing ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : null}
                        {t('common:actions.save')}
                    </Button>
                </>
            }
        >
            <FormSection index={0} title="" columns={2} className="gap-0">
                <FormField
                    label={t('form.nombre')}
                    error={errors.nombre}
                    required
                    className="sm:col-span-2"
                >
                    <Input
                        value={data.nombre}
                        onChange={(e) => setData('nombre', e.target.value)}
                        maxLength={200}
                    />
                </FormField>
                <FormField label={t('form.ruc')} error={errors.ruc}>
                    <Input
                        value={data.ruc}
                        onChange={(e) =>
                            setData(
                                'ruc',
                                e.target.value.replace(/\D/g, '').slice(0, 11),
                            )
                        }
                        inputMode="numeric"
                        maxLength={11}
                        className="font-mono"
                    />
                </FormField>
                <FormField
                    label={t('form.direccion')}
                    error={errors.direccion}
                    className="sm:col-span-2"
                >
                    <Input
                        value={data.direccion}
                        onChange={(e) => setData('direccion', e.target.value)}
                        maxLength={255}
                    />
                </FormField>
                <div className="sm:col-span-2">
                    <GeoCascadeFields
                        value={geo}
                        onChange={(next) => {
                            setGeo(next);
                            setData('distrito_id', next.distrito_id);
                        }}
                        departamentos={departamentos}
                        errors={{ distrito_id: errors.distrito_id }}
                        labels={{
                            departamento: t('form.departamento'),
                            provincia: t('form.provincia'),
                            distrito: t('form.distrito'),
                        }}
                    />
                </div>
                <div className="flex items-center gap-2 sm:col-span-2">
                    <Checkbox
                        id="clinica-asesorada-activa"
                        checked={data.activo}
                        onCheckedChange={(checked) =>
                            setData('activo', checked === true)
                        }
                    />
                    <Label htmlFor="clinica-asesorada-activa">
                        {t('form.activo')}
                    </Label>
                </div>
            </FormSection>
        </FormModal>
    );
}
