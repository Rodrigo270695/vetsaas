import { router } from '@inertiajs/react';
import { Loader2, PauseCircle, PlayCircle } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { Trans, useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import tenants from '@/routes/plataforma/tenants';
import type { Tenant } from '../types';

export type TenantSuspendDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    tenant: Tenant | null;
    /**
     * Modo del diálogo:
     *   - 'suspend' → pide motivo y dispara POST /tenants/{id}/suspend.
     *   - 'resume'  → confirmación simple y dispara POST /tenants/{id}/resume.
     */
    mode: 'suspend' | 'resume';
};

/**
 * Diálogo dedicado a suspender / reanudar un tenant.
 *
 * - Suspensión: requiere un motivo (mín. 5 caracteres). Se guarda en
 *   `suspension_reason` para que cualquier operador vea el contexto al
 *   reabrir un caso de soporte.
 * - Reanudación: solo confirmación, sin texto libre.
 *
 * Ambas acciones son reversibles. Para la cancelación definitiva se
 * usa el `TenantDeleteDialog`.
 */
export function TenantSuspendDialog({
    open,
    onOpenChange,
    tenant,
    mode,
}: TenantSuspendDialogProps) {
    const { t } = useTranslation(['tenants', 'common']);
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (open) {
            setReason('');
            setError(null);
        }
    }, [open]);

    const isSuspend = mode === 'suspend';
    const minReasonLength = 5;
    const canSubmitSuspend = reason.trim().length >= minReasonLength;
    const canSubmit = isSuspend ? canSubmitSuspend : true;

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!tenant) return;

        setProcessing(true);
        setError(null);

        const url = isSuspend
            ? tenants.suspend(tenant.id).url
            : tenants.resume(tenant.id).url;

        const payload = isSuspend ? { reason: reason.trim() } : {};

        router.post(url, payload, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
            onError: (errors) => {
                const specific =
                    (errors as Record<string, string | undefined>)?.reason ??
                    (errors as Record<string, string | undefined>)?.estado ??
                    null;
                setError(specific ?? t('common:feedback.save_error'));
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={onSubmit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <div
                            className={
                                isSuspend
                                    ? 'flex size-11 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                    : 'flex size-11 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                            }
                        >
                            {isSuspend ? (
                                <PauseCircle
                                    className="size-5"
                                    strokeWidth={2.5}
                                    aria-hidden="true"
                                />
                            ) : (
                                <PlayCircle
                                    className="size-5"
                                    strokeWidth={2.5}
                                    aria-hidden="true"
                                />
                            )}
                        </div>
                        <DialogTitle className="pt-2 text-base">
                            {isSuspend
                                ? t('tenants:suspend.title')
                                : t('tenants:resume.title')}
                        </DialogTitle>
                        <DialogDescription className="text-sm" asChild>
                            <p>
                                <Trans
                                    ns="tenants"
                                    i18nKey={
                                        isSuspend
                                            ? 'suspend.description'
                                            : 'resume.description'
                                    }
                                    values={{
                                        name:
                                            tenant?.razon_social ??
                                            tenant?.slug ??
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

                    {isSuspend && (
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="tenant-suspend-reason">
                                {t('tenants:suspend.reason_label')}{' '}
                                <span
                                    className="text-destructive"
                                    aria-hidden="true"
                                >
                                    *
                                </span>
                            </Label>
                            <Textarea
                                id="tenant-suspend-reason"
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                                placeholder={t(
                                    'tenants:suspend.reason_placeholder',
                                )}
                                rows={3}
                                autoFocus
                            />
                            <p className="text-xs text-muted-foreground">
                                {t('tenants:suspend.reason_hint', {
                                    min: minReasonLength,
                                })}
                            </p>
                            {error && (
                                <p className="text-xs text-destructive">
                                    {error}
                                </p>
                            )}
                        </div>
                    )}

                    {!isSuspend && error && (
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
                            className={
                                isSuspend
                                    ? 'cursor-pointer gap-2 bg-amber-600 text-white hover:bg-amber-700 focus-visible:ring-amber-500/40 disabled:cursor-not-allowed'
                                    : 'cursor-pointer gap-2 bg-emerald-600 text-white hover:bg-emerald-700 focus-visible:ring-emerald-500/40 disabled:cursor-not-allowed'
                            }
                        >
                            {processing && (
                                <Loader2
                                    className="size-4 animate-spin"
                                    aria-hidden="true"
                                />
                            )}
                            {processing
                                ? t('tenants:suspend.loading')
                                : isSuspend
                                  ? t('tenants:suspend.confirm')
                                  : t('tenants:resume.confirm')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
