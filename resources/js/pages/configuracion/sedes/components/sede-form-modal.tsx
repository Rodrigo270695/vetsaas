import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection } from '@/components/forms';
import {
    GeoCascadeFields,
    type GeoCascadeValue,
} from '@/components/geo/geo-cascade-fields';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import sedes from '@/routes/configuracion/sedes';
import type { GeoOption, Sede } from '../types';

export type SedeFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Sede a editar; si es `null` el modal se abre en modo crear. */
    sede: Sede | null;
    /** Catálogo de departamentos pre-cargado desde el index. */
    departamentos: readonly GeoOption[];
};

type SedeFormData = {
    nombre: string;
    direccion: string;
    telefono: string;
    email: string;
    /** Único campo geográfico que se envía: la FK al distrito. */
    distrito_id: number | null;
    serie_factura: string;
    serie_boleta: string;
    activa: boolean;
};

const emptyForm: SedeFormData = {
    nombre: '',
    direccion: '',
    telefono: '',
    email: '',
    distrito_id: null,
    serie_factura: '',
    serie_boleta: '',
    activa: true,
};

const buildInitialData = (sede: Sede | null): SedeFormData => ({
    nombre: sede?.nombre ?? '',
    direccion: sede?.direccion ?? '',
    telefono: sede?.telefono ?? '',
    email: sede?.email ?? '',
    distrito_id: sede?.distrito_id ?? null,
    serie_factura: sede?.serie_factura ?? '',
    serie_boleta: sede?.serie_boleta ?? '',
    activa: sede?.activa ?? true,
});

/**
 * Deriva la cadena (departamento_id, provincia_id) inicial a partir de
 * la sede en edición. Si la sede trae `distrito_model` (eager loaded),
 * usamos sus IDs; caso contrario quedan en null y el usuario re-elige.
 */
const buildInitialGeoValue = (sede: Sede | null): GeoCascadeValue => {
    if (!sede || !sede.distrito_model) {
        return {
            departamento_id: null,
            provincia_id: null,
            distrito_id: sede?.distrito_id ?? null,
        };
    }

    return {
        departamento_id: sede.distrito_model.provincia.departamento_id,
        provincia_id: sede.distrito_model.provincia_id,
        distrito_id: sede.distrito_model.id,
    };
};

/**
 * Campos obligatorios mínimos para habilitar el botón submit.
 */
const isFormValid = (data: SedeFormData): boolean => {
    return data.nombre.trim().length > 0;
};

/**
 * Modal de crear/editar sede.
 *
 * - Si `sede === null` → modo "Nueva sede" (POST a `sedes.store`).
 * - Si `sede` viene → modo "Editar sede" (PUT a `sedes.update`).
 * - Usa `useForm` de Inertia para manejar state, errores y processing.
 * - La ubicación es una cascada Departamento → Provincia → Distrito.
 *   Solo `distrito_id` se envía al backend; los strings denormalizados
 *   los hidrata `SedeController` desde el catálogo.
 */
export function SedeFormModal({
    open,
    onOpenChange,
    sede,
    departamentos,
}: SedeFormModalProps) {
    const { t } = useTranslation(['sedes', 'common']);
    const isEdit = sede !== null;

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<SedeFormData>(emptyForm);

    /*
     * Estado de la cascada geográfica. Los campos `departamento_id` y
     * `provincia_id` NO se envían al backend; sirven solo para filtrar
     * las opciones del siguiente nivel. El único valor persistido es
     * `geo.distrito_id`, que se sincroniza con `data.distrito_id`.
     */
    const [geo, setGeo] = useState<GeoCascadeValue>(() =>
        buildInitialGeoValue(null),
    );

    const canSubmit = isFormValid(data) && !processing;

    const initialSnapshotRef = useRef<SedeFormData>(emptyForm);

    useEffect(() => {
        if (open) {
            const initial = buildInitialData(sede);
            initialSnapshotRef.current = initial;
            (Object.keys(initial) as Array<keyof SedeFormData>).forEach((key) => {
                setData(key, initial[key]);
            });
            setGeo(buildInitialGeoValue(sede));
            clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, sede?.id]);

    /**
     * Cualquier cambio en la cascada se propaga al `useForm` para que
     * `distrito_id` viaje al backend al hacer submit.
     */
    const handleGeoChange = (next: GeoCascadeValue) => {
        setGeo(next);
        setData('distrito_id', next.distrito_id);
    };

    const isDirty = useMemo(() => {
        const initial = initialSnapshotRef.current;
        return (Object.keys(initial) as Array<keyof SedeFormData>).some(
            (key) => initial[key] !== data[key],
        );
    }, [data]);

    const confirmDiscard = (): boolean => {
        if (!isDirty) {
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
            setGeo({
                departamento_id: null,
                provincia_id: null,
                distrito_id: null,
            });
            clearErrors();
        }
        onOpenChange(next);
    };

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const onSuccess = () => {
            reset();
            setGeo({
                departamento_id: null,
                provincia_id: null,
                distrito_id: null,
            });
            clearErrors();
            onOpenChange(false);
        };

        if (isEdit && sede) {
            put(sedes.update(sede.id).url, {
                preserveScroll: true,
                onSuccess,
            });
        } else {
            post(sedes.store().url, {
                preserveScroll: true,
                onSuccess,
            });
        }
    };

    return (
        <FormModal
            open={open}
            onOpenChange={handleClose}
            title={isEdit ? t('form.title_edit') : t('form.title_create')}
            description={
                isEdit
                    ? t('form.description_edit')
                    : t('form.description_create')
            }
            size="lg"
            onSubmit={onSubmit}
            footer={
                <>
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
                            <Loader2
                                className="size-4 animate-spin"
                                aria-hidden="true"
                            />
                        )}
                        {isEdit
                            ? t('form.submit_edit')
                            : t('form.submit_create')}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-5">
                <FormSection
                    index={0}
                    title={t('form.section_basic')}
                    description={
                        isEdit
                            ? t('form.section_basic_hint_edit')
                            : t('form.section_basic_hint_create')
                    }
                    columns={2}
                >
                    <FormField
                        id="sede-nombre"
                        label={t('form.fields.nombre')}
                        required
                        error={errors.nombre}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="sede-nombre"
                            value={data.nombre}
                            onChange={(e) => setData('nombre', e.target.value)}
                            placeholder={t('form.fields.nombre_placeholder')}
                            autoComplete="off"
                            autoFocus
                        />
                    </FormField>

                    <FormField
                        id="sede-telefono"
                        label={t('form.fields.telefono')}
                        error={errors.telefono}
                    >
                        <Input
                            id="sede-telefono"
                            value={data.telefono}
                            onChange={(e) =>
                                setData('telefono', e.target.value)
                            }
                            placeholder="+51 1 555-0101"
                            autoComplete="tel"
                        />
                    </FormField>

                    <FormField
                        id="sede-email"
                        label={t('form.fields.email')}
                        error={errors.email}
                    >
                        <Input
                            id="sede-email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="sede@vetsaas.pe"
                            autoComplete="email"
                        />
                    </FormField>
                </FormSection>

                <FormSection
                    index={1}
                    title={t('form.section_location')}
                    description={t('form.section_location_hint')}
                    columns={1}
                >
                    <FormField
                        id="sede-direccion"
                        label={t('form.fields.direccion')}
                        error={errors.direccion}
                    >
                        <Input
                            id="sede-direccion"
                            value={data.direccion}
                            onChange={(e) =>
                                setData('direccion', e.target.value)
                            }
                            placeholder="Av. Arequipa 1234"
                            autoComplete="street-address"
                        />
                    </FormField>

                    <GeoCascadeFields
                        departamentos={departamentos}
                        value={geo}
                        onChange={handleGeoChange}
                        disabled={processing}
                        errors={{ distrito_id: errors.distrito_id }}
                        labels={{
                            departamento: t('form.fields.departamento'),
                            provincia: t('form.fields.provincia'),
                            distrito: t('form.fields.distrito'),
                        }}
                    />
                </FormSection>

                <FormSection
                    index={2}
                    title={t('form.section_billing')}
                    description={t('form.section_billing_hint')}
                    columns={2}
                >
                    <FormField
                        id="sede-serie-factura"
                        label={t('form.fields.serie_factura')}
                        error={errors.serie_factura}
                        hint={t('form.fields.serie_factura_hint')}
                    >
                        <Input
                            id="sede-serie-factura"
                            value={data.serie_factura}
                            onChange={(e) =>
                                setData(
                                    'serie_factura',
                                    e.target.value.toUpperCase(),
                                )
                            }
                            placeholder="F001"
                            maxLength={4}
                            className="font-mono uppercase"
                        />
                    </FormField>

                    <FormField
                        id="sede-serie-boleta"
                        label={t('form.fields.serie_boleta')}
                        error={errors.serie_boleta}
                        hint={t('form.fields.serie_boleta_hint')}
                    >
                        <Input
                            id="sede-serie-boleta"
                            value={data.serie_boleta}
                            onChange={(e) =>
                                setData(
                                    'serie_boleta',
                                    e.target.value.toUpperCase(),
                                )
                            }
                            placeholder="B001"
                            maxLength={4}
                            className="font-mono uppercase"
                        />
                    </FormField>
                </FormSection>

                <FormSection index={3} title={t('form.section_status')}>
                    <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-border/60 bg-card/40 p-3 transition-colors hover:bg-muted/30">
                        <Checkbox
                            id="sede-activa"
                            checked={data.activa}
                            onCheckedChange={(checked) =>
                                setData('activa', checked === true)
                            }
                            className="mt-0.5"
                        />
                        <div className="flex flex-col gap-0.5">
                            <span className="text-sm font-medium">
                                {t('form.fields.activa')}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {t('form.fields.activa_hint')}
                            </span>
                        </div>
                    </label>
                    {errors.activa && (
                        <p className="text-xs text-destructive">
                            {errors.activa}
                        </p>
                    )}
                </FormSection>
            </div>
        </FormModal>
    );
}
