import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle, CalendarClock, ExternalLink } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
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
import type { Auth } from '@/types/auth';
import type { TenantShared } from '@/types/tenant';
import { cn } from '@/lib/utils';

export type SubscriptionRenewalAlert = {
    days_until_renewal: number;
    renewal_anchor_at: string | null;
    urgency: 'yellow' | 'amber' | 'red' | 'ok' | 'danger' | 'muted';
    plan_nombre: string | null;
    renewal_url: string | null;
    subscription_url: string;
};

const STORAGE_PREFIX = 'vetsaas.subscription-renewal-modal';

function todayKey(): string {
    return new Date().toISOString().slice(0, 10);
}

function storageKey(tenantId: string, userId: string): string {
    return `${STORAGE_PREFIX}.${tenantId}.${userId}`;
}

function wasShownToday(tenantId: string, userId: string): boolean {
    if (typeof window === 'undefined') return true;

    return localStorage.getItem(storageKey(tenantId, userId)) === todayKey();
}

function markShownToday(tenantId: string, userId: string): void {
    if (typeof window === 'undefined') return;

    localStorage.setItem(storageKey(tenantId, userId), todayKey());
}

function urgencyStyles(
    urgency: SubscriptionRenewalAlert['urgency'],
): { ring: string; icon: string; badge: string } {
    switch (urgency) {
        case 'red':
            return {
                ring: 'ring-red-500/30',
                icon: 'text-red-600 dark:text-red-400',
                badge: 'bg-red-500/10 text-red-700 dark:text-red-300 ring-red-500/20',
            };
        case 'amber':
            return {
                ring: 'ring-amber-500/30',
                icon: 'text-amber-600 dark:text-amber-400',
                badge: 'bg-amber-500/10 text-amber-800 dark:text-amber-300 ring-amber-500/20',
            };
        case 'yellow':
            return {
                ring: 'ring-yellow-500/30',
                icon: 'text-yellow-700 dark:text-yellow-400',
                badge: 'bg-yellow-500/10 text-yellow-800 dark:text-yellow-300 ring-yellow-500/20',
            };
        default:
            return {
                ring: 'ring-primary/20',
                icon: 'text-primary',
                badge: 'bg-primary/10 text-primary ring-primary/20',
            };
    }
}

function formatAnchorDate(value: string | null, locale: string): string | null {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;

    return date.toLocaleDateString(locale === 'en' ? 'en-US' : 'es-PE', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}

/**
 * Modal diario (máx. 1 vez por día) cuando faltan 7 días o menos
 * para el próximo cobro. Solo para admins con config-general.view.
 */
export function SubscriptionRenewalReminderModal() {
    const { t, i18n } = useTranslation(['config-suscripcion', 'common']);
    const page = usePage<{
        auth: Auth;
        tenant: TenantShared | null;
        subscription_renewal_alert: SubscriptionRenewalAlert | null;
        url: string;
    }>();

    const alert = page.props.subscription_renewal_alert;
    const tenant = page.props.tenant;
    const userId = page.props.auth.user?.id;
    const currentPath = page.url.split('?')[0] ?? '';

    const [open, setOpen] = useState(false);

    const shouldOffer = useMemo(() => {
        if (!alert || !tenant?.id || !userId) return false;
        if (currentPath.startsWith('/configuracion/suscripcion')) return false;
        if (wasShownToday(tenant.id, userId)) return false;

        return true;
    }, [alert, tenant?.id, userId, currentPath]);

    useEffect(() => {
        if (shouldOffer) {
            setOpen(true);
        }
    }, [shouldOffer]);

    const dismiss = useCallback(() => {
        if (tenant?.id && userId) {
            markShownToday(tenant.id, userId);
        }
        setOpen(false);
    }, [tenant?.id, userId]);

    if (!alert || !tenant?.id || !userId) {
        return null;
    }

    const styles = urgencyStyles(alert.urgency);
    const anchorLabel = formatAnchorDate(
        alert.renewal_anchor_at,
        i18n.language,
    );
    const daysLabel =
        alert.days_until_renewal === 0
            ? t('days_until.today')
            : t('days_until.future', { count: alert.days_until_renewal });

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) dismiss();
            }}
        >
            <DialogContent className={cn('gap-5 sm:max-w-md', styles.ring, 'ring-2')}>
                <DialogHeader className="gap-3">
                    <div className="flex items-start gap-3">
                        <span
                            className={cn(
                                'flex size-10 shrink-0 items-center justify-center rounded-full ring-1 ring-inset',
                                styles.badge,
                            )}
                        >
                            <AlertTriangle className={cn('size-5', styles.icon)} />
                        </span>
                        <div className="space-y-1">
                            <DialogTitle>{t('modal.title')}</DialogTitle>
                            <DialogDescription className="text-sm leading-relaxed">
                                {t('modal.description', {
                                    plan: alert.plan_nombre ?? t('empty_plan'),
                                })}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div className="space-y-3 rounded-lg border border-border/60 bg-muted/20 p-4">
                    <div className="flex items-center gap-2 text-sm font-medium">
                        <CalendarClock className={cn('size-4', styles.icon)} />
                        <span>{daysLabel}</span>
                    </div>
                    {anchorLabel && (
                        <p className="text-sm text-muted-foreground">
                            {t('modal.due_date', { date: anchorLabel })}
                        </p>
                    )}
                    <p className="text-sm text-muted-foreground">
                        {t(`alerts.${alert.urgency}`)}
                    </p>
                </div>

                <DialogFooter className="flex-col gap-2 sm:flex-col sm:items-stretch">
                    <Button asChild onClick={dismiss}>
                        <Link href={alert.subscription_url}>
                            {t('modal.view_subscription')}
                        </Link>
                    </Button>
                    {alert.renewal_url && (
                        <Button variant="outline" asChild onClick={dismiss}>
                            <a
                                href={alert.renewal_url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {t('renew_cta')}
                                <ExternalLink className="size-4" />
                            </a>
                        </Button>
                    )}
                    <Button variant="ghost" onClick={dismiss}>
                        {t('modal.dismiss')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
