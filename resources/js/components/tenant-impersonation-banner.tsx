import { router, usePage } from '@inertiajs/react';
import { ShieldAlert, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import tenantImpersonation from '@/routes/impersonate';

const DISMISS_KEY = 'vetsaas:impersonation-banner-minimized';

/**
 * Aviso de modo soporte: compacto en desktop; en móvil es flotante y se puede
 * minimizar para no comerse el viewport (queda un chip para salir).
 */
export function TenantImpersonationBanner() {
    const { t } = useTranslation(['common']);
    const { tenant_impersonation: imp } = usePage().props;
    const [minimized, setMinimized] = useState(false);

    useEffect(() => {
        try {
            setMinimized(sessionStorage.getItem(DISMISS_KEY) === '1');
        } catch {
            setMinimized(false);
        }
    }, []);

    if (!imp || typeof imp !== 'object' || !('tenant_label' in imp)) {
        return null;
    }

    const label =
        typeof imp.tenant_label === 'string' && imp.tenant_label.trim() !== ''
            ? imp.tenant_label
            : t('impersonation.banner_fallback_clinic');

    const onLeave = (): void => {
        try {
            sessionStorage.removeItem(DISMISS_KEY);
        } catch {
            // ignore
        }
        router.post(tenantImpersonation.leave.url());
    };

    const onMinimize = (): void => {
        setMinimized(true);
        try {
            sessionStorage.setItem(DISMISS_KEY, '1');
        } catch {
            // ignore
        }
    };

    const onExpand = (): void => {
        setMinimized(false);
        try {
            sessionStorage.removeItem(DISMISS_KEY);
        } catch {
            // ignore
        }
    };

    if (minimized) {
        return (
            <div className="pointer-events-none fixed inset-x-0 bottom-3 z-50 flex justify-center px-3 md:bottom-4 md:justify-end md:px-4">
                <div className="pointer-events-auto flex max-w-full items-center gap-1.5 rounded-full border border-destructive/50 bg-destructive text-destructive-foreground shadow-lg">
                    <button
                        type="button"
                        onClick={onExpand}
                        className="flex min-w-0 items-center gap-1.5 rounded-l-full py-2 pr-1 pl-3 text-left"
                    >
                        <ShieldAlert className="size-3.5 shrink-0" aria-hidden />
                        <span className="truncate text-xs font-semibold">
                            {t('impersonation.chip_label', { clinic: label })}
                        </span>
                    </button>
                    <Button
                        type="button"
                        size="sm"
                        variant="secondary"
                        className="mr-1 h-7 shrink-0 rounded-full px-2.5 text-[0.7rem]"
                        onClick={onLeave}
                    >
                        {t('impersonation.banner_leave_short')}
                    </Button>
                </div>
            </div>
        );
    }

    return (
        <>
            {/* Desktop / tablet: franja compacta arriba */}
            <div className="hidden shrink-0 border-b border-destructive/40 bg-destructive/15 px-3 py-1.5 md:block">
                <div className="mx-auto flex max-w-6xl items-center gap-2">
                    <ShieldAlert className="size-3.5 shrink-0 text-destructive" aria-hidden />
                    <p className="min-w-0 flex-1 truncate text-xs text-destructive">
                        <span className="font-semibold">{t('impersonation.banner_title')}</span>
                        <span className="mx-1.5 text-destructive/50">·</span>
                        <span className="text-destructive/90">{label}</span>
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-7 shrink-0 cursor-pointer border-destructive/70 bg-background px-2.5 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                        onClick={onLeave}
                    >
                        {t('impersonation.banner_leave_short')}
                    </Button>
                </div>
            </div>

            {/* Móvil: toast flotante abajo (no empuja el layout) */}
            <div
                className={cn(
                    'pointer-events-none fixed inset-x-0 bottom-3 z-50 flex justify-center px-3 md:hidden',
                )}
                role="status"
            >
                <div className="pointer-events-auto w-full max-w-sm rounded-xl border border-destructive/40 bg-background/95 p-2.5 shadow-lg backdrop-blur supports-backdrop-filter:bg-background/90">
                    <div className="flex items-start gap-2">
                        <span className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg bg-destructive/15 text-destructive">
                            <ShieldAlert className="size-3.5" aria-hidden />
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="text-xs font-semibold text-foreground">
                                {t('impersonation.banner_title')}
                            </p>
                            <p className="mt-0.5 line-clamp-2 text-[0.7rem] leading-snug text-muted-foreground">
                                {t('impersonation.banner_body_short', { clinic: label })}
                            </p>
                        </div>
                        <button
                            type="button"
                            className="shrink-0 rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                            aria-label={t('impersonation.minimize')}
                            onClick={onMinimize}
                        >
                            <X className="size-3.5" />
                        </button>
                    </div>
                    <div className="mt-2 flex justify-end">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="h-7 border-destructive/60 px-2.5 text-xs text-destructive hover:bg-destructive/10"
                            onClick={onLeave}
                        >
                            {t('impersonation.banner_leave_short')}
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}
