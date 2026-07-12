import { Link } from '@inertiajs/react';
import {
    Activity,
    ArrowRightLeft,
    BadgePercent,
    BarChart3,
    BedDouble,
    BellRing,
    Bot,
    Boxes,
    Building2,
    CalendarDays,
    CalendarOff,
    Camera,
    Clock,
    Cog,
    CreditCard,
    DoorOpen,
    Dog,
    FileBarChart,
    FileText,
    FileX,
    FlaskConical,
    Folder,
    Hash,
    CircleHelp,
    Headset,
    History,
    Home,
    LayoutGrid,
    LineChart,
    Megaphone,
    MessageCircle,
    MessageSquareText,
    Package,
    PawPrint,
    Pill,
    Receipt,
    ReceiptText,
    Repeat,
    Scissors,
    ScrollText,
    Send,
    Server,
    ShieldCheck,
    ShoppingCart,
    Slice,
    SlidersHorizontal,
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
                    context: 'tenant',
                    items: [
                        {
                            title: t('items.ayuda'),
                            href: '/configuracion/ayuda',
                            icon: CircleHelp,
                        },
                        {
                            title: t('items.general'),
                            href: '/configuracion/general',
                            icon: SlidersHorizontal,
                            permission: 'config-general.view',
                        },
                        {
                            title: t('items.suscripcion'),
                            href: '/configuracion/suscripcion',
                            icon: CreditCard,
                            permission: 'config-general.view',
                        },
                        {
                            title: t('items.sedes'),
                            href: '/configuracion/sedes',
                            icon: Building2,
                            permission: 'sedes.view',
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
                        },
                        {
                            title: t('items.bloqueos'),
                            href: '/configuracion/bloqueos',
                            icon: CalendarOff,
                            permission: 'bloqueos.view',
                        },
                        {
                            title: t('items.tarifas'),
                            href: '/configuracion/tarifas',
                            icon: BarChart3,
                            permission: 'tarifas.view',
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
                        },
                    ],
                },
                /*
                 * Plataforma SaaS — visible solo para superadmin (y, a
                 * futuro, roles de soporte interno) y SOLO en el host
                 * central. Sus rutas (`/plataforma/*`) viven en el schema
                 * `public` y no se exponen desde subdominios de tenant.
                 */
                {
                    title: t('groups.plataforma'),
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
                            title: t('items.tenants'),
                            href: '/plataforma/tenants',
                            icon: Store,
                            permission: 'plataforma-tenants.view',
                        },
                        {
                            title: t('items.auditoria_soporte'),
                            href: '/plataforma/auditoria-soporte',
                            icon: Headset,
                            permission: 'plataforma-tenants.view',
                        },
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
                            // Conversaciones del bot: pausa/reactiva por lead
                            // desde el navegador (funciona en celular).
                            title: t('items.salesbot_conversations'),
                            href: '/plataforma/salesbot-conversations',
                            icon: MessageCircle,
                            permission: 'salesbot-knowledge.view',
                        },
                        {
                            title: t('items.salesbot_knowledge'),
                            href: '/plataforma/salesbot-knowledge',
                            icon: Bot,
                            permission: 'salesbot-knowledge.view',
                        },
                        {
                            title: t('items.bot_ia_announcements'),
                            href: '/plataforma/bot-ia-announcements',
                            icon: Megaphone,
                            permission: 'bot-ia-announcements.view',
                        },
                        {
                            // Configuración global: credenciales de Twilio
                            // y Brevo compartidas por todas las clínicas.
                            // Solo superadmin tiene este permiso.
                            title: t('items.platform_settings'),
                            href: '/plataforma/configuracion',
                            icon: Cog,
                            permission: 'platform-settings.view',
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

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
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
                    groups={groups}
                />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
