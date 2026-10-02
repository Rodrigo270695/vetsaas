import { Link, usePage } from '@inertiajs/react';
import { Gem } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useSidebar } from '@/components/ui/sidebar';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';

type TenantPlan = {
    nombre: string;
    codigo: string;
    badge: string | null;
    color_hex: string | null;
};

function planName(plan: TenantPlan): string {
    const nombre = plan.nombre.trim();
    if (nombre !== '') {
        return nombre;
    }

    const codigo = plan.codigo.trim();

    return codigo !== '' ? codigo : 'Plan';
}

function planColor(plan: TenantPlan): string {
    if (plan.color_hex) {
        return plan.color_hex;
    }

    return plan.codigo === 'free' ? '#64748b' : '#4f46e5';
}

function hexToRgb(hex: string): { r: number; g: number; b: number } | null {
    const raw = hex.trim().replace('#', '');
    const full = raw.length === 3
        ? raw.split('').map((char) => char + char).join('')
        : raw.slice(0, 6);

    if (!/^[0-9a-fA-F]{6}$/.test(full)) {
        return null;
    }

    return {
        r: Number.parseInt(full.slice(0, 2), 16),
        g: Number.parseInt(full.slice(2, 4), 16),
        b: Number.parseInt(full.slice(4, 6), 16),
    };
}

function rgba(hex: string, alpha: number): string {
    const rgb = hexToRgb(hex);
    if (!rgb) {
        return `rgba(79, 70, 229, ${alpha})`;
    }

    return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${alpha})`;
}

function collapsedMark(name: string): string {
    const clean = name.trim();
    if (clean.length <= 3) {
        return clean;
    }

    return clean.slice(0, 2);
}

export function SidebarPlanBadge() {
    const { t } = useTranslation('nav');
    const plan = usePage().props.tenant_plan as TenantPlan | null;
    const { can } = usePermission();
    const { state, isMobile } = useSidebar();
    const collapsed = !isMobile && state === 'collapsed';

    if (!plan) {
        return null;
    }

    const name = planName(plan);
    const color = planColor(plan);
    const href = can('config-general.view') ? '/configuracion/suscripcion' : null;
    const kicker = t('plan_kicker');

    if (collapsed) {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <span
                        className="mx-auto mb-2 flex size-8 items-center justify-center rounded-full text-[10px] font-semibold tracking-tight"
                        style={{
                            color,
                            backgroundColor: rgba(color, 0.14),
                            boxShadow: `inset 0 0 0 1px ${rgba(color, 0.35)}`,
                        }}
                    >
                        {collapsedMark(name)}
                    </span>
                </TooltipTrigger>
                <TooltipContent side="right">{name}</TooltipContent>
            </Tooltip>
        );
    }

    const card = (
        <span className="relative flex items-center gap-2.5 px-2.5 py-2">
            <span
                aria-hidden
                className="pointer-events-none absolute inset-0"
                style={{
                    background: `radial-gradient(90% 140% at 0% 50%, ${rgba(color, 0.18)}, transparent 62%)`,
                }}
            />
            <span
                className="relative flex size-8 shrink-0 items-center justify-center rounded-lg"
                style={{
                    color,
                    backgroundColor: rgba(color, 0.12),
                    boxShadow: `inset 0 0 0 1px ${rgba(color, 0.28)}`,
                }}
            >
                <Gem className="size-3.5" strokeWidth={2.25} />
            </span>
            <span className="relative min-w-0">
                <span className="block truncate text-[13px] font-semibold leading-none tracking-tight text-foreground">
                    {name}
                </span>
                <span className="mt-1 block text-[10px] leading-none text-muted-foreground">
                    {kicker}
                </span>
            </span>
            <span
                aria-hidden
                className="relative ml-auto h-6 w-px shrink-0"
                style={{
                    background: `linear-gradient(to bottom, transparent, ${rgba(color, 0.7)}, transparent)`,
                }}
            />
        </span>
    );

    const className = cn(
        'mx-2 mb-2 block overflow-hidden rounded-xl border bg-card/80 shadow-sm',
        href && 'cursor-pointer transition-transform duration-200 hover:-translate-y-px hover:shadow-md',
    );

    return (
        <div className={className} style={{ borderColor: rgba(color, 0.28) }}>
            {href ? (
                <Link href={href} className="block" title={name}>
                    {card}
                </Link>
            ) : (
                card
            )}
        </div>
    );
}
