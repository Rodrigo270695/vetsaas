import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    /**
     * Si se omite, el item se renderiza como texto plano (no clickeable).
     * Útil para niveles intermedios que no tienen página propia, como
     * "Configuración" cuando es solo una sección del sidebar.
     */
    href?: NonNullable<InertiaLinkProps['href']>;
};

/**
 * Contexto de hosting en el que un item del sidebar es relevante.
 *
 * - `central`: solo aparece en el dominio central (panel SaaS sin tenant).
 *   Pensado para módulos de Plataforma (`/plataforma/*`).
 * - `tenant`: solo aparece dentro de un subdominio de clínica
 *   (`<slug>.vetsaas.test`). Para módulos operativos cuyo backend depende
 *   del schema del tenant (pacientes, citas, configuración, etc.).
 * - `both` (default): siempre disponible cuando el usuario tenga permisos
 *   (e.g. Dashboard, perfil de usuario).
 *
 * Se usa para evitar que un superadmin vea links a páginas que en su host
 * actual responderían 404 (porque la tabla no existe en `public`).
 */
export type NavContext = 'central' | 'tenant' | 'both';

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /**
     * Permiso requerido para que el item aparezca en el menú.
     * Si es array → basta con tener uno (OR). El rol `superadmin` lo ignora.
     */
    permission?: string | string[];
    /**
     * Host(s) donde tiene sentido mostrar este item. Default `'both'`.
     * Ver {@link NavContext}.
     */
    context?: NavContext;
    /**
     * Si es `false`, el item no se muestra en el sidebar (módulo aún no implementado).
     * También puedes centralizar la ruta en `config/nav-implemented.ts`.
     */
    /**
     * Solo visible si el add-on IA está activo en la suscripción del tenant.
     */
    requiresBotIa?: boolean;

    /** Clave de módulo tenant; si se omite se infiere desde `href`. */
    moduleKey?: string;
};

/**
 * Grupo de navegación con items hijos desplegables.
 * Usado en el sidebar principal para agrupar módulos del negocio.
 *
 * El grupo aparece si AL MENOS UNO de sus items pasa el chequeo de permisos
 * y contexto. Si tú quieres además requerir un permiso a nivel grupo, usa
 * `permission`; idem para forzar un único contexto con `context`.
 */
export type NavGroup = {
    title: string;
    icon?: LucideIcon;
    defaultOpen?: boolean;
    permission?: string | string[];
    /** Contexto del grupo entero (cascada al filtrado). Default `'both'`. */
    context?: NavContext;
    items: NavItem[];
};
