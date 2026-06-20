import { Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { useTranslation } from 'react-i18next';
import { toastManager } from '@/lib/toast';
import { isOfflinePath } from '@/lib/offline/offline-routes';
import { visitOfflineAware } from '@/lib/offline/page-cache';

type OfflineAwareLinkProps = ComponentProps<typeof Link>;

/**
 * Enlace Inertia que, sin internet, restaura la última versión cacheada
 * de rutas offline (Caja + Clínica + Servicios + Inventario + Facturación + Comunicaciones + Reportes + Configuración + Cola sync).
 */
export function OfflineAwareLink({
    href,
    onClick,
    ...props
}: OfflineAwareLinkProps) {
    const { t } = useTranslation('offline');
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

                if (!isOfflinePath(hrefString)) {
                    event.preventDefault();
                    toastManager.warning({
                        title: t('link.offline_only_title'),
                        description: t('link.offline_only_body'),
                    });

                    return;
                }

                event.preventDefault();
                void visitOfflineAware(hrefString).then((ok) => {
                    if (!ok) {
                        toastManager.warning({
                            title: t('link.cache_miss_title'),
                            description: t('link.cache_miss_body'),
                        });
                    }
                });
            }}
        />
    );
}
