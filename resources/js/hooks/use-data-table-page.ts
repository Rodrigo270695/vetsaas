import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { SortState } from '@/components/data-page';
import { toastManager } from '@/lib/toast';

/**
 * Versión del schema de preferencias guardadas en localStorage.
 * Si cambia la forma del JSON persistido (p.ej. agregas un campo),
 * súbelo para invalidar lo guardado y evitar lecturas corruptas.
 */
const STORAGE_VERSION = 1;

type StoredPreferences = {
    v: number;
    per_page?: number;
    sort?: string | null;
    direction?: 'asc' | 'desc' | null;
};

function readStoredPreferences(storageKey: string): StoredPreferences | null {
    if (typeof window === 'undefined') {
        return null;
    }
    try {
        const raw = window.localStorage.getItem(storageKey);
        if (!raw) {
            return null;
        }
        const parsed = JSON.parse(raw) as StoredPreferences;
        if (parsed.v !== STORAGE_VERSION) {
            return null;
        }

        return parsed;
    } catch {
        return null;
    }
}

function writeStoredPreferences(
    storageKey: string,
    prefs: StoredPreferences,
): void {
    if (typeof window === 'undefined') {
        return;
    }
    try {
        window.localStorage.setItem(storageKey, JSON.stringify(prefs));
    } catch {
        // localStorage puede no estar disponible (modo privado/cuota llena).
    }
}

/**
 * Filtros estándar que cualquier listado paginado server-driven entiende.
 * Pensado para mapearse 1:1 con un `LengthAwarePaginator` de Laravel
 * y los query params típicos (`search`, `per_page`, `sort`, `direction`).
 */
export type BaseFilters = {
    search: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
};

export type UseDataTablePageOptions<
    TExtra extends Record<string, unknown> = Record<string, never>,
> = {
    /** URL del endpoint Inertia al que se hace `router.get`. */
    routeUrl: string;
    /** Filtros iniciales tal como llegaron del servidor. */
    initialFilters: BaseFilters & TExtra;
    /** Props para partial reload (`Inertia.only`). */
    only?: string[];
    /** Mensaje a mostrar como toast si la petición falla. */
    errorMessage?: string;
    /**
     * Si se provee, el hook persiste `per_page`, `sort` y `direction`
     * en `localStorage[storageKey]` y los restaura al primer mount si
     * no había nada en la URL.
     *
     * Ejemplo: `'vetsaas.sedes.prefs'`.
     */
    storageKey?: string;
    /**
     * Valores que se consideran "default" del servidor. Sirve para decidir
     * si lo guardado en localStorage debe disparar un fetch automático
     * (solo cuando la URL no tenía nada custom).
     */
    defaults?: {
        per_page?: number;
        sort?: string | null;
        direction?: 'asc' | 'desc' | null;
    };
};

export type UseDataTablePageResult<
    TExtra extends Record<string, unknown> = Record<string, never>,
> = {
    /** Texto del input de búsqueda (controlado). */
    search: string;
    setSearch: (value: string) => void;
    /** True mientras hay un request en vuelo. */
    isLoading: boolean;
    /** Estado de ordenamiento derivado de la query. */
    sort: SortState | null;
    setSort: (sort: SortState | null) => void;
    /** Tamaño de página actual. */
    perPage: number;
    setPerPage: (perPage: number) => void;
    /**
     * Aplica filtros adicionales propios del módulo (estado, fecha, etc.).
     * Hace merge con los filtros actuales y vuelve a página 1.
     */
    applyFilter: (overrides: Partial<TExtra>) => void;
    /**
     * Fuerza un fetch con un set arbitrario de overrides.
     * Útil para "Limpiar filtros" o sincronizar varios cambios a la vez.
     */
    fetch: (overrides?: Partial<BaseFilters & TExtra>) => void;
};

/**
 * Hook genérico para páginas de listado con tabla + filtros + paginación.
 *
 * Encapsula:
 *  - Búsqueda con debounce adaptativo (1 char = 800ms, 2+ chars = 500ms).
 *  - Cambios de orden (`sort` + `direction`).
 *  - Cambios de tamaño de página (`per_page`).
 *  - Filtros adicionales arbitrarios (`applyFilter`).
 *  - Estado de loading + manejo de errores con toast.
 *  - Reset automático a `page=1` en cualquier cambio de filtros.
 *  - Cancelación de timer en unmount.
 *
 * Diseñado para ser **server-driven**: el estado vive en la URL/server,
 * el cliente solo gestiona el input de búsqueda mientras el usuario tipea.
 *
 * Ejemplo de uso:
 * ```tsx
 * const { search, setSearch, sort, setSort, perPage, setPerPage, applyFilter, isLoading } =
 *     useDataTablePage<{ estado?: 'activa' | 'inactiva' }>({
 *         routeUrl: sedes.index().url,
 *         initialFilters: filters,
 *         only: ['sedes', 'filters', 'stats'],
 *     });
 *
 * applyFilter({ estado: 'inactiva' }); // recarga filtrando por inactivas
 * ```
 */
export function useDataTablePage<
    TExtra extends Record<string, unknown> = Record<string, never>,
>(
    options: UseDataTablePageOptions<TExtra>,
): UseDataTablePageResult<TExtra> {
    const {
        routeUrl,
        initialFilters,
        only,
        errorMessage = 'No se pudo cargar la lista.',
        storageKey,
        defaults,
    } = options;

    const [search, setSearch] = useState<string>(initialFilters.search ?? '');
    const [isLoading, setIsLoading] = useState<boolean>(false);

    const currentSort: SortState | null =
        initialFilters.sort && initialFilters.direction
            ? {
                  key: initialFilters.sort,
                  direction: initialFilters.direction,
              }
            : null;

    /**
     * Mantenemos los `initialFilters` en una ref para que `fetch` siempre
     * lea la versión más reciente sin recrearse en cada render.
     */
    const filtersRef = useRef(initialFilters);
    filtersRef.current = initialFilters;

    const fetch = useCallback(
        (overrides: Partial<BaseFilters & TExtra> = {}) => {
            setIsLoading(true);

            const current = filtersRef.current;
            const merged: Record<string, unknown> = {
                ...current,
                ...overrides,
                // Cambios de filtros siempre vuelven a página 1.
                page: 1,
            };

            // Limpiar undefined/null/'' para query string mínimo.
            const cleaned = Object.fromEntries(
                Object.entries(merged).filter(([, value]) => {
                    if (value === undefined || value === null) {
                        return false;
                    }
                    if (typeof value === 'string' && value.length === 0) {
                        return false;
                    }

                    return true;
                }),
            );

            router.get(routeUrl, cleaned, {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only,
                onError: () => toastManager.error({ title: errorMessage }),
                onFinish: () => setIsLoading(false),
            });
        },
        [routeUrl, only, errorMessage],
    );

    /**
     * Debounce de búsqueda con duración adaptativa:
     *  - 1 char  → 800ms (resultados poco específicos, esperamos más).
     *  - 2+ chars → 500ms (sweet spot para tipeo natural).
     *  - 0 chars (limpiado) → 300ms (respuesta rápida al borrar).
     */
    const isFirstRender = useRef(true);
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        // Si el texto coincide con el server-side, no disparamos.
        if (search === filtersRef.current.search) {
            return;
        }

        const trimmed = search.trim();
        let delay = 500;

        if (trimmed.length === 0) {
            delay = 300;
        } else if (trimmed.length === 1) {
            delay = 800;
        }

        const timer = window.setTimeout(() => {
            fetch({ search: trimmed } as Partial<BaseFilters & TExtra>);
        }, delay);

        return () => window.clearTimeout(timer);
    }, [search, fetch]);

    const setSort = useCallback(
        (sort: SortState | null) => {
            if (storageKey) {
                writeStoredPreferences(storageKey, {
                    v: STORAGE_VERSION,
                    per_page: filtersRef.current.per_page,
                    sort: sort?.key ?? null,
                    direction: sort?.direction ?? null,
                });
            }
            fetch({
                sort: sort?.key ?? null,
                direction: sort?.direction ?? null,
            } as Partial<BaseFilters & TExtra>);
        },
        [fetch, storageKey],
    );

    const setPerPage = useCallback(
        (perPage: number) => {
            if (storageKey) {
                writeStoredPreferences(storageKey, {
                    v: STORAGE_VERSION,
                    per_page: perPage,
                    sort: filtersRef.current.sort ?? null,
                    direction: filtersRef.current.direction ?? null,
                });
            }
            fetch({ per_page: perPage } as Partial<BaseFilters & TExtra>);
        },
        [fetch, storageKey],
    );

    const applyFilter = useCallback(
        (overrides: Partial<TExtra>) => {
            fetch(overrides as Partial<BaseFilters & TExtra>);
        },
        [fetch],
    );

    /**
     * Restauración de preferencias desde localStorage al primer mount.
     * Solo dispara fetch si la URL no traía ya valores custom (para
     * no pisarlos si el usuario llegó vía un link compartido).
     */
    const didRestoreRef = useRef(false);
    useEffect(() => {
        if (didRestoreRef.current || !storageKey) {
            return;
        }
        didRestoreRef.current = true;

        const stored = readStoredPreferences(storageKey);
        if (!stored) {
            return;
        }

        const defaultPerPage = defaults?.per_page;
        const urlHasCustom =
            (defaultPerPage !== undefined &&
                initialFilters.per_page !== defaultPerPage) ||
            initialFilters.sort !== null;

        if (urlHasCustom) {
            return;
        }

        const overrides: Partial<BaseFilters> = {};
        let shouldFetch = false;

        if (stored.per_page && stored.per_page !== initialFilters.per_page) {
            overrides.per_page = stored.per_page;
            shouldFetch = true;
        }

        if (stored.sort && stored.direction) {
            overrides.sort = stored.sort;
            overrides.direction = stored.direction;
            shouldFetch = true;
        }

        if (shouldFetch) {
            fetch(overrides as Partial<BaseFilters & TExtra>);
        }
        // Solo en el primer mount; las deps que importan son `storageKey` (estable).
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [storageKey]);

    return {
        search,
        setSearch,
        isLoading,
        sort: currentSort,
        setSort,
        perPage: initialFilters.per_page,
        setPerPage,
        applyFilter,
        fetch,
    };
}
