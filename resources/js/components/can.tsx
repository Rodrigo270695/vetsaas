import type { ReactNode } from 'react';
import { usePermission, type PermissionInput } from '@/hooks/use-permission';

export type CanProps = {
    /**
     * Permiso(s) requerido(s). Si es array, basta con tener uno (OR).
     * Para AND usa el prop `all`.
     */
    permission?: PermissionInput;
    /** Lista de permisos que se exige TODOS (AND). */
    all?: string[];
    /** Rol exacto requerido (e.g. 'superadmin'). Bypassea `permission` si coincide. */
    role?: string;
    /** Contenido que se renderiza si el usuario cumple el chequeo. */
    children: ReactNode;
    /** Fallback opcional cuando NO cumple (por defecto: nada). */
    fallback?: ReactNode;
};

/**
 * Renderiza condicionalmente sus children según permisos / rol del usuario.
 *
 * Ejemplos:
 *   <Can permission="sedes.create">
 *     <Button>+ Nueva sede</Button>
 *   </Can>
 *
 *   <Can permission={['sedes.update', 'sedes.delete']}>
 *     <RowActions />
 *   </Can>
 *
 *   <Can all={['ventas.create', 'caja-sesiones.open']}>
 *     <NuevaVentaButton />
 *   </Can>
 *
 *   <Can role="superadmin">
 *     <DebugPanel />
 *   </Can>
 */
export function Can({
    permission,
    all,
    role,
    children,
    fallback = null,
}: CanProps) {
    const { can, canAll, hasRole } = usePermission();

    const ok =
        (role !== undefined && hasRole(role)) ||
        (all !== undefined && canAll(all)) ||
        (permission !== undefined && can(permission));

    return <>{ok ? children : fallback}</>;
}
