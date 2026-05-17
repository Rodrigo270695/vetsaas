import {
    Copy,
    ExternalLink,
    Lock,
    MoreHorizontal,
    PauseCircle,
    Pencil,
    PlayCircle,
    ScreenShare,
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
    onEnterSupport?: (tenant: Tenant) => void;
    canUpdate?: boolean;
    canDelete?: boolean;
    canSuspend?: boolean;
    canResume?: boolean;
    canImpersonate?: boolean;
};

/**
 * Dropdown de acciones por fila para tenants.
 *
 * Lógica de visibilidad:
 *  - "Copiar slug" → siempre disponible.
 *  - "Abrir subdominio" → siempre disponible (link externo).
 *  - "Editar" → solo si hay permiso y el tenant no está cancelado.
 *  - "Suspender" → solo si está trial/active y hay permiso `suspend`.
 *  - "Reanudar" → solo si está suspended y hay permiso `resume`.
 *  - "Eliminar" → solo si NO está active y hay permiso `delete`.
 *  - Si el tenant está cancelado, mostramos un item informativo "Bloqueado".
 */
export function TenantRowActions({
    tenant,
    onEdit,
    onDelete,
    onSuspend,
    onResume,
    onEnterSupport,
    canUpdate = true,
    canDelete = true,
    canSuspend = true,
    canResume = true,
    canImpersonate = false,
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
            <DropdownMenuContent align="end" className="w-56">
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

                {(showEdit || showSuspend || showResume || showDelete) && (
                    <DropdownMenuSeparator />
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
