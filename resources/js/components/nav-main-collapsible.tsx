import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useMemo } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { isNavRouteImplemented } from '@/config/nav-implemented';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { OfflineAwareLink } from '@/components/offline-aware-link';
import { isOfflinePath } from '@/lib/offline/offline-routes';
import type { NavContext, NavGroup, NavItem } from '@/types';

function isItemImplemented(item: NavItem): boolean {
    if (item.implemented === false) {
        return false;
    }

    const href = typeof item.href === 'string' ? item.href : '';

    return href === '' || isNavRouteImplemented(href);
}

type NavMainCollapsibleProps = {
    /** Label discreta arriba del grupo (e.g. "Módulos"). */
    label?: string;
    /** Items que aparecen al inicio sin agrupar (e.g. Dashboard). */
    singles?: NavItem[];
    /** Grupos desplegables con sub-items. */
    groups: NavGroup[];
};

/**
 * Navegación principal del sidebar con grupos colapsables premium.
 *
 * Mejoras visuales:
 * - Indicador vertical brand a la izquierda del sub-item activo (barra de 2.5px
 *   que aparece con fade + slide desde la izquierda).
 * - Hover sutil con `translate-x` ligero y fondo brand/10.
 * - Iconos de los sub-items con cambio de color en hover/active.
 * - Stagger animation de los sub-items al abrir el grupo (cada uno 25ms después).
 * - Caret (ChevronRight) que rota 90° suavemente.
 * - Texto del grupo con `font-medium` y mayor jerarquía visual.
 *
 * El componente respeta el modo `collapsible="icon"` del sidebar — al colapsar
 * solo se ven los iconos de los grupos (los sub-items se ocultan).
 */
/**
 * ¿El item/grupo aplica al contexto actual?
 *
 * - `both` (o ausente) → siempre.
 * - `central` → solo en el host central (sin tenant resuelto).
 * - `tenant` → solo cuando estamos dentro de un subdominio de clínica.
 *
 * **El rol `superadmin` recibe bypass total del filtro de contexto**
 * (consistente con el bypass de permisos en {@see usePermission}). Ver
 * TODOS los grupos en cualquier host le da una vista mental completa
 * de la plataforma y los módulos disponibles a sus clínicas, además de
 * un atajo rápido para inspeccionar cualquier área durante soporte.
 *
 * Cuando hace click en un módulo tenant desde el host central, el
 * middleware {@see \App\Http\Middleware\EnsureTenant} le muestra una
 * pantalla informativa amigable (`shared/tenant-required.tsx`) con
 * CTA hacia **Plataforma › Tenants** — no rompe nada. En Fase 5 esta
 * pantalla quedará reemplazada por impersonation directa.
 */
function matchesContext(
    context: NavContext | undefined,
    hasTenant: boolean,
    isSuperadmin: boolean,
): boolean {
    if (isSuperadmin) return true;
    if (!context || context === 'both') return true;
    if (context === 'tenant') return hasTenant;
    if (context === 'central') return !hasTenant;
    return true;
}

export function NavMainCollapsible({
    label,
    singles = [],
    groups,
}: NavMainCollapsibleProps) {
    const { isCurrentUrl, isCurrentOrParentUrl, currentUrl } = useCurrentUrl();
    const { isMobile, setOpenMobile } = useSidebar();
    const { can, isSuperadmin } = usePermission();
    const tenant = usePage().props.tenant;
    const hasTenant = tenant !== null && tenant !== undefined;

    const closeMobileSidebar = () => {
        if (isMobile) {
            setOpenMobile(false);
        }
    };

    const visibleSingles = useMemo(
        () =>
            singles.filter(
                (item) =>
                    isItemImplemented(item) &&
                    (!item.permission || can(item.permission)) &&
                    matchesContext(item.context, hasTenant, isSuperadmin),
            ),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [singles, hasTenant, isSuperadmin],
    );

    const visibleGroups = useMemo(() => {
        return groups
            .map((group) => ({
                ...group,
                items: group.items.filter(
                    (item) =>
                        isItemImplemented(item) &&
                        (!item.permission || can(item.permission)) &&
                        matchesContext(
                            item.context ?? group.context,
                            hasTenant,
                            isSuperadmin,
                        ),
                ),
            }))
            .filter((group) => {
                if (group.permission && !can(group.permission)) return false;
                if (!matchesContext(group.context, hasTenant, isSuperadmin)) {
                    return false;
                }
                return group.items.length > 0;
            });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [groups, hasTenant, isSuperadmin]);

    const initialOpenMap = useMemo(() => {
        const map: Record<string, boolean> = {};
        for (const group of visibleGroups) {
            const hasActiveChild = group.items.some((item) =>
                isCurrentOrParentUrl(item.href),
            );
            map[group.title] = hasActiveChild || group.defaultOpen === true;
        }
        return map;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [currentUrl, visibleGroups]);

    if (visibleSingles.length === 0 && visibleGroups.length === 0) {
        return null;
    }

    return (
        <SidebarGroup className="px-2 py-0">
            {label && (
                <SidebarGroupLabel className="text-[0.7rem] font-semibold tracking-wider text-muted-foreground uppercase">
                    {label}
                </SidebarGroupLabel>
            )}

            <SidebarMenu>
                {visibleSingles.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={isCurrentUrl(item.href)}
                            tooltip={{ children: item.title }}
                            className="font-medium transition-all data-[active=true]:bg-primary/10 data-[active=true]:text-primary"
                        >
                            <Link
                                href={item.href}
                                prefetch
                                onClick={closeMobileSidebar}
                            >
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}

                {visibleGroups.map((group) => (
                    <Collapsible
                        key={group.title}
                        asChild
                        defaultOpen={initialOpenMap[group.title]}
                        className="group/collapsible"
                    >
                        <SidebarMenuItem>
                            <CollapsibleTrigger asChild>
                                <SidebarMenuButton
                                    tooltip={{ children: group.title }}
                                    className="group/trigger cursor-pointer font-medium transition-all hover:bg-primary/8"
                                >
                                    {group.icon && (
                                        <group.icon className="transition-colors group-data-[state=open]/collapsible:text-primary" />
                                    )}
                                    <span className="transition-colors group-data-[state=open]/collapsible:text-foreground">
                                        {group.title}
                                    </span>
                                    <ChevronRight className="ml-auto size-4 text-muted-foreground transition-all duration-300 group-data-[state=open]/collapsible:rotate-90 group-data-[state=open]/collapsible:text-primary" />
                                </SidebarMenuButton>
                            </CollapsibleTrigger>

                            <CollapsibleContent className="overflow-hidden data-[state=closed]:animate-collapsible-up data-[state=open]:animate-collapsible-down">
                                <SidebarMenuSub className="mt-1 gap-0.5 border-sidebar-border/50">
                                    {group.items.map((item, index) => (
                                        <NavSubItem
                                            key={item.title}
                                            item={item}
                                            active={isCurrentOrParentUrl(
                                                item.href,
                                            )}
                                            index={index}
                                            onNavigate={closeMobileSidebar}
                                        />
                                    ))}
                                </SidebarMenuSub>
                            </CollapsibleContent>
                        </SidebarMenuItem>
                    </Collapsible>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}

type NavSubItemProps = {
    item: NavItem;
    active: boolean;
    /** Posición dentro del grupo, usada para stagger animation. */
    index: number;
    /** Callback al hacer click — útil para cerrar sidebar en móvil. */
    onNavigate?: () => void;
};

/**
 * Sub-item individual del menú desplegable.
 * Lleva su propia animación de entrada (stagger por índice).
 */
function NavSubItem({ item, active, index, onNavigate }: NavSubItemProps) {
    const LinkComponent = isOfflinePath(item.href) ? OfflineAwareLink : Link;

    return (
        <SidebarMenuSubItem
            style={{ animationDelay: `${index * 30}ms` }}
            className="animate-in fade-in slide-in-from-left-2 fill-mode-both duration-300"
        >
            <LinkComponent
                href={item.href}
                prefetch
                onClick={onNavigate}
                data-active={active}
                className={cn(
                    'group/sub relative flex h-9 items-center gap-2.5 overflow-hidden rounded-md pr-2 pl-3 text-sm transition-all duration-200 outline-hidden focus-visible:ring-2 focus-visible:ring-ring',
                    active
                        ? 'bg-primary/10 font-medium text-primary'
                        : 'text-sidebar-foreground/85 hover:translate-x-0.5 hover:bg-primary/8 hover:text-foreground',
                )}
            >
                {/* Barra vertical brand cuando está activo */}
                <span
                    aria-hidden="true"
                    className={cn(
                        'absolute top-1.5 bottom-1.5 left-0 w-[2.5px] rounded-r-full bg-primary transition-all duration-200',
                        active
                            ? 'translate-x-0 opacity-100'
                            : '-translate-x-1 opacity-0',
                    )}
                />

                {item.icon && (
                    <item.icon
                        strokeWidth={2.25}
                        className={cn(
                            'size-4 shrink-0 transition-colors duration-200',
                            active
                                ? 'text-primary'
                                : 'text-muted-foreground group-hover/sub:text-primary',
                        )}
                    />
                )}

                <span className="truncate">{item.title}</span>
            </LinkComponent>
        </SidebarMenuSubItem>
    );
}

