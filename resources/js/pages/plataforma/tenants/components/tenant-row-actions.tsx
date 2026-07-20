import { router } from '@inertiajs/react';
import {
    Copy,
    ExternalLink,
    Gauge,
    Globe,
    LayoutGrid,
    Lock,
    MessageCircle,
    MoreHorizontal,
    PauseCircle,
    Pencil,
    PlayCircle,
    RefreshCw,
    ScreenShare,
    Square,
    Trash2,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { tenantLoginUrl, useTenancy } from '@/lib/tenancy-url';
import { toastManager } from '@/lib/toast';
import type { Tenant } from '../types';

export type TenantRowActionsProps = {
    tenant: Tenant;
    onEdit: (tenant: Tenant) => void;
    onDelete: (tenant: Tenant) => void;
    onSuspend: (tenant: Tenant) => void;
    onResume: (tenant: Tenant) => void;
    onChangeSlug?: (tenant: Tenant) => void;
    onEnterSupport?: (tenant: Tenant) => void;
    onRestartWhatsApp?: (tenant: Tenant) => void;
    onStopWhatsApp?: (tenant: Tenant) => void;
    canUpdate?: boolean;
    canDelete?: boolean;
    canSuspend?: boolean;
    canResume?: boolean;
    canImpersonate?: boolean;
    canRestartWhatsApp?: boolean;
    canStopWhatsApp?: boolean;
    openwaConfigured?: boolean;
};

/**
 * Dropdown de acciones por fila para tenants.
 */
export function TenantRowActions({
    tenant,
    onEdit,
    onDelete,
    onSuspend,
    onResume,
    onChangeSlug,
    onEnterSupport,
    onRestartWhatsApp,
    onStopWhatsApp,
    canUpdate = true,
    canDelete = true,
    canSuspend = true,
    canResume = true,
    canImpersonate = false,
    canRestartWhatsApp = false,
    canStopWhatsApp = false,
    openwaConfigured = false,
}: TenantRowActionsProps) {
    const { t } = useTranslation(['tenants', 'common']);
    const tenancy = useTenancy();

    const isCancelled = tenant.estado === 'cancelled';
    const isSuspended = tenant.estado === 'suspended';
    const isActive = tenant.estado === 'active';

    const showEdit = canUpdate && !isCancelled;
    const showSuspend = canSuspend && (isActive || tenant.estado === 'trial');
    const showResume = canResume && isSuspended;
    const showDelete = canDelete && !isActive;

    const showEnterSupport =
        canImpersonate &&
        typeof onEnterSupport === 'function' &&
        ['trial', 'active'].includes(tenant.estado);

    const showChangeSlug =
        canUpdate &&
        typeof onChangeSlug === 'function' &&
        !isCancelled;

    const showWhatsAppActions =
        openwaConfigured &&
        !isCancelled &&
        (canRestartWhatsApp || canStopWhatsApp);

    const whatsappStatus = tenant.whatsapp_session?.status ?? 'sin_sesion';

    const handleCopy = async () => {
        try {
            await navigator.clipboard.writeText(tenant.slug);
            toastManager.success({
                title: t('tenants:toast.slug_copied'),
                description: tenant.slug,
                duration: 2000,
            });
        } catch {
            toastManager.error({
                title: t('common:feedback.copy_error'),
            });
        }
    };

    const subdomainUrl = tenantLoginUrl(tenant.slug, tenancy);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={t('tenants:row.actions_for', {
                        name: tenant.razon_social,
                    })}
                    className="size-8 cursor-pointer"
                >
                    <MoreHorizontal className="size-4" strokeWidth={2.5} />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-60">
                <DropdownMenuItem
                    onSelect={handleCopy}
                    className="cursor-pointer gap-2"
                >
                    <Copy className="size-4" strokeWidth={2.25} />
                    {t('tenants:row.copy_slug')}
                </DropdownMenuItem>

                <DropdownMenuItem asChild className="cursor-pointer gap-2">
                    <a
                        href={subdomainUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <ExternalLink
                            className="size-4"
                            strokeWidth={2.25}
                        />
                        {t('tenants:row.open_subdomain')}
                    </a>
                </DropdownMenuItem>

                {showEnterSupport ? (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={() => onEnterSupport?.(tenant)}
                            className="cursor-pointer gap-2 text-violet-700 focus:text-violet-700 dark:text-violet-400"
                        >
                            <ScreenShare className="size-4 shrink-0" strokeWidth={2.25} />
                            {t('tenants:row.enter_support')}
                        </DropdownMenuItem>
                    </>
                ) : null}

                {showWhatsAppActions ? (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            disabled
                            className="gap-2 text-xs text-muted-foreground"
                        >
                            <MessageCircle className="size-3.5" strokeWidth={2.25} />
                            {t('tenants:row.whatsapp_status', { status: whatsappStatus })}
                        </DropdownMenuItem>
                        {canRestartWhatsApp ? (
                            <DropdownMenuItem
                                onSelect={() => onRestartWhatsApp?.(tenant)}
                                className="cursor-pointer gap-2 text-emerald-700 focus:text-emerald-700 dark:text-emerald-400"
                            >
                                <RefreshCw className="size-4" strokeWidth={2.25} />
                                {t('tenants:row.whatsapp_restart')}
                            </DropdownMenuItem>
                        ) : null}
                        {canStopWhatsApp ? (
                            <DropdownMenuItem
                                onSelect={() => onStopWhatsApp?.(tenant)}
                                className="cursor-pointer gap-2 text-amber-700 focus:text-amber-700 dark:text-amber-400"
                            >
                                <Square className="size-4" strokeWidth={2.25} />
                                {t('tenants:row.whatsapp_stop')}
                            </DropdownMenuItem>
                        ) : null}
                    </>
                ) : null}

                {(showEdit || showChangeSlug || showSuspend || showResume || showDelete) && (
                    <DropdownMenuSeparator />
                )}

                {showChangeSlug && (
                    <DropdownMenuItem
                        onSelect={() => onChangeSlug?.(tenant)}
                        className="cursor-pointer gap-2"
                    >
                        <Globe className="size-4" strokeWidth={2.25} />
                        {t('tenants:row.change_slug')}
                    </DropdownMenuItem>
                )}

                {canUpdate && !isCancelled && (
                    <DropdownMenuItem
                        onSelect={() => router.visit(`/plataforma/tenants/${tenant.id}/modulos`)}
                        className="cursor-pointer gap-2"
                    >
                        <LayoutGrid className="size-4" strokeWidth={2.25} />
                        {t('tenants:row.modules')}
                    </DropdownMenuItem>
                )}

                {canUpdate && !isCancelled && (
                    <DropdownMenuItem
                        onSelect={() => router.visit(`/plataforma/tenants/${tenant.id}/limites`)}
                        className="cursor-pointer gap-2"
                    >
                        <Gauge className="size-4" strokeWidth={2.25} />
                        {t('tenants:row.limits')}
                    </DropdownMenuItem>
                )}

                {showEdit && (
                    <DropdownMenuItem
                        onSelect={() => onEdit(tenant)}
                        className="cursor-pointer gap-2"
                    >
                        <Pencil className="size-4" strokeWidth={2.25} />
                        {t('common:actions.edit')}
                    </DropdownMenuItem>
                )}

                {showSuspend && (
                    <DropdownMenuItem
                        onSelect={() => onSuspend(tenant)}
                        className="cursor-pointer gap-2 text-amber-700 focus:text-amber-700 dark:text-amber-400"
                    >
                        <PauseCircle className="size-4" strokeWidth={2.25} />
                        {t('tenants:row.suspend')}
                    </DropdownMenuItem>
                )}

                {showResume && (
                    <DropdownMenuItem
                        onSelect={() => onResume(tenant)}
                        className="cursor-pointer gap-2 text-emerald-700 focus:text-emerald-700 dark:text-emerald-400"
                    >
                        <PlayCircle className="size-4" strokeWidth={2.25} />
                        {t('tenants:row.resume')}
                    </DropdownMenuItem>
                )}

                {showDelete && (
                    <DropdownMenuItem
                        onSelect={() => onDelete(tenant)}
                        className="cursor-pointer gap-2 text-destructive focus:text-destructive"
                    >
                        <Trash2 className="size-4" strokeWidth={2.25} />
                        {t('common:actions.delete')}
                    </DropdownMenuItem>
                )}

                {isCancelled && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            disabled
                            className="gap-2 text-xs text-muted-foreground"
                        >
                            <Lock className="size-3.5" strokeWidth={2.25} />
                            {t('tenants:row.cancelled_locked')}
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
