import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

/**
 * Tipo aceptado como ID de fila. Sedes usa UUID (string), Roles usa
 * BIGINT (number); el hook soporta ambos manteniendo type-safety.
 */
export type RowId = string | number;

export type RowSelectionState<TKey extends RowId = string> = {
    /** IDs seleccionados. Set para chequeos O(1). */
    selectedIds: Set<TKey>;
    /** Total de IDs seleccionados. */
    count: number;
    /** True si TODAS las filas visibles están seleccionadas. */
    isAllSelected: boolean;
    /** True si HAY al menos una seleccionada pero no todas. */
    isSomeSelected: boolean;
    /** Marca/desmarca una fila individual. */
    toggle: (id: TKey) => void;
    /** Marca/desmarca todas las visibles. */
    toggleAll: () => void;
    /** Limpia la selección. */
    clear: () => void;
    /** True si la fila está seleccionada. */
    isSelected: (id: TKey) => boolean;
};

export type UseRowSelectionOptions<T, TKey extends RowId = string> = {
    /** Filas visibles actualmente (de la página actual). */
    rows: T[];
    /** Extractor de ID por fila. */
    rowKey: (row: T) => TKey;
    /**
     * Si true, cuando cambian las filas (paginación/filtrado) se mantiene
     * la selección de filas que ya no están visibles. Default false.
     */
    persistAcrossPages?: boolean;
};

/**
 * Hook para gestionar selección de filas con soporte a:
 *  - Toggle individual.
 *  - Select all / deselect all sobre filas visibles.
 *  - Estado tri-state (none / some / all) para el header checkbox.
 *  - Limpieza automática al cambiar el dataset (configurable).
 *
 * Pensado para tablas paginadas server-driven: por defecto la selección
 * se limpia cuando cambian las filas (cambias de página o filtras), que
 * es el comportamiento más predecible para acciones bulk.
 *
 * Genérico en `TKey` para que cada módulo use el tipo nativo de su PK
 * (string para UUID, number para BIGINT) sin conversiones intermedias.
 */
export function useRowSelection<T, TKey extends RowId = string>({
    rows,
    rowKey,
    persistAcrossPages = false,
}: UseRowSelectionOptions<T, TKey>): RowSelectionState<TKey> {
    const [selectedIds, setSelectedIds] = useState<Set<TKey>>(
        () => new Set<TKey>(),
    );

    const visibleIds = useMemo(() => rows.map(rowKey), [rows, rowKey]);

    // Limpieza al cambiar de página si no se solicita persistencia.
    const lastRowsRef = useRef(rows);
    useEffect(() => {
        if (persistAcrossPages) {
            lastRowsRef.current = rows;

            return;
        }

        if (lastRowsRef.current !== rows) {
            setSelectedIds(new Set<TKey>());
            lastRowsRef.current = rows;
        }
    }, [rows, persistAcrossPages]);

    const isAllSelected = useMemo(() => {
        if (visibleIds.length === 0) {
            return false;
        }

        return visibleIds.every((id) => selectedIds.has(id));
    }, [visibleIds, selectedIds]);

    const isSomeSelected = useMemo(() => {
        if (visibleIds.length === 0) {
            return false;
        }
        if (isAllSelected) {
            return false;
        }

        return visibleIds.some((id) => selectedIds.has(id));
    }, [visibleIds, selectedIds, isAllSelected]);

    const toggle = useCallback((id: TKey) => {
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    }, []);

    const toggleAll = useCallback(() => {
        setSelectedIds((prev) => {
            const everyVisibleSelected = visibleIds.every((id) => prev.has(id));
            const next = new Set(prev);

            if (everyVisibleSelected) {
                visibleIds.forEach((id) => next.delete(id));
            } else {
                visibleIds.forEach((id) => next.add(id));
            }

            return next;
        });
    }, [visibleIds]);

    const clear = useCallback(() => setSelectedIds(new Set<TKey>()), []);

    const isSelected = useCallback(
        (id: TKey) => selectedIds.has(id),
        [selectedIds],
    );

    return {
        selectedIds,
        count: selectedIds.size,
        isAllSelected,
        isSomeSelected,
        toggle,
        toggleAll,
        clear,
        isSelected,
    };
}
