import { Link, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { usePermission } from '@/hooks/use-permission';
import { useSidebar } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';

function planLabel(plan: { nombre: string; codigo: string; badge: string | null }): string {
    const badge = (plan.badge ?? '').trim();
    if (badge !== '' && badge.length <= 16) {
        return badge;
    }

    const codigo = plan.codigo.trim();
    if (codigo !== '') {
        return codigo.toUpperCase();
    }

    return plan.nombre;
}

function planColor(plan: { codigo: string; color_hex: string | null }): string {
    if (plan.color_hex) {
        return plan.color_hex;
    }

    return plan.codigo === 'free' ? '#64748b' : '#4f46e5';
}

export function SidebarPlanBadge() {
    const { t } = useTranslation('nav');
    const plan = usePage().props.tenant_plan;
    const { can } = usePermission();
    const { state, isMobile } = useSidebar();
    const collapsed = !isMobile && state === 'collapsed';

    if (!plan) {
        return null;
    }

    const label = planLabel(plan);
    const color = planColor(plan);
    const href = can('config-general.view') ? '/configuracion/suscripcion' : null;
    const title = `${t('plan_kicker')} ${label}`;

    if (collapsed) {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <span
                        className="mx-auto mb-1 flex size-8 items-center justify-center rounded-lg text-[10px] font-bold tracking-wide text-white"
                        style={{ backgroundColor: color }}
                    >
                        {label.slice(0, 3)}
                    </span>
                </TooltipTrigger>
                <TooltipContent side="right">{title}</TooltipContent>
            </Tooltip>
        );
    }

    const body = (
        <span className="flex items-center justify-between gap-2">
            <span className="text-[10px] font-medium tracking-[0.16em] text-muted-foreground uppercase">
                {t('plan_kicker')}
            </span>
            <span
                className="rounded-md px-1.5 py-0.5 text-[11px] font-semibold tracking-wide text-white"
                style={{ backgroundColor: color }}
            >
                {label}
            </span>
        </span>
    );

    const className = cn(
        'mx-2 mb-2 block overflow-hidden rounded-lg border border-border/70 bg-card shadow-sm',
        href && 'cursor-pointer transition-colors hover:bg-muted/40',
    );

    return (
        <div className={className}>
            <span className="block h-0.5" style={{ backgroundColor: color }} />
            {href ? (
                <Link href={href} className="block px-2.5 py-2" title={title}>
                    {body}
                </Link>
            ) : (
                <span className="block px-2.5 py-2">{body}</span>
            )}
        </div>
    );
}
