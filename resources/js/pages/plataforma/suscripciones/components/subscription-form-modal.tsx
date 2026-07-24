import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useMemo, useRef, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import suscripciones from '@/routes/plataforma/suscripciones';
import type {
    Subscription,
    SubscriptionPlanOption,
    SubscriptionTenantOption,
} from '../types';

export type SubscriptionFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Suscripción a editar; si es `null`, abre en modo crear. */
    subscription: Subscription | null;
    plansCatalog: readonly SubscriptionPlanOption[];
    tenantsCatalog: readonly SubscriptionTenantOption[];
};

type SubscriptionFormData = {
    tenant_id: string;
    plan_id: string;
    estado: string;
    ciclo: string;
    precio_pactado: string;
    descuento_pct: string;
    trial_ends_at: string;
    current_period_start: string;
    current_period_end: string;
    grace_ends_at: string;
    proximo_cobro_at: string;
    cancel_reason: string;
    cancel_feedback: string;
};

const emptyForm: SubscriptionFormData = {
    tenant_id: '',
    plan_id: '',
    estado: 'trial',
    ciclo: 'mensual',
    precio_pactado: '0.00',
    descuento_pct: '0',
    trial_ends_at: '',
    current_period_start: '',
    current_period_end: '',
    grace_ends_at: '',
    proximo_cobro_at: '',
    cancel_reason: '',
    cancel_feedback: '',
};

/** Convierte `"2026-05-12T12:00:00Z"` o similar en `"2026-05-12T12:00"`. */
const formatDateTimeLocal = (value: string | null): string => {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const pad = (n: number) => n.toString().padStart(2, '0');
    return (
        date.getFullYear() +
        '-' +
        pad(date.getMonth() + 1) +
        '-' +
        pad(date.getDate()) +
        'T' +
        pad(date.getHours()) +
        ':' +
        pad(date.getMinutes())
    );
};

const buildInitialData = (
    subscription: Subscription | null,
): SubscriptionFormData => ({
    tenant_id: subscription?.tenant_id ?? '',
    plan_id: subscription?.plan_id ?? '',
    estado: subscription?.estado ?? 'trial',
    ciclo: subscription?.ciclo ?? 'mensual',
    precio_pactado: subscription?.precio_pactado ?? '0.00',
    descuento_pct: subscription?.descuento_pct ?? '0',
    trial_ends_at: formatDateTimeLocal(subscription?.trial_ends_at ?? null),
    current_period_start: formatDateTimeLocal(
        subscription?.current_period_start ?? null,
    ),
    current_period_end: formatDateTimeLocal(
        subscription?.current_period_end ?? null,
    ),
    grace_ends_at: formatDateTimeLocal(subscription?.grace_ends_at ?? null),
    proximo_cobro_at: formatDateTimeLocal(
        subscription?.proximo_cobro_at ?? null,
    ),
    cancel_reason: subscription?.cancel_reason ?? '',
    cancel_feedback: subscription?.cancel_feedback ?? '',
});

const isFormValid = (data: SubscriptionFormData): boolean => {
    if (!data.tenant_id) return false;
    if (!data.plan_id) return false;
    if (!data.estado) return false;
    if (!data.ciclo) return false;
    if (
        data.precio_pactado === '' ||
        Number.isNaN(Number(data.precio_pactado))
    ) {
        return false;
    }
    if (Number(data.precio_pactado) < 0) return false;
    return true;
};

const ESTADOS = ['trial', 'active', 'grace', 'suspended', 'cancelled'];
const CICLOS = ['mensual', 'trimestral', 'semestral', 'anual'] as const;

function cycleMonths(ciclo: string): number {
    switch (ciclo) {
        case 'trimestral':
            return 3;
        case 'semestral':
            return 6;
        case 'anual':
            return 12;
        default:
            return 1;
    }
}

function suggestedPlanPrice(
    plan: { precio_mensual: string; precio_anual: string | null },
    ciclo: string,
): string {
    const mensual = Number(plan.precio_mensual);
    if (ciclo === 'anual') {
        return plan.precio_anual ?? String(mensual * 12);
    }

    return String(Math.round(mensual * cycleMonths(ciclo) * 100) / 100);
}

function addMonthsToDateTimeLocal(value: string, months: number): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }

    date.setMonth(date.getMonth() + months);
    const pad = (n: number) => n.toString().padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/**
 * Modal de crear/editar suscripción.
 *
 * Pensado para soporte interno: el alta normal sale del checkout de
 * Orvae. Acá podemos:
 *   - Crear suscripciones manuales para clientes VIP / migración.
 *   - Ajustar fechas (trial, periodo actual, próximo cobro) si hubo
 *     un problema de cobranza o una negociación especial.
 *   - Ver/editar la razón de cancelación (cuando ya esté cancelada).
 *
 * Las transiciones "rápidas" (extender trial, cambiar plan, cancelar)
 * tienen su propio diálogo dedicado en `SubscriptionActionsDialog`.
 */
export function SubscriptionFormModal({
    open,
    onOpenChange,
    subscription,
    plansCatalog,
    tenantsCatalog,
}: SubscriptionFormModalProps) {
    const { t } = useTranslation(['suscripciones', 'common']);
    const isEdit = subscription !== null;

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<SubscriptionFormData>(emptyForm);

    const canSubmit = isFormValid(data) && !processing;

    const initialSnapshotRef = useRef<SubscriptionFormData>(emptyForm);

    useEffect(() => {
        if (open) {
            const initial = buildInitialData(subscription);
            initialSnapshotRef.current = initial;
            (Object.keys(initial) as Array<keyof SubscriptionFormData>).forEach(
                (key) => {
                    setData(key, initial[key] as never);
                },
            );
            clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, subscription?.id]);

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

    /**
     * Al elegir un plan en modo CREATE auto-completamos el precio_pactado
     * con el precio del plan según el ciclo seleccionado. En edición
     * no tocamos el precio para no sobreescribir el negociado.
     */
    const handlePlanChange = (planId: string) => {
        setData('plan_id', planId);
        if (isEdit) return;

        const plan = plansCatalog.find((p) => p.id === planId);
        if (!plan) return;

        const price = suggestedPlanPrice(plan, data.ciclo);
        setData('precio_pactado', price);

        // Si el plan tiene trial_days y aún no hay fecha, la inicializamos.
        if (plan.trial_days > 0 && !data.trial_ends_at) {
            const trialEnd = new Date();
            trialEnd.setDate(trialEnd.getDate() + plan.trial_days);
            const pad = (n: number) => n.toString().padStart(2, '0');
            setData(
                'trial_ends_at',
                `${trialEnd.getFullYear()}-${pad(trialEnd.getMonth() + 1)}-${pad(trialEnd.getDate())}T${pad(trialEnd.getHours())}:${pad(trialEnd.getMinutes())}`,
            );
        }
    };

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const onSuccess = () => {
            reset();
            clearErrors();
            onOpenChange(false);
        };

        if (isEdit && subscription) {
            put(suscripciones.update(subscription.id).url, {
                preserveScroll: true,
                onSuccess,
            });
        } else {
            post(suscripciones.store().url, {
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
                    ? t('suscripciones:form.title_edit')
                    : t('suscripciones:form.title_create')
            }
            description={
                isEdit
                    ? t('suscripciones:form.description_edit')
                    : t('suscripciones:form.description_create')
            }
            size="xl"
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
                            ? t('suscripciones:form.submit_edit')
                            : t('suscripciones:form.submit_create')}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-5">
                <FormSection
                    index={0}
                    title={t('suscripciones:form.section_contract')}
                    description={t(
                        'suscripciones:form.section_contract_hint',
                    )}
                >
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField
                            id="sub-tenant-id"
                            label={t('suscripciones:form.fields.tenant_id')}
                            required
                            hint={t(
                                'suscripciones:form.fields.tenant_id_hint',
                            )}
                            error={errors.tenant_id}
                        >
                            <Select
                                value={data.tenant_id}
                                onValueChange={(v) => setData('tenant_id', v)}
                                disabled={isEdit}
                            >
                                <SelectTrigger
                                    id="sub-tenant-id"
                                    className="w-full"
                                >
                                    <SelectValue
                                        placeholder={t(
                                            'suscripciones:form.fields.tenant_id_placeholder',
                                        )}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {tenantsCatalog.map((tenant) => (
                                        <SelectItem
                                            key={tenant.id}
                                            value={tenant.id}
                                            className="cursor-pointer"
                                        >
                                            <span className="font-medium">
                                                {tenant.razon_social}
                                            </span>
                                            <span className="ml-2 font-mono text-xs text-muted-foreground">
                                                {tenant.slug}
                                            </span>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField
                            id="sub-plan-id"
                            label={t('suscripciones:form.fields.plan_id')}
                            required
                            error={errors.plan_id}
                        >
                            <Select
                                value={data.plan_id}
                                onValueChange={handlePlanChange}
                            >
                                <SelectTrigger
                                    id="sub-plan-id"
                                    className="w-full"
                                >
                                    <SelectValue
                                        placeholder={t(
                                            'suscripciones:form.fields.plan_id_placeholder',
                                        )}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {plansCatalog.map((plan) => (
                                        <SelectItem
                                            key={plan.id}
                                            value={plan.id}
                                            className="cursor-pointer"
                                        >
                                            <span className="font-medium">
                                                {plan.nombre}
                                            </span>
                                            <span className="ml-2 font-mono text-xs text-muted-foreground">
                                                {plan.codigo}
                                            </span>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField
                            id="sub-estado"
                            label={t('suscripciones:form.fields.estado')}
                            required
                            error={errors.estado}
                        >
                            <Select
                                value={data.estado}
                                onValueChange={(v) => setData('estado', v)}
                            >
                                <SelectTrigger
                                    id="sub-estado"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {ESTADOS.map((estado) => (
                                        <SelectItem
                                            key={estado}
                                            value={estado}
                                            className="cursor-pointer"
                                        >
                                            {t(
                                                `suscripciones:estados.${estado}`,
                                            )}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField
                            id="sub-ciclo"
                            label={t('suscripciones:form.fields.ciclo')}
                            required
                            error={errors.ciclo}
                        >
                            <Select
                                value={data.ciclo}
                                onValueChange={(v) => {
                                    setData('ciclo', v);
                                    const plan = plansCatalog.find((p) => p.id === data.plan_id);
                                    if (plan && !isEdit) {
                                        setData('precio_pactado', suggestedPlanPrice(plan, v));
                                    } else if (plan && isEdit) {
                                        // Al cambiar ciclo en edición, sugerimos precio del catálogo;
                                        // el operador puede sobreescribir precio_pactado.
                                        setData('precio_pactado', suggestedPlanPrice(plan, v));
                                    }

                                    const start =
                                        data.current_period_start ||
                                        (() => {
                                            const now = new Date();
                                            const pad = (n: number) => n.toString().padStart(2, '0');

                                            return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
                                        })();

                                    if (!data.current_period_start) {
                                        setData('current_period_start', start);
                                    }

                                    setData(
                                        'current_period_end',
                                        addMonthsToDateTimeLocal(start, cycleMonths(v)),
                                    );
                                    setData(
                                        'proximo_cobro_at',
                                        addMonthsToDateTimeLocal(start, cycleMonths(v)),
                                    );
                                }}
                            >
                                <SelectTrigger
                                    id="sub-ciclo"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {CICLOS.map((ciclo) => (
                                        <SelectItem
                                            key={ciclo}
                                            value={ciclo}
                                            className="cursor-pointer"
                                        >
                                            {t(
                                                `suscripciones:ciclos.${ciclo}`,
                                            )}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField
                            id="sub-precio-pactado"
                            label={t(
                                'suscripciones:form.fields.precio_pactado',
                            )}
                            required
                            hint={t(
                                'suscripciones:form.fields.precio_pactado_hint',
                            )}
                            error={errors.precio_pactado}
                        >
                            <Input
                                id="sub-precio-pactado"
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.precio_pactado}
                                onChange={(e) =>
                                    setData('precio_pactado', e.target.value)
                                }
                                className="font-mono"
                            />
                        </FormField>

                        <FormField
                            id="sub-descuento-pct"
                            label={t(
                                'suscripciones:form.fields.descuento_pct',
                            )}
                            error={errors.descuento_pct}
                        >
                            <Input
                                id="sub-descuento-pct"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                value={data.descuento_pct}
                                onChange={(e) =>
                                    setData('descuento_pct', e.target.value)
                                }
                                className="font-mono"
                            />
                        </FormField>
                    </div>
                </FormSection>

                <FormSection
                    index={1}
                    title={t('suscripciones:form.section_dates')}
                    description={t('suscripciones:form.section_dates_hint')}
                >
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField
                            id="sub-trial-ends-at"
                            label={t(
                                'suscripciones:form.fields.trial_ends_at',
                            )}
                            error={errors.trial_ends_at}
                        >
                            <Input
                                id="sub-trial-ends-at"
                                type="datetime-local"
                                value={data.trial_ends_at}
                                onChange={(e) =>
                                    setData('trial_ends_at', e.target.value)
                                }
                            />
                        </FormField>

                        <FormField
                            id="sub-proximo-cobro"
                            label={t(
                                'suscripciones:form.fields.proximo_cobro_at',
                            )}
                            error={errors.proximo_cobro_at}
                        >
                            <Input
                                id="sub-proximo-cobro"
                                type="datetime-local"
                                value={data.proximo_cobro_at}
                                onChange={(e) =>
                                    setData(
                                        'proximo_cobro_at',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>

                        <FormField
                            id="sub-period-start"
                            label={t(
                                'suscripciones:form.fields.current_period_start',
                            )}
                            error={errors.current_period_start}
                        >
                            <Input
                                id="sub-period-start"
                                type="datetime-local"
                                value={data.current_period_start}
                                onChange={(e) =>
                                    setData(
                                        'current_period_start',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>

                        <FormField
                            id="sub-period-end"
                            label={t(
                                'suscripciones:form.fields.current_period_end',
                            )}
                            error={errors.current_period_end}
                        >
                            <Input
                                id="sub-period-end"
                                type="datetime-local"
                                value={data.current_period_end}
                                onChange={(e) =>
                                    setData(
                                        'current_period_end',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>

                        <FormField
                            id="sub-grace-ends-at"
                            label={t(
                                'suscripciones:form.fields.grace_ends_at',
                            )}
                            hint={t('suscripciones:form.fields.grace_ends_at_hint')}
                            error={errors.grace_ends_at}
                        >
                            <Input
                                id="sub-grace-ends-at"
                                type="datetime-local"
                                value={data.grace_ends_at}
                                onChange={(e) =>
                                    setData('grace_ends_at', e.target.value)
                                }
                            />
                        </FormField>
                    </div>
                </FormSection>

                {data.estado === 'cancelled' && (
                    <FormSection
                        index={2}
                        title={t('suscripciones:form.section_cancellation')}
                        description={t(
                            'suscripciones:form.section_cancellation_hint',
                        )}
                    >
                        <div className="grid grid-cols-1 gap-4">
                            <FormField
                                id="sub-cancel-reason"
                                label={t(
                                    'suscripciones:form.fields.cancel_reason',
                                )}
                                error={errors.cancel_reason}
                            >
                                <Textarea
                                    id="sub-cancel-reason"
                                    value={data.cancel_reason}
                                    onChange={(e) =>
                                        setData(
                                            'cancel_reason',
                                            e.target.value,
                                        )
                                    }
                                    rows={2}
                                />
                            </FormField>

                            <FormField
                                id="sub-cancel-feedback"
                                label={t(
                                    'suscripciones:form.fields.cancel_feedback',
                                )}
                                error={errors.cancel_feedback}
                            >
                                <Textarea
                                    id="sub-cancel-feedback"
                                    value={data.cancel_feedback}
                                    onChange={(e) =>
                                        setData(
                                            'cancel_feedback',
                                            e.target.value,
                                        )
                                    }
                                    rows={3}
                                />
                            </FormField>
                        </div>
                    </FormSection>
                )}
            </div>
        </FormModal>
    );
}
