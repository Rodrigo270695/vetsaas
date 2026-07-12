import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';

/**
 * Tipo que pueden tener las claves de fila. Sedes usa UUID (string),
 * Roles usa BIGINT (number); el componente soporta ambos vía unión.
 */
export type DataTableRowId = string | number;

/**
 * Interfaz mínima que necesita DataTable para renderizar la columna de
 * selección. Cualquier hook (p.ej. `useRowSelection`) que cumpla esta
 * forma sirve, sin acoplamiento directo.
 */
export type DataTableSelection<TKey extends DataTableRowId = DataTableRowId> = {
    selectedIds: Set<TKey>;
    isAllSelected: boolean;
    isSomeSelected: boolean;
    toggle: (id: TKey) => void;
    toggleAll: () => void;
};

export type SortDirection = 'asc' | 'desc';

export type SortState = {
    /** Identificador de la columna activa (suele ser la key de DB). */
    key: string;
    direction: SortDirection;
};

export type DataTableColumn<T> = {
    /** Identificador único de la columna. */
    key: string;
    /** Encabezado mostrado en `<th>`. */
    header: ReactNode;
    /** Renderer para la celda en desktop. Recibe el row completo. */
    cell: (row: T) => ReactNode;
    /** Si true, alinea a la derecha (usado para columnas de acciones). */
    align?: 'left' | 'right' | 'center';
    /** Clases CSS extra aplicadas al <th> y <td>. */
    className?: string;
    /** Si false, no se renderiza esta columna en mobile cards (default true). */
    showInMobile?: boolean;
    /** Si true, el header se vuelve clickeable y dispara `onSortChange`. */
    sortable?: boolean;
    /**
     * Identificador que se envía al backend al ordenar.
     * Si no se provee, se usa `key`.
     */
    sortKey?: string;
};

export type DataTableProps<T> = {
    columns: DataTableColumn<T>[];
    data: T[];
    /** Función para extraer la key única de cada row. */
    rowKey: (row: T) => string | number;
    /** Mensaje cuando no hay datos. */
    emptyState?: ReactNode;
    /** Renderer custom de card móvil (sobrescribe el default). */
    mobileCard?: (row: T) => ReactNode;
    /** Slot superior dentro del mismo card (búsqueda, filtros). */
    toolbar?: ReactNode;
    /** Slot inferior dentro del mismo card (paginación). */
    footer?: ReactNode;
    /** Estado actual de ordenamiento. */
    sort?: SortState | null;
    /**
     * Callback cuando el usuario hace click en un header sortable.
     * Recibe el siguiente SortState (cicla asc → desc → vuelve al default).
     * Si la columna se desactiva (3er click), recibe `null`.
     */
    onSortChange?: (sort: SortState | null) => void;
    /**
     * Si true, se atenúa visualmente el cuerpo de la tabla y se bloquean
     * interacciones para indicar que la lista está cargando.
     */
    isLoading?: boolean;
    /**
     * Mensaje anunciado a screen readers (vía `aria-live="polite"`).
     * Suele venir como "13 sedes encontradas" o similar.
     */
    ariaLiveMessage?: string;
    /**
     * Si se provee, la tabla muestra una columna de checkbox al inicio
     * (desktop) y un checkbox en cada card (mobile). Las filas seleccionadas
     * reciben un highlight visual sutil.
     *
     * Polimórfica en TKey para que cada módulo trabaje con su PK nativa
     * (UUID en string, BIGINT en number) sin conversiones.
     */
    selection?: DataTableSelection<DataTableRowId>;
    /**
     * Si true, la tabla desktop usa `table-layout: fixed` para repartir
     * anchos de columnas de forma predecible (útil cuando hay muchas columnas
     * y texto largo que no debe invadir celdas vecinas).
     */
    tableLayoutFixed?: boolean;
    /**
     * Clases CSS extra por fila (p. ej. resaltar registros antiguos).
     */
    getRowClassName?: (row: T) => string | undefined;
    className?: string;
};

/**
 * Tabla genérica responsive con slots integrados (toolbar + footer) y
 * soporte de ordenamiento por columna.
 *
 * - **Desktop (md+):** tabla clásica con encabezado sticky-friendly.
 *   Las columnas marcadas `sortable` muestran iconos ↑ ↓ ↕ y son clickeables.
 * - **Mobile:** cada row se renderiza como una "card" apilada.
 *
 * El toolbar y footer se renderizan dentro del mismo card para presentar
 * filtros y paginación visualmente integrados con los datos.
 */
export function DataTable<T>({
    columns,
    data,
    rowKey,
    emptyState,
    mobileCard,
    toolbar,
    footer,
    sort,
    onSortChange,
    isLoading = false,
    ariaLiveMessage,
    selection,
    getRowClassName,
    className,
    tableLayoutFixed = false,
}: DataTableProps<T>) {
    const isEmpty = data.length === 0;
    const mobileColumns = columns.filter(
        (col) => col.showInMobile !== false,
    );
    const hasSelection = Boolean(selection);
    const totalColspan = columns.length + (hasSelection ? 1 : 0);

    /**
     * Cicla: asc → desc → null (vuelve al orden default del servidor).
     */
    const handleHeaderClick = (col: DataTableColumn<T>) => {
        if (!col.sortable || !onSortChange) {
            return;
        }

        const key = col.sortKey ?? col.key;
        const isActive = sort?.key === key;

        if (!isActive) {
            onSortChange({ key, direction: 'asc' });

            return;
        }

        if (sort?.direction === 'asc') {
            onSortChange({ key, direction: 'desc' });

            return;
        }

        onSortChange(null);
    };

    const renderHeader = (col: DataTableColumn<T>) => {
        const key = col.sortKey ?? col.key;
        const isSortable = !!col.sortable && !!onSortChange;
        const isActive = isSortable && sort?.key === key;
        const direction: SortDirection | null = isActive ? sort!.direction : null;

        if (!isSortable) {
            return col.header;
        }

        return (
            <button
                type="button"
                onClick={() => handleHeaderClick(col)}
                className={cn(
                    'inline-flex cursor-pointer items-center gap-1.5 rounded-md transition-colors',
                    'text-inherit hover:text-brand-950 dark:hover:text-brand-50',
                    isActive && 'text-brand-950 dark:text-brand-50',
                    col.align === 'right' && 'flex-row-reverse',
                )}
                aria-label={`Ordenar por ${
                    typeof col.header === 'string' ? col.header : key
                }`}
            >
                <span>{col.header}</span>
                {!isActive && (
                    <ArrowUpDown
                        className="size-3 opacity-50"
                        strokeWidth={2.5}
                        aria-hidden
                    />
                )}
                {isActive && direction === 'asc' && (
                    <ArrowUp
                        className="size-3 text-brand-700 dark:text-brand-300"
                        strokeWidth={2.5}
                        aria-hidden
                    />
                )}
                {isActive && direction === 'desc' && (
                    <ArrowDown
                        className="size-3 text-brand-700 dark:text-brand-300"
                        strokeWidth={2.5}
                        aria-hidden
                    />
                )}
            </button>
        );
    };

    return (
        <div
            className={cn(
                'overflow-hidden rounded-lg border border-border/60 bg-card shadow-xs',
                className,
            )}
        >
            {ariaLiveMessage && (
                <span aria-live="polite" className="sr-only">
                    {ariaLiveMessage}
                </span>
            )}

            {toolbar && (
                <div className="border-b border-border/60 bg-muted/20 px-4 py-3">
                    {toolbar}
                </div>
            )}

            <div
                className={cn(
                    'transition-opacity duration-200',
                    isLoading && 'pointer-events-none opacity-60',
                )}
            >
            <div className="hidden md:block">
                <div className="overflow-x-auto">
                    <table
                        className={cn(
                            'w-full min-w-0 border-collapse text-sm',
                            tableLayoutFixed && 'table-fixed',
                        )}
                    >
                        <thead>
                            <tr>
                                {hasSelection && selection && (
                                    <th
                                        scope="col"
                                        className="w-10 border-b border-brand-200/60 bg-brand-50/75 px-3 py-2 dark:border-brand-800/40 dark:bg-brand-950/40"
                                    >
                                        <Checkbox
                                            checked={
                                                selection.isAllSelected
                                                    ? true
                                                    : selection.isSomeSelected
                                                      ? 'indeterminate'
                                                      : false
                                            }
                                            onCheckedChange={() =>
                                                selection.toggleAll()
                                            }
                                            aria-label="Seleccionar todas las filas"
                                        />
                                    </th>
                                )}
                                {columns.map((col) => (
                                    <th
                                        key={col.key}
                                        className={cn(
                                            'border-b border-brand-200/60 bg-brand-50/75 px-4 py-2 text-left text-xs font-semibold tracking-wide text-brand-800/90 dark:border-brand-800/40 dark:bg-brand-950/40 dark:text-brand-100/90',
                                            col.align === 'right' && 'text-right',
                                            col.align === 'center' && 'text-center',
                                            col.className,
                                        )}
                                    >
                                        {renderHeader(col)}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {isEmpty ? (
                                <tr>
                                    <td
                                        colSpan={totalColspan}
                                        className="px-4 py-12"
                                    >
                                        {emptyState ?? (
                                            <div className="text-center text-sm text-muted-foreground">
                                                Sin registros para mostrar.
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ) : (
                                data.map((row) => {
                                    const rowId = rowKey(row);
                                    const reactKey = String(rowId);
                                    const isRowSelected =
                                        selection?.selectedIds.has(rowId) ?? false;
                                    const rowClassExtra = getRowClassName?.(row);

                                    return (
                                        <tr
                                            key={reactKey}
                                            data-selected={isRowSelected}
                                            className={cn(
                                                'border-b border-border/40 transition-colors last:border-b-0 hover:bg-muted/30',
                                                isRowSelected &&
                                                    'bg-primary/5 hover:bg-primary/10',
                                                rowClassExtra,
                                            )}
                                        >
                                            {hasSelection && selection && (
                                                <td className="w-10 px-3 py-2 align-middle">
                                                    <Checkbox
                                                        checked={isRowSelected}
                                                        onCheckedChange={() =>
                                                            selection.toggle(
                                                                rowId,
                                                            )
                                                        }
                                                        aria-label="Seleccionar fila"
                                                    />
                                                </td>
                                            )}
                                            {columns.map((col) => (
                                                <td
                                                    key={col.key}
                                                    className={cn(
                                                        'min-w-0 px-4 py-2 align-middle text-sm text-foreground',
                                                        col.align === 'right' &&
                                                            'text-right',
                                                        col.align === 'center' &&
                                                            'text-center',
                                                        col.className,
                                                    )}
                                                >
                                                    {col.cell(row)}
                                                </td>
                                            ))}
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="divide-y divide-border/60 md:hidden">
                {isEmpty ? (
                    <div className="px-4 py-10">
                        {emptyState ?? (
                            <div className="text-center text-sm text-muted-foreground">
                                Sin registros para mostrar.
                            </div>
                        )}
                    </div>
                ) : (
                    data.map((row) => {
                        const rowId = rowKey(row);
                        const reactKey = String(rowId);
                        const isRowSelected =
                            selection?.selectedIds.has(rowId) ?? false;
                        const rowClassExtra = getRowClassName?.(row);

                        return (
                            <div
                                key={reactKey}
                                data-selected={isRowSelected}
                                className={cn(
                                    'flex items-start gap-3 px-4 py-2.5 transition-colors hover:bg-muted/30',
                                    isRowSelected && 'bg-primary/5',
                                    rowClassExtra,
                                )}
                            >
                                {hasSelection && selection && (
                                    <Checkbox
                                        checked={isRowSelected}
                                        onCheckedChange={() =>
                                            selection.toggle(rowId)
                                        }
                                        aria-label="Seleccionar fila"
                                        className="mt-1 shrink-0"
                                    />
                                )}
                                <div className="min-w-0 flex-1">
                                    {mobileCard ? (
                                        mobileCard(row)
                                    ) : (
                                        <dl className="space-y-1.5">
                                            {mobileColumns.map((col) => (
                                                <div
                                                    key={col.key}
                                                    className="flex items-center justify-between gap-3"
                                                >
                                                    <dt className="text-xs font-medium tracking-wide text-muted-foreground">
                                                        {col.header}
                                                    </dt>
                                                    <dd className="min-w-0 text-right text-sm text-foreground">
                                                        {col.cell(row)}
                                                    </dd>
                                                </div>
                                            ))}
                                        </dl>
                                    )}
                                </div>
                            </div>
                        );
                    })
                )}
            </div>

            </div>

            {footer && (
                <div className="border-t border-border/60 bg-muted/20">
                    {footer}
                </div>
            )}
        </div>
    );
}
