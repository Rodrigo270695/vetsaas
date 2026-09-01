import { Link } from '@inertiajs/react';
import {
    Activity,
    ArrowRightLeft,
    BadgePercent,
    Banknote,
    BarChart3,
    BedDouble,
    BellRing,
    Bot,
    BookOpenCheck,
    Boxes,
    Building2,
    CalendarDays,
    CalendarOff,
    Camera,
    Clock,
    Cog,
    CreditCard,
    Gift,
    DoorOpen,
    Database,
    Dog,
    FileBarChart,
    FileText,
    FileX,
    FlaskConical,
    Folder,
    Gauge,
    Hash,
    CircleHelp,
    Headset,
    History,
    Home,
    KeyRound,
    Landmark,
    LayoutGrid,
    LineChart,
    MapPin,
    Megaphone,
    MessageCircle,
    MessagesSquare,
    MessageSquareText,
    Package,
    PawPrint,
    Pill,
    Radar,
    Receipt,
    ReceiptText,
    Repeat,
    Scissors,
    ScrollText,
    Send,
    Server,
    ShieldAlert,
    ShieldCheck,
    ShoppingCart,
    Slice,
    SlidersHorizontal,
    Smartphone,
    Sparkles,
    Stethoscope,
    Store,
    Syringe,
    Trophy,
    Truck,
    UserCog,
    Users,
    Wallet,
} from 'lucide-react';
import { useMemo } from 'react';
import { usePlatformSupportChatUnread } from '@/contexts/platform-support-chat-unread-context';
import { useTenantChatUnread } from '@/contexts/tenant-chat-unread-context';
import { useTranslation } from 'react-i18next';
import AppLogo from '@/components/app-logo';
import { NavMainCollapsible } from '@/components/nav-main-collapsible';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavGroup, NavItem } from '@/types';

/**
 * Construye los items y grupos de navegación con etiquetas traducidas.
 *
 * Se mantiene como hook porque las traducciones cambian dinámicamente
 * cuando el usuario alterna idioma desde el selector, y queremos que la
 * navegación se rerenderice en vivo sin recargar la página.
 */
function useNavConfig(): { singles: NavItem[]; groups: NavGroup[] } {
    const { t } = useTranslation('nav');

    return useMemo(
        () => ({
            singles: [
                {
                    title: t('items.dashboard'),
                    href: dashboard(),
                    icon: LayoutGrid,
                    permission: 'dashboard.view',
                },
            ],
            groups: [
                /*
                 * Bloques operativos de cada clínica: solo tienen sentido
                 * dentro de un subdominio de tenant (su backend depende
                 * del schema del tenant). En el host central se ocultan
                 * automáticamente vía `context: 'tenant'`, evitando 404
                 * para roles que tienen permisos globales (superadmin).
                 */
                {
                    title: t('groups.clinica'),
                    icon: Stethoscope,
                    context: 'tenant',
                    items: [
                        {
                            title: t('items.pacientes'),
                            href: '/clinica/pacientes',
                            icon: Dog,
                            permission: 'pacientes.view',
                        },
                        {
                            title: t('items.clinicas_asesoradas'),
                            href: '/clinica/clinicas-asesoradas',
                            icon: Building2,
                            permission: 'clinicas-asesoradas.view',
                            requiresModoAsesora: true,
                        },
                        {
                            title: t('items.propietarios'),
                            href: '/clinica/propietarios',
                            icon: Users,
                            permission: 'propietarios.view',
                        },
                        {
                            title: t('items.citas'),
                            href: '/clinica/citas',
                            icon: CalendarDays,
                            permission: 'citas.view',
                        },
                        {
                            title: t('items.historias_clinicas'),
                            href: '/clinica/historias-clinicas',
                            icon: FileText,
                            permission: 'historias-clinicas.view',
                        },
                        {
                            title: t('items.vacunaciones'),
                            href: '/clinica/vacunaciones',
                            icon: Syringe,
                            permission: 'vacunaciones.view',
                        },
                        {
                            title: t('items.recetas'),
                            href: '/clinica/recetas',
                            icon: Pill,
                            permission: 'recetas.view',
                        },
                        {
                            title: t('items.laboratorio'),
                            href: '/clinica/laboratorio',
                            icon: FlaskConical,
                            permission: 'laboratorio.view',
                        },
                        {
                            title: t('items.cirugias'),
                            href: '/clinica/cirugias',
                            icon: Slice,
                            permission: 'cirugias.view',
                        },
                        {
                            title: t('items.hospitalizacion'),
                            href: '/clinica/hospitalizacion',
                            icon: BedDouble,
                            permission: 'hospitalizacion.view',
                        },
                    ],
                },
                {
                    title: t('groups.servicios'),
                    icon: Scissors,
                    context: 'tenant',
                    items: [
                        {
                            title: t('items.agenda_servicios'),
                            href: '/servicios/agenda',
                            icon: CalendarDays,
                            permission: 'servicios-agenda.view',
                        },
                        {
                            title: t('items.grooming'),
                            href: '/servicios/grooming',
                            icon: Sparkles,
                            permission: 'grooming.view',
                        },
                        {
                            title: t('items.hotel'),
                            href: '/servicios/hotel',
                            icon: Home,
                            permission: 'hotel.view',
                        },
                    ],
                },
                {
                    title: t('groups.inventario'),
                    icon: PawPrint,
                    context: 'tenant',
                    items: [
                        {
                            title: t('items.productos'),
                            href: '/inventario/productos',
                            icon: Package,
                            permission: 'productos.view',
                        },
                        {
                            title: t('items.categorias'),
                            href: '/inventario/categorias',
                            icon: Folder,
                            permission: 'categorias-inventario.view',
                        },
                        {
                            title: t('items.stock'),
                            href: '/inventario/stock',
                            icon: Boxes,
                            permission: 'stock.view',
                        },
                        {
                            title: t('items.movimientos'),
                            href: '/inventario/movimientos',
                            icon: ArrowRightLeft,
                            permission: 'movimientos-stock.view',
                        },
                        {
                            title: t('items.alertas'),
                            href: '/inventario/alertas',
                            icon: BellRing,
                            permission: 'alertas-stock.view',
                        },
                        {
                            title: t('items.proveedores'),
                            href: '/inventario/proveedores',
                            icon: Truck,
                            permission: 'proveedores.view',
                        },
                        {
                            title: t('items.compras'),
                            href: '/inventario/compras',
                            icon: ShoppingCart,
                            permission: 'compras.view',
                        },
                    ],
                },
                {
                    title: t('groups.caja'),
                    icon: Wallet,
                    context: 'tenant',
                    items: [
                        {
                            title: t('items.sesiones'),
                            href: '/caja/sesiones',
                            icon: DoorOpen,
                            permission: 'caja-sesiones.view',
                        },
                        {
                            title: t('items.ventas'),
                            href: '/caja/ventas',
                            icon: ReceiptText,
                            permission: 'ventas.view',
                        },
                        {
                            title: t('items.pagos'),
                            href: '/caja/pagos',
                            icon: CreditCard,
                            permission: 'pagos.view',
                        },
                        {
                            title: t('items.descuentos'),
                            href: '/caja/descuentos',
                            icon: BadgePercent,
                            permission: 'descuentos.view',
                        },
                    ],
                },
                {
                    title: t('groups.facturacion'),
                    icon: Receipt,
                    context: 'tenant',
                    items: [
                        {
                            title: t('items.documentos'),
                            href: '/facturacion/documentos',
                            icon: FileText,
                            permission: 'documentos.view',
                        },
                        {
                            title: t('items.series'),
                            href: '/facturacion/series',
                            icon: Hash,
                            permission: 'series.view',
                        },
                        {
                            title: t('items.notas_baja'),
                            href: '/facturacion/notas-baja',
                            icon: FileX,
                            permission: 'notas-baja.view',
                        },
                        {
                            title: t('items.resumenes'),
                            href: '/facturacion/resumenes',
                            icon: FileBarChart,
                            permission: 'resumenes.view',
                        },
                    ],
                },
                {
                    title: t('groups.comunicaciones'),
                    icon: MessageCircle,
                    context: 'tenant',
                    items: [
                        {
                            title: t('items.cola_saliente'),
                            href: '/comunicaciones/cola',
                            icon: Send,
                            permission: 'comunicaciones-cola.view',
                        },
                        {
                            title: t('items.historico'),
                            href: '/comunicaciones/historico',
                            icon: History,
                            permission: 'comunicaciones-historico.view',
                        },
                        {
                            title: t('items.chat_interno'),
                            href: '/comunicaciones/chat',
                            icon: MessagesSquare,
                            permission: 'comunicaciones-chat.view',
                        },
                        {
                            title: t('items.bot_ia'),
                            href: '/comunicaciones/bot-ia',
                            icon: Bot,
                            permission: 'comunicaciones-bot-ia.view',
                            requiresBotIa: true,
                            novedadWhenBotIaInactive: true,
                        },
                        {
                            title: t('items.plantillas'),
                            href: '/comunicaciones/plantillas',
                            icon: MessageSquareText,
                            permission: 'plantillas.view',
                        },
                    ],
                },
                {
                    title: t('groups.reportes'),
                    icon: Activity,
                    context: 'tenant',
                    items: [
                        {
                            title: t('items.snapshots'),
                            href: '/reportes/snapshots',
                            icon: Camera,
                            permission: 'snapshots.view',
                        },
                        {
                            title: t('items.financiero'),
                            href: '/reportes/financiero',
                            icon: LineChart,
                            permission: 'reporte-financiero.view',
                        },
                        {
                            title: t('items.ventas_productos'),
                            href: '/reportes/ventas-productos',
                            icon: Package,
                            permission: 'reporte-financiero.view',
                        },
                        {
                            title: t('items.ingresos_ventas'),
                            href: '/reportes/ingresos-ventas',
                            icon: Banknote,
                            permission: 'reporte-financiero.view',
                        },
                        {
                            title: t('items.ventas_servicios'),
                            href: '/reportes/ventas-servicios',
                            icon: FileBarChart,
                            permission: 'reporte-financiero.view',
                        },
                        {
                            title: t('items.egresos'),
                            href: '/reportes/egresos',
                            icon: Wallet,
                            permission: 'reporte-financiero.view',
                        },
                        {
                            title: t('items.top_pacientes'),
                            href: '/reportes/top-pacientes',
                            icon: Trophy,
                            permission: 'reporte-top-pacientes.view',
                        },
                    ],
                },
                {
                    title: t('groups.configuracion'),
                    icon: Cog,
                    // Visible también en panel central (roles / usuarios de plataforma).
                    // Ítems operativos de clínica llevan context: 'tenant'.
                    context: 'both',
                    items: [
                        {
                            title: t('items.ayuda'),
                            href: '/configuracion/ayuda',
                            icon: CircleHelp,
                            context: 'tenant',
                        },
                        {
                            title: t('items.general'),
                            href: '/configuracion/general',
                            icon: SlidersHorizontal,
                            permission: 'config-general.view',
                            context: 'tenant',
                        },
                        {
                            title: t('items.suscripcion'),
                            href: '/configuracion/suscripcion',
                            icon: CreditCard,
                            permission: 'config-general.view',
                            context: 'tenant',
                        },
                        {
                            title: t('items.referidos'),
                            href: '/configuracion/referidos',
                            icon: Gift,
                            permission: 'config-general.view',
                            context: 'tenant',
                        },
                        {
                            title: t('items.sedes'),
                            href: '/configuracion/sedes',
                            icon: Building2,
                            permission: 'sedes.view',
                            context: 'tenant',
                        },
                        {
                            title: t('items.roles'),
                            href: '/configuracion/roles',
                            icon: ShieldCheck,
                            permission: 'roles.view',
                        },
                        {
                            title: t('items.horarios'),
                            href: '/configuracion/horarios',
                            icon: Clock,
                            permission: 'horarios.view',
                            context: 'tenant',
                        },
                        {
                            title: t('items.bloqueos'),
                            href: '/configuracion/bloqueos',
                            icon: CalendarOff,
                            permission: 'bloqueos.view',
                            context: 'tenant',
                        },
                        {
                            title: t('items.tarifas'),
                            href: '/configuracion/tarifas',
                            icon: BarChart3,
                            permission: 'tarifas.view',
                            context: 'tenant',
                        },
                        {
                            title: t('items.usuarios'),
                            href: '/configuracion/usuarios',
                            icon: UserCog,
                            permission: 'usuarios.view',
                        },
                        {
                            title: t('items.logs'),
                            href: '/auditoria/logs',
                            icon: ScrollText,
                            permission: 'auditoria-logs.view',
                            context: 'tenant',
                        },
                    ],
                },
                /*
                 * Panel SaaS (host central). Partido en secciones cortas
                 * para no apilar 15+ ítems en un solo acordeón.
                 */
                {
                    title: t('groups.plataforma_sistema'),
                    icon: Server,
                    context: 'central',
                    items: [
                        {
                            title: t('items.operaciones'),
                            href: '/plataforma/operaciones',
                            icon: Activity,
                            permission: 'plataforma-operaciones.view',
                        },
                        {
                            title: t('items.esquema_bd'),
                            href: '/plataforma/esquema',
                            icon: Database,
                            permission: 'plataforma-operaciones.view',
                        },
                        {
                            title: t('items.sesiones_login'),
                            href: '/plataforma/sesiones-login',
                            icon: KeyRound,
                            permission: 'plataforma-operaciones.view',
                        },
                        {
                            title: t('items.uso_chat'),
                            href: '/plataforma/uso-chat',
                            icon: MessagesSquare,
                            permission: 'plataforma-operaciones.view',
                        },
                        {
                            title: t('items.whatsapp_salud'),
                            href: '/plataforma/whatsapp-salud',
                            icon: Smartphone,
                            permission: 'plataforma-operaciones.view',
                        },
                        {
                            title: t('items.apiperu'),
                            href: '/plataforma/apiperu',
                            icon: Landmark,
                            permission: 'plataforma-operaciones.view',
                        },
                        {
                            title: t('items.platform_settings'),
                            href: '/plataforma/configuracion',
                            icon: Cog,
                            permission: 'platform-settings.view',
                        },
                    ],
                },
                {
                    title: t('groups.plataforma_reportes'),
                    icon: FileBarChart,
                    context: 'central',
                    items: [
                        {
                            title: t('items.reportes_plataforma'),
                            href: '/plataforma/reportes',
                            icon: LineChart,
                            permission: 'plataforma-reportes.view',
                        },
                        {
                            title: t('items.mapa_clinicas'),
                            href: '/plataforma/reportes/mapa',
                            icon: MapPin,
                            permission: 'plataforma-reportes.view',
                        },
                        {
                            title: t('items.mapa_demos'),
                            href: '/plataforma/reportes/mapa-demos',
                            icon: FlaskConical,
                            permission: 'plataforma-reportes.view',
                        },
                    ],
                },
                {
                    title: t('groups.plataforma_clinicas'),
                    icon: Building2,
                    context: 'central',
                    items: [
                        {
                            title: t('items.tenants'),
                            href: '/plataforma/tenants',
                            icon: Store,
                            permission: 'plataforma-tenants.view',
                        },
                        {
                            title: t('items.modulos_clinicas'),
                            href: '/plataforma/modulos-clinicas',
                            icon: LayoutGrid,
                            permission: 'plataforma-tenants.view',
                        },
                        {
                            title: t('items.auditoria_soporte'),
                            href: '/plataforma/auditoria-soporte',
                            icon: Headset,
                            permission: 'plataforma-tenants.view',
                        },
                        {
                            title: t('items.chat_soporte'),
                            href: '/plataforma/chat-soporte',
                            icon: MessagesSquare,
                            permission: 'plataforma-chat-soporte.view',
                        },
                        {
                            title: t('items.auditoria_seguridad'),
                            href: '/plataforma/auditoria-seguridad',
                            icon: ShieldAlert,
                            permission: 'plataforma-tenants.view',
                        },
                    ],
                },
                {
                    title: t('groups.plataforma_cobros'),
                    icon: Wallet,
                    context: 'central',
                    items: [
                        {
                            title: t('items.planes'),
                            href: '/plataforma/planes',
                            icon: Sparkles,
                            permission: 'plataforma-planes.view',
                        },
                        {
                            title: t('items.suscripciones'),
                            href: '/plataforma/suscripciones',
                            icon: Repeat,
                            permission: 'plataforma-suscripciones.view',
                        },
                        {
                            title: t('items.uso_planes'),
                            href: '/plataforma/uso-planes',
                            icon: Gauge,
                            permission: 'plataforma-suscripciones.view',
                        },
                        {
                            title: t('items.embudo'),
                            href: '/plataforma/embudo',
                            icon: Banknote,
                            permission: 'plataforma-suscripciones.view',
                        },
                        {
                            title: t('items.avisos_renovacion'),
                            href: '/plataforma/avisos-renovacion',
                            icon: BellRing,
                            permission: 'plataforma-suscripciones.view',
                        },
                        {
                            title: t('items.cobros'),
                            href: '/plataforma/cobros',
                            icon: Wallet,
                            permission: 'plataforma-cobros.view',
                        },
                        {
                            title: t('items.platform_pagos'),
                            href: '/plataforma/pagos',
                            icon: CreditCard,
                            permission: 'plataforma-cobros.view',
                        },
                    ],
                },
                {
                    title: t('groups.plataforma_ventas'),
                    icon: Bot,
                    context: 'central',
                    items: [
                        {
                            title: t('items.salesbot_conversations'),
                            href: '/plataforma/salesbot-conversations',
                            icon: MessageCircle,
                            permission: 'salesbot-knowledge.view',
                        },
                        {
                            title: t('items.salesbot_meetings'),
                            href: '/plataforma/salesbot-meetings',
                            icon: CalendarDays,
                            permission: 'salesbot-knowledge.view',
                        },
                        {
                            title: t('items.salesbot_knowledge'),
                            href: '/plataforma/salesbot-knowledge',
                            icon: Bot,
                            permission: 'salesbot-knowledge.view',
                        },
                        {
                            title: t('items.prospectos_veterinarias'),
                            href: '/plataforma/prospectos-veterinarias',
                            icon: Radar,
                            permission: 'plataforma-prospectos.view',
                        },
                    ],
                },
                {
                    title: t('groups.plataforma_producto'),
                    icon: BookOpenCheck,
                    context: 'central',
                    items: [
                        {
                            title: t('items.in_app_assistant_knowledge'),
                            href: '/plataforma/in-app-assistant-knowledge',
                            icon: BookOpenCheck,
                            permission: 'in-app-assistant-knowledge.view',
                        },
                        {
                            title: t('items.bot_ia_announcements'),
                            href: '/plataforma/bot-ia-announcements',
                            icon: Megaphone,
                            permission: 'bot-ia-announcements.view',
                        },
                    ],
                },
            ],
        }),
        [t],
    );
}

export function AppSidebar() {
    const { t } = useTranslation('nav');
    const { singles, groups } = useNavConfig();
    const { unreadTotal } = useTenantChatUnread();
    const { unreadTotal: platformSupportUnread } = usePlatformSupportChatUnread();

    const groupsWithChatBadge = useMemo(() => {
        return groups.map((group) => ({
            ...group,
            items: group.items.map((item) => {
                if (item.href === '/comunicaciones/chat' && unreadTotal > 0) {
                    return { ...item, badgeCount: unreadTotal };
                }
                if (
                    item.href === '/plataforma/chat-soporte'
                    && platformSupportUnread > 0
                ) {
                    return { ...item, badgeCount: platformSupportUnread };
                }
                return item;
            }),
        }));
    }, [groups, unreadTotal, platformSupportUnread]);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()}>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMainCollapsible
                    label={t('section')}
                    singles={singles}
                    groups={groupsWithChatBadge}
                />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
