import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useMemo, useRef, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import planes from '@/routes/plataforma/planes';
import type { Plan } from '../types';

export type PlanFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Plan a editar; si es `null` el modal abre en modo crear. */
    plan: Plan | null;
};

type PlanFormData = {
    codigo: string;
    nombre: string;
    descripcion: string;
    badge: string;
    color_hex: string;
    precio_mensual: string;
    precio_anual: string;
    trial_days: number;
    orden: number;
    es_publico: boolean;
    activo: boolean;
};

const emptyForm: PlanFormData = {
    codigo: '',
    nombre: '',
    descripcion: '',
    badge: '',
    color_hex: '#1F6E4A',
    precio_mensual: '0.00',
    precio_anual: '',
    trial_days: 14,
    orden: 0,
    es_publico: true,
    activo: true,
};

const buildInitialData = (plan: Plan | null): PlanFormData => ({
    codigo: plan?.codigo ?? '',
    nombre: plan?.nombre ?? '',
    descripcion: plan?.descripcion ?? '',
    badge: plan?.badge ?? '',
    color_hex: plan?.color_hex ?? '#1F6E4A',
    precio_mensual: plan?.precio_mensual ?? '0.00',
    precio_anual: plan?.precio_anual ?? '',
    trial_days: plan?.trial_days ?? 14,
    orden: plan?.orden ?? 0,
    es_publico: plan?.es_publico ?? true,
    activo: plan?.activo ?? true,
});

const CODE_REGEX = /^[a-z][a-z0-9_]*$/;
const HEX_REGEX = /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/;

const isFormValid = (data: PlanFormData): boolean => {
    if (!CODE_REGEX.test(data.codigo) || data.codigo.length < 2) return false;
    if (data.nombre.trim().length < 2) return false;
    if (data.color_hex && !HEX_REGEX.test(data.color_hex)) return false;
    if (Number.isNaN(Number(data.precio_mensual))) return false;
    if (Number(data.precio_mensual) < 0) return false;
    if (data.precio_anual !== '' && Number.isNaN(Number(data.precio_anual))) return false;
    return true;
};

/**
 * Modal de crear/editar plan.
 *
 * Espejo de `SedeFormModal` / `UserFormModal` / `TenantFormModal`:
 *   - 3 secciones: Identidad / Precios / Visibilidad.
 *   - El código no es editable en modo edición (el backend rechaza el
 *     cambio porque romper el código rompe `Plan::resolveFeature`).
 *   - Las features se gestionan en un modal separado, accesible desde
 *     el dropdown de la fila ("Gestionar features").
 */
export function PlanFormModal({
    open,
    onOpenChange,
    plan,
}: PlanFormModalProps) {
    const { t } = useTranslation(['planes', 'common']);
    const isEdit = plan !== null;

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<PlanFormData>(emptyForm);

    const canSubmit = isFormValid(data) && !processing;

    const initialSnapshotRef = useRef<PlanFormData>(emptyForm);

    useEffect(() => {
        if (open) {
            const initial = buildInitialData(plan);
            initialSnapshotRef.current = initial;
            (Object.keys(initial) as Array<keyof PlanFormData>).forEach(
                (key) => {
                    setData(key, initial[key] as never);
                },
            );
            clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, plan?.id]);

    const isDirty = useMemo(() => {
        const initial = initialSnapshotRef.current;
        return JSON.stringify(initial) !== JSON.stringify(data);
    }, [data]);

    const confirmDiscard = (): boolean => {
        if (!isDirty) return true;
        return window.confirm(t('common:form.unsaved_changes'));
    };

    const handleClose = (next: boolean) => {
        if (!next) {
            if (!confirmDiscard()) return;
            reset();
            clearErrors();
        }
        onOpenChange(next);
    };

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const onSuccess = () => {
            reset();
            clearErrors();
            onOpenChange(false);
        };

        if (isEdit && plan) {
            put(planes.update(plan.id).url, {
                preserveScroll: true,
                onSuccess,
            });
        } else {
            post(planes.store().url, {
                preserveScroll: true,
                onSuccess,
            });
        }
    };

    return (
        <FormModal
            open={open}
            onOpenChange={handleClose}
            title={
                isEdit
                    ? t('planes:form.title_edit')
                    : t('planes:form.title_create')
            }
            description={
                isEdit
                    ? t('planes:form.description_edit')
                    : t('planes:form.description_create')
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
                            ? t('planes:form.submit_edit')
                            : t('planes:form.submit_create')}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-5">
                <FormSection
                    index={0}
                    title={t('planes:form.section_identity')}
                    description={t('planes:form.section_identity_hint')}
                >
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField
                            id="plan-codigo"
                            label={t('planes:form.fields.codigo')}
                            required
                            hint={t('planes:form.fields.codigo_hint')}
                            error={errors.codigo}
                        >
                            <Input
                                id="plan-codigo"
                                value={data.codigo}
                                onChange={(e) =>
                                    setData(
                                        'codigo',
                                        e.target.value
                                            .toLowerCase()
                                            .replace(/[^a-z0-9_]/g, '_'),
                                    )
                                }
                                placeholder="starter"
                                disabled={isEdit}
                                autoComplete="off"
                                autoFocus={!isEdit}
                                className="font-mono"
                            />
                        </FormField>

                        <FormField
                            id="plan-nombre"
                            label={t('planes:form.fields.nombre')}
                            required
                            error={errors.nombre}
                        >
                            <Input
                                id="plan-nombre"
                                value={data.nombre}
                                onChange={(e) =>
                                    setData('nombre', e.target.value)
                                }
                                placeholder={t(
                                    'planes:form.fields.nombre_placeholder',
                                )}
                                autoComplete="off"
                                autoFocus={isEdit}
                            />
                        </FormField>

                        <div className="sm:col-span-2">
                            <FormField
                                id="plan-descripcion"
                                label={t('planes:form.fields.descripcion')}
                                hint={t('planes:form.fields.descripcion_hint')}
                                error={errors.descripcion}
                            >
                                <Textarea
                                    id="plan-descripcion"
                                    value={data.descripcion}
                                    onChange={(e) =>
                                        setData('descripcion', e.target.value)
                                    }
                                    placeholder={t(
                                        'planes:form.fields.descripcion_placeholder',
                                    )}
                                    rows={3}
                                />
                            </FormField>
                        </div>

                        <FormField
                            id="plan-badge"
                            label={t('planes:form.fields.badge')}
                            hint={t('planes:form.fields.badge_hint')}
                            error={errors.badge}
                        >
                            <Input
                                id="plan-badge"
                                value={data.badge}
                                onChange={(e) =>
                                    setData('badge', e.target.value)
                                }
                                placeholder={t(
                                    'planes:form.fields.badge_placeholder',
                                )}
                                autoComplete="off"
                            />
                        </FormField>

                        <FormField
                            id="plan-color-hex"
                            label={t('planes:form.fields.color_hex')}
                            hint={t('planes:form.fields.color_hex_hint')}
                            error={errors.color_hex}
                        >
                            <div className="flex items-center gap-2">
                                <Input
                                    id="plan-color-hex"
                                    type="color"
                                    value={
                                        HEX_REGEX.test(data.color_hex)
                                            ? data.color_hex
                                            : '#1F6E4A'
                                    }
                                    onChange={(e) =>
                                        setData('color_hex', e.target.value)
                                    }
                                    className="h-9 w-12 cursor-pointer p-1"
                                    aria-label={t(
                                        'planes:form.fields.color_picker_aria',
                                    )}
                                />
                                <Input
                                    value={data.color_hex}
                                    onChange={(e) =>
                                        setData('color_hex', e.target.value)
                                    }
                                    placeholder="#1F6E4A"
                                    autoComplete="off"
                                    className="font-mono"
                                />
                            </div>
                        </FormField>
                    </div>
                </FormSection>

                <FormSection
                    index={1}
                    title={t('planes:form.section_pricing')}
                    description={t('planes:form.section_pricing_hint')}
                >
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField
                            id="plan-precio-mensual"
                            label={t('planes:form.fields.precio_mensual')}
                            required
                            error={errors.precio_mensual}
                        >
                            <Input
                                id="plan-precio-mensual"
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.precio_mensual}
                                onChange={(e) =>
                                    setData('precio_mensual', e.target.value)
                                }
                                className="font-mono"
                            />
                        </FormField>

                        <FormField
                            id="plan-precio-anual"
                            label={t('planes:form.fields.precio_anual')}
                            hint={t('planes:form.fields.precio_anual_hint')}
                            error={errors.precio_anual}
                        >
                            <Input
                                id="plan-precio-anual"
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.precio_anual}
                                onChange={(e) =>
                                    setData('precio_anual', e.target.value)
                                }
                                placeholder={t(
                                    'planes:form.fields.precio_anual_placeholder',
                                )}
                                className="font-mono"
                            />
                        </FormField>

                        <FormField
                            id="plan-trial-days"
                            label={t('planes:form.fields.trial_days')}
                            required
                            hint={t('planes:form.fields.trial_days_hint')}
                            error={errors.trial_days}
                        >
                            <Input
                                id="plan-trial-days"
                                type="number"
                                min="0"
                                max="365"
                                value={data.trial_days}
                                onChange={(e) =>
                                    setData(
                                        'trial_days',
                                        Number(e.target.value) || 0,
                                    )
                                }
                            />
                        </FormField>

                        <FormField
                            id="plan-orden"
                            label={t('planes:form.fields.orden')}
                            required
                            hint={t('planes:form.fields.orden_hint')}
                            error={errors.orden}
                        >
                            <Input
                                id="plan-orden"
                                type="number"
                                min="0"
                                max="1000"
                                value={data.orden}
                                onChange={(e) =>
                                    setData(
                                        'orden',
                                        Number(e.target.value) || 0,
                                    )
                                }
                            />
                        </FormField>
                    </div>
                </FormSection>

                <FormSection
                    index={2}
                    title={t('planes:form.section_visibility')}
                    description={t('planes:form.section_visibility_hint')}
                >
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField
                            id="plan-es-publico"
                            label={t('planes:form.fields.es_publico')}
                            hint={t('planes:form.fields.es_publico_hint')}
                            error={errors.es_publico}
                        >
                            <label
                                htmlFor="plan-es-publico"
                                className="flex h-9 cursor-pointer items-center gap-2 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <Checkbox
                                    id="plan-es-publico"
                                    checked={data.es_publico}
                                    onCheckedChange={(checked) =>
                                        setData('es_publico', checked === true)
                                    }
                                />
                                <span className="text-foreground/80">
                                    {data.es_publico
                                        ? t('planes:form.fields.es_publico_yes')
                                        : t('planes:form.fields.es_publico_no')}
                                </span>
                            </label>
                        </FormField>

                        <FormField
                            id="plan-activo"
                            label={t('planes:form.fields.activo')}
                            hint={t('planes:form.fields.activo_hint')}
                            error={errors.activo}
                        >
                            <label
                                htmlFor="plan-activo"
                                className="flex h-9 cursor-pointer items-center gap-2 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <Checkbox
                                    id="plan-activo"
                                    checked={data.activo}
                                    onCheckedChange={(checked) =>
                                        setData('activo', checked === true)
                                    }
                                />
                                <span className="text-foreground/80">
                                    {data.activo
                                        ? t('planes:form.fields.activo_yes')
                                        : t('planes:form.fields.activo_no')}
                                </span>
                            </label>
                        </FormField>
                    </div>
                </FormSection>
            </div>
        </FormModal>
    );
}
