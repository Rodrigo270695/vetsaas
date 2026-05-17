import { router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

export const DEFAULT_PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100] as const;

export type DataPaginationProps<T> = {
    /** Estructura JSON estándar de LengthAwarePaginator de Laravel. */
    meta: Paginated<T>;
    /**
     * Nombre del query param de página (Laravel `paginate(..., $pageName)`).
     * Por defecto `page`.
     */
    pageQueryKey?: string;
    /** Llaves de query que deben preservarse al cambiar de página. */
    preservedQuery?: Record<string, string | number | undefined | null>;
    /**
     * Si se provee, muestra el selector "por página" y dispara este callback
     * cuando el usuario cambia el tamaño. Si NO se provee, el selector se
     * oculta.
     */
    onPerPageChange?: (perPage: number) => void;
    /** Opciones disponibles en el selector de "por página". */
    perPageOptions?: readonly number[];
    className?: string;
};

/**
 * Construye el rango de páginas a mostrar con elipsis inteligente.
 *
 * Reglas:
 *  - Si total ≤ 7: mostrar todas (1, 2, 3, 4, 5, 6, 7).
 *  - Si current está cerca del inicio: 1, 2, 3, 4, 5, ..., last.
 *  - Si current está cerca del fin: 1, ..., last-4, last-3, last-2, last-1, last.
 *  - Si current está en el medio: 1, ..., curr-1, curr, curr+1, ..., last.
 */
function buildPageRange(
    current: number,
    total: number,
): (number | 'ellipsis-left' | 'ellipsis-right')[] {
    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }

    const pages: (number | 'ellipsis-left' | 'ellipsis-right')[] = [];

    if (current <= 4) {
        pages.push(1, 2, 3, 4, 5, 'ellipsis-right', total);
    } else if (current >= total - 3) {
        pages.push(
            1,
            'ellipsis-left',
            total - 4,
            total - 3,
            total - 2,
            total - 1,
            total,
        );
    } else {
        pages.push(
            1,
            'ellipsis-left',
            current - 1,
            current,
            current + 1,
            'ellipsis-right',
            total,
        );
    }

    return pages;
}

/**
 * Paginador "premium": iconos en vez de palabras, números con elipsis y
 * selector de tamaño de página. Pensado para datasets grandes (miles de filas).
 *
 * Navega vía Inertia con `preserveScroll` y `preserveState` para mantener
 * filtros y scroll position al cambiar de página.
 */
export function DataPagination<T>({
    meta,
    pageQueryKey = 'page',
    preservedQuery,
    onPerPageChange,
    perPageOptions = DEFAULT_PER_PAGE_OPTIONS,
    className,
}: DataPaginationProps<T>) {
    const totalPages = meta.last_page;
    const currentPage = meta.current_page;
    const canPrev = currentPage > 1;
    const canNext = currentPage < totalPages;

    const buildPageUrl = (page: number) => {
        const base = meta.path;
        const params = new URLSearchParams();

        if (preservedQuery) {
            Object.entries(preservedQuery).forEach(([key, value]) => {
                if (value !== undefined && value !== null && value !== '') {
                    params.set(key, String(value));
                }
            });
        }

        params.set(pageQueryKey, String(page));
        return `${base}?${params.toString()}`;
    };

    const navigate = (urlOrPage: string | number | null) => {
        if (urlOrPage === null) {
            return;
        }
        const url =
            typeof urlOrPage === 'number'
                ? buildPageUrl(urlOrPage)
                : urlOrPage;
        router.visit(url, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    if (meta.total === 0) {
        return null;
    }

    const pageRange = buildPageRange(currentPage, totalPages);

    return (
        <nav
            aria-label="Paginación"
            className={cn(
                'flex flex-col items-stretch justify-between gap-3 px-4 py-3 sm:flex-row sm:items-center',
                className,
            )}
        >
            {/* Resumen + selector de "por página" */}
            <div className="order-2 flex flex-col items-start gap-2 sm:order-1 sm:flex-row sm:items-center sm:gap-4">
                <p className="text-xs text-muted-foreground sm:text-sm">
                    Mostrando{' '}
                    <span className="font-semibold text-foreground tabular-nums">
                        {meta.from ?? 0}
                    </span>{' '}
                    –{' '}
                    <span className="font-semibold text-foreground tabular-nums">
                        {meta.to ?? 0}
                    </span>{' '}
                    de{' '}
                    <span className="font-semibold text-foreground tabular-nums">
                        {meta.total}
                    </span>{' '}
                    resultados
                </p>

                {onPerPageChange && (
                    <div className="flex items-center gap-2">
                        <label
                            htmlFor="per-page-select"
                            className="text-xs text-muted-foreground sm:text-sm"
                        >
                            Por página
                        </label>
                        <Select
                            value={String(meta.per_page)}
                            onValueChange={(value) =>
                                onPerPageChange(Number(value))
                            }
                        >
                            <SelectTrigger
                                id="per-page-select"
                                size="sm"
                                className="h-8 w-22 cursor-pointer"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent side="top" align="end">
                                {perPageOptions.map((opt) => (
                                    <SelectItem
                                        key={opt}
                                        value={String(opt)}
                                        className="cursor-pointer"
                                    >
                                        {opt}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                )}
            </div>

            {/* Navegación: iconos + números */}
            <div className="order-1 flex items-center justify-center gap-1 sm:order-2 sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    onClick={() => navigate(1)}
                    disabled={!canPrev}
                    className="size-8 cursor-pointer"
                    aria-label="Primera página"
                    title="Primera página"
                >
                    <ChevronsLeft className="size-4" strokeWidth={2.25} />
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    onClick={() => navigate(meta.prev_page_url)}
                    disabled={!canPrev}
                    className="size-8 cursor-pointer"
                    aria-label="Página anterior"
                    title="Página anterior"
                >
                    <ChevronLeft className="size-4" strokeWidth={2.25} />
                </Button>

                {/* Números de página (oculto en móvil para no saturar) */}
                <div className="hidden items-center gap-1 sm:flex">
                    {pageRange.map((item, idx) => {
                        if (
                            item === 'ellipsis-left' ||
                            item === 'ellipsis-right'
                        ) {
                            return (
                                <span
                                    key={`${item}-${idx}`}
                                    className="flex size-8 items-center justify-center text-xs text-muted-foreground select-none"
                                    aria-hidden
                                >
                                    …
                                </span>
                            );
                        }

                        const isActive = item === currentPage;
                        return (
                            <Button
                                key={item}
                                type="button"
                                variant={isActive ? 'default' : 'outline'}
                                size="icon"
                                onClick={() => navigate(item)}
                                disabled={isActive}
                                aria-label={`Ir a página ${item}`}
                                aria-current={isActive ? 'page' : undefined}
                                className={cn(
                                    'size-8 cursor-pointer text-xs font-semibold tabular-nums',
                                    isActive && 'pointer-events-none',
                                )}
                            >
                                {item}
                            </Button>
                        );
                    })}
                </div>

                {/* En móvil: texto compacto "X de Y" */}
                <span className="px-2 text-xs font-medium tabular-nums text-muted-foreground sm:hidden">
                    <span className="text-foreground">{currentPage}</span> /{' '}
                    <span className="text-foreground">{totalPages}</span>
                </span>

                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    onClick={() => navigate(meta.next_page_url)}
                    disabled={!canNext}
                    className="size-8 cursor-pointer"
                    aria-label="Página siguiente"
                    title="Página siguiente"
                >
                    <ChevronRight className="size-4" strokeWidth={2.25} />
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    onClick={() => navigate(totalPages)}
                    disabled={!canNext}
                    className="size-8 cursor-pointer"
                    aria-label="Última página"
                    title="Última página"
                >
                    <ChevronsRight className="size-4" strokeWidth={2.25} />
                </Button>
            </div>
        </nav>
    );
}
