import { router, usePage } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import tenantImpersonation from '@/routes/impersonate';

export function TenantImpersonationBanner() {
    const { t } = useTranslation(['common']);
    const { tenant_impersonation: imp } = usePage().props;

    if (!imp || typeof imp !== 'object' || !('tenant_label' in imp)) {
        return null;
    }

    const label =
        typeof imp.tenant_label === 'string' && imp.tenant_label.trim() !== ''
            ? imp.tenant_label
            : t('impersonation.banner_fallback_clinic');

    const onLeave = (): void => {
        router.post(tenantImpersonation.leave.url());
    };

    return (
        <div className="shrink-0 border-b border-destructive/40 bg-destructive/15 px-4 py-2.5">
            <Alert variant="destructive" className="border-destructive/60 bg-transparent py-2 shadow-none">
                <ShieldAlert className="size-4 text-destructive" aria-hidden />
                <AlertTitle className="text-sm font-semibold">
                    {t('impersonation.banner_title')}
                </AlertTitle>
                <AlertDescription className="flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                    <span>
                        {t('impersonation.banner_body', { clinic: label })}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="cursor-pointer border-destructive/70 bg-background whitespace-nowrap text-destructive hover:bg-destructive/10 hover:text-destructive"
                        onClick={onLeave}
                    >
                        {t('impersonation.banner_leave')}
                    </Button>
                </AlertDescription>
            </Alert>
        </div>
    );
}
