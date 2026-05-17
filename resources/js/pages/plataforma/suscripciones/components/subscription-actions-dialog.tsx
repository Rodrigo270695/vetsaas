import { router } from '@inertiajs/react';
import { Ban, CalendarPlus, Loader2, Repeat } from 'lucide-react';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { Trans, useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import suscripciones from '@/routes/plataforma/suscripciones';
import type { Subscription, SubscriptionPlanOption } from '../types';

/**
 * Modo del diálogo. Cada uno apunta a un endpoint distinto en el
 * controller, pero comparten la misma estructura visual.
 */
export type SubscriptionActionMode = 'extend-trial' | 'change-plan' | 'cancel';

export type SubscriptionActionsDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    subscription: Subscription | null;
    mode: SubscriptionActionMode | null;
    plansCatalog: readonly SubscriptionPlanOption[];
};

/**
 * Diálogo unificado para las 3 transiciones especializadas de una
 * suscripción:
 *   - 'extend-trial': pide `days` (1-365). El backend reanuda a 'trial'
 *     y agrega N días al `trial_ends_at`.
 *   - 'change-plan': pide `plan_id`. Opcionalmente conserva el precio
 *     pactado actual (checkbox `keep_price`).
 *   - 'cancel': pide motivo obligatorio (min 5 chars) y feedback opcional.
 *     Marca la suscripción como `cancelled`.
 *
 * Se usa este patrón unificado en vez de 3 diálogos separados porque
 * comparten más del 80% del código (skeleton, footer, dispatch al
 * backend) y nunca se abren simultáneamente.
 */
export function SubscriptionActionsDialog({
    open,
    onOpenChange,
    subscription,
    mode,
    plansCatalog,
}: SubscriptionActionsDialogProps) {
    const { t } = useTranslation(['suscripciones', 'common']);

    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Estados específicos por modo (todos en este componente para no
    // crear 3 sub-componentes con el mismo skeleton).
    const [days, setDays] = useState<number>(7);
    const [newPlanId, setNewPlanId] = useState<string>('');
    const [keepPrice, setKeepPrice] = useState<boolean>(false);
    const [reason, setReason] = useState<string>('');
    const [feedback, setFeedback] = useState<string>('');

    useEffect(() => {
        if (!open) return;
        setProcessing(false);
        setError(null);
        setDays(7);
        setNewPlanId(subscription?.plan_id ?? '');
        setKeepPrice(false);
        setReason('');
        setFeedback('');
    }, [open, subscription?.plan_id]);

    const minReasonLength = 5;

    const canSubmit = useMemo(() => {
        if (!mode) return false;
        switch (mode) {
            case 'extend-trial':
                return days >= 1 && days <= 365;
            case 'change-plan':
                return (
                    newPlanId !== '' &&
                    newPlanId !== subscription?.plan_id
                );
            case 'cancel':
                return reason.trim().length >= minReasonLength;
        }
    }, [mode, days, newPlanId, subscription?.plan_id, reason]);

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!subscription || !mode) return;

        setProcessing(true);
        setError(null);

        const onFinish = () => setProcessing(false);
        const onSuccess = () => onOpenChange(false);
        const onError = (errors: Record<string, string | undefined>) => {
            const specific =
                errors?.reason ??
                errors?.days ??
                errors?.plan_id ??
                errors?.estado ??
                null;
            setError(specific ?? t('common:feedback.save_error'));
        };
        const opts = {
            preserveScroll: true,
            onFinish,
            onSuccess,
            onError,
        };

        switch (mode) {
            case 'extend-trial':
                router.post(
                    suscripciones.extendTrial(subscription.id).url,
                    { days },
                    opts,
                );
                break;
            case 'change-plan':
                router.post(
                    suscripciones.changePlan(subscription.id).url,
                    { plan_id: newPlanId, keep_price: keepPrice },
                    opts,
                );
                break;
            case 'cancel':
                router.post(
                    suscripciones.cancel(subscription.id).url,
                    {
                        reason: reason.trim(),
                        feedback: feedback.trim() || null,
                    },
                    opts,
                );
                break;
        }
    };

    if (!mode) {
        return null;
    }

    // Icon + tema visual cambian según el modo.
    const themes = {
        'extend-trial': {
            icon: CalendarPlus,
            iconClass:
                'flex size-11 items-center justify-center rounded-full bg-sky-500/10 text-sky-600 dark:text-sky-400',
            buttonClass:
                'cursor-pointer gap-2 bg-sky-600 text-white hover:bg-sky-700 focus-visible:ring-sky-500/40 disabled:cursor-not-allowed',
        },
        'change-plan': {
            icon: Repeat,
            iconClass:
                'flex size-11 items-center justify-center rounded-full bg-primary/10 text-primary',
            buttonClass:
                'cursor-pointer gap-2 disabled:cursor-not-allowed',
        },
        cancel: {
            icon: Ban,
            iconClass:
                'flex size-11 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400',
            buttonClass:
                'cursor-pointer gap-2 bg-amber-600 text-white hover:bg-amber-700 focus-visible:ring-amber-500/40 disabled:cursor-not-allowed',
        },
    } as const;

    const Icon = themes[mode].icon;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={onSubmit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <div className={themes[mode].iconClass}>
                            <Icon
                                className="size-5"
                                strokeWidth={2.5}
                                aria-hidden="true"
                            />
                        </div>
                        <DialogTitle className="pt-2 text-base">
                            {t(`suscripciones:${mode}.title`)}
                        </DialogTitle>
                        <DialogDescription className="text-sm" asChild>
                            <p>
                                <Trans
                                    ns="suscripciones"
                                    i18nKey={`${mode}.description`}
                                    values={{
                                        tenant:
                                            subscription?.tenant
                                                ?.razon_social ??
                                            subscription?.tenant?.slug ??
                                            '',
                                    }}
                                    components={{
                                        strong: (
                                            <strong className="text-foreground" />
                                        ),
                                    }}
                                />
                            </p>
                        </DialogDescription>
                    </DialogHeader>

                    {mode === 'extend-trial' && (
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="sub-days">
                                {t('suscripciones:extend-trial.days_label')}
                            </Label>
                            <Input
                                id="sub-days"
                                type="number"
                                min={1}
                                max={365}
                                value={days}
                                onChange={(e) =>
                                    setDays(Number(e.target.value) || 1)
                                }
                                autoFocus
                            />
                            <p className="text-xs text-muted-foreground">
                                {t('suscripciones:extend-trial.days_hint')}
                            </p>
                        </div>
                    )}

                    {mode === 'change-plan' && (
                        <div className="flex flex-col gap-3">
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="sub-plan">
                                    {t(
                                        'suscripciones:change-plan.plan_label',
                                    )}
                                </Label>
                                <Select
                                    value={newPlanId}
                                    onValueChange={(v) => setNewPlanId(v)}
                                >
                                    <SelectTrigger
                                        id="sub-plan"
                                        className="w-full"
                                    >
                                        <SelectValue
                                            placeholder={t(
                                                'suscripciones:change-plan.plan_placeholder',
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
                                {subscription?.plan && (
                                    <p className="text-xs text-muted-foreground">
                                        {t(
                                            'suscripciones:change-plan.current_plan',
                                            {
                                                name: subscription.plan
                                                    .nombre,
                                            },
                                        )}
                                    </p>
                                )}
                            </div>

                            <label
                                htmlFor="sub-keep-price"
                                className="flex cursor-pointer items-start gap-2 rounded-md border border-input bg-muted/30 p-3 text-sm"
                            >
                                <Checkbox
                                    id="sub-keep-price"
                                    checked={keepPrice}
                                    onCheckedChange={(checked) =>
                                        setKeepPrice(checked === true)
                                    }
                                    className="mt-0.5"
                                />
                                <span className="flex flex-col">
                                    <span className="font-medium text-foreground">
                                        {t(
                                            'suscripciones:change-plan.keep_price_label',
                                        )}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {t(
                                            'suscripciones:change-plan.keep_price_hint',
                                        )}
                                    </span>
                                </span>
                            </label>
                        </div>
                    )}

                    {mode === 'cancel' && (
                        <div className="flex flex-col gap-3">
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="sub-reason">
                                    {t('suscripciones:cancel.reason_label')}{' '}
                                    <span
                                        className="text-destructive"
                                        aria-hidden="true"
                                    >
                                        *
                                    </span>
                                </Label>
                                <Textarea
                                    id="sub-reason"
                                    value={reason}
                                    onChange={(e) =>
                                        setReason(e.target.value)
                                    }
                                    placeholder={t(
                                        'suscripciones:cancel.reason_placeholder',
                                    )}
                                    rows={2}
                                    autoFocus
                                />
                                <p className="text-xs text-muted-foreground">
                                    {t('suscripciones:cancel.reason_hint', {
                                        min: minReasonLength,
                                    })}
                                </p>
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="sub-feedback">
                                    {t('suscripciones:cancel.feedback_label')}
                                </Label>
                                <Textarea
                                    id="sub-feedback"
                                    value={feedback}
                                    onChange={(e) =>
                                        setFeedback(e.target.value)
                                    }
                                    placeholder={t(
                                        'suscripciones:cancel.feedback_placeholder',
                                    )}
                                    rows={3}
                                />
                            </div>
                        </div>
                    )}

                    {error && (
                        <p className="text-xs text-destructive">{error}</p>
                    )}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                            className="cursor-pointer"
                        >
                            {t('common:actions.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || !canSubmit}
                            className={themes[mode].buttonClass}
                        >
                            {processing && (
                                <Loader2
                                    className="size-4 animate-spin"
                                    aria-hidden="true"
                                />
                            )}
                            {processing
                                ? t('suscripciones:common.loading')
                                : t(`suscripciones:${mode}.confirm`)}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
