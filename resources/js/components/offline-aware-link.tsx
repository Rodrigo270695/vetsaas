import { Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { toastManager } from '@/lib/toast';
import { isCajaOfflinePath } from '@/lib/offline/caja-routes';
import { visitOfflineAware } from '@/lib/offline/page-cache';

type OfflineAwareLinkProps = ComponentProps<typeof Link>;

/**
 * Enlace Inertia que, sin internet, restaura la última versión cacheada
 * de rutas de Caja (Fase 2 offline).
 */
export function OfflineAwareLink({
    href,
    onClick,
    ...props
}: OfflineAwareLinkProps) {
    const hrefString = typeof href === 'string' ? href : href.url;

    return (
        <Link
            {...props}
            href={href}
            onClick={(event) => {
                onClick?.(event);

                if (event.defaultPrevented || navigator.onLine) {
                    return;
                }

                if (!isCajaOfflinePath(hrefString)) {
                    event.preventDefault();
                    toastManager.warning({
                        title: 'Sin conexión',
                        description:
                            'Esta sección requiere internet. Las rutas de Caja siguen disponibles offline.',
                    });

                    return;
                }

                event.preventDefault();
                void visitOfflineAware(hrefString).then((ok) => {
                    if (!ok) {
                        toastManager.warning({
                            title: 'Sin conexión',
                            description:
                                'Abre esta pantalla al menos una vez con internet para usarla offline.',
                        });
                    }
                });
            }}
        />
    );
}
