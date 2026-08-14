import type { InertiaLinkProps } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { toUrl } from '@/lib/utils';

export type IsCurrentUrlFn = (
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl?: string,
    startsWith?: boolean,
) => boolean;

export type IsCurrentOrParentUrlFn = (
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl?: string,
) => boolean;

/**
 * Activo exacto o como padre, pero cede si un hermano del menú es más específico
 * (evita que `/plataforma/reportes` quede activo en `/plataforma/reportes/mapa`).
 */
export type IsNavItemActiveFn = (
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    siblingHrefs: Array<NonNullable<InertiaLinkProps['href']>>,
    currentUrl?: string,
) => boolean;

export type WhenCurrentUrlFn = <TIfTrue, TIfFalse = null>(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    ifTrue: TIfTrue,
    ifFalse?: TIfFalse,
) => TIfTrue | TIfFalse;

export type UseCurrentUrlReturn = {
    currentUrl: string;
    isCurrentUrl: IsCurrentUrlFn;
    isCurrentOrParentUrl: IsCurrentOrParentUrlFn;
    isNavItemActive: IsNavItemActiveFn;
    whenCurrentUrl: WhenCurrentUrlFn;
};

export function useCurrentUrl(): UseCurrentUrlReturn {
    const page = usePage();
    const currentUrlPath = new URL(
        page.url,
        typeof window !== 'undefined'
            ? window.location.origin
            : 'http://localhost',
    ).pathname;

    const isCurrentUrl: IsCurrentUrlFn = (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
        startsWith: boolean = false,
    ) => {
        const urlToCompare = currentUrl ?? currentUrlPath;
        const urlString = toUrl(urlToCheck);

        const comparePath = (path: string): boolean =>
            startsWith ? urlToCompare.startsWith(path) : path === urlToCompare;

        if (!urlString.startsWith('http')) {
            return comparePath(urlString);
        }

        try {
            const absoluteUrl = new URL(urlString);

            return comparePath(absoluteUrl.pathname);
        } catch {
            return false;
        }
    };

    const isCurrentOrParentUrl: IsCurrentOrParentUrlFn = (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
    ) => {
        const urlToCompare = currentUrl ?? currentUrlPath;
        const path = toUrl(urlToCheck);
        const pathname = path.startsWith('http')
            ? (() => {
                  try {
                      return new URL(path).pathname;
                  } catch {
                      return path;
                  }
              })()
            : path;

        if (pathname === urlToCompare) {
            return true;
        }

        // Solo hijo real: `/reportes` no debe coincidir con `/reportes-mapa`,
        // pero sí con `/reportes/mapa`.
        return urlToCompare.startsWith(
            pathname.endsWith('/') ? pathname : `${pathname}/`,
        );
    };

    const isNavItemActive: IsNavItemActiveFn = (
        urlToCheck,
        siblingHrefs,
        currentUrl,
    ) => {
        const urlToCompare = currentUrl ?? currentUrlPath;

        if (!isCurrentOrParentUrl(urlToCheck, urlToCompare)) {
            return false;
        }

        const selfPath = toUrl(urlToCheck);
        const hasMoreSpecificSibling = siblingHrefs.some((sibling) => {
            const siblingPath = toUrl(sibling);
            if (siblingPath === selfPath) {
                return false;
            }
            if (siblingPath.length <= selfPath.length) {
                return false;
            }
            return isCurrentOrParentUrl(sibling, urlToCompare);
        });

        return !hasMoreSpecificSibling;
    };

    const whenCurrentUrl: WhenCurrentUrlFn = <TIfTrue, TIfFalse = null>(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        ifTrue: TIfTrue,
        ifFalse: TIfFalse = null as TIfFalse,
    ): TIfTrue | TIfFalse => {
        return isCurrentUrl(urlToCheck) ? ifTrue : ifFalse;
    };

    return {
        currentUrl: currentUrlPath,
        isCurrentUrl,
        isCurrentOrParentUrl,
        isNavItemActive,
        whenCurrentUrl,
    };
}
