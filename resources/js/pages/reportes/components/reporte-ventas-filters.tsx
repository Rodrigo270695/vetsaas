import { router } from '@inertiajs/react';
import { useCallback, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { AtencionDateRangeFilter } from '@/pages/clinica/historias-clinicas/components/atencion-date-range-filter';
import type { ReporteVentasFiltros } from './types';

type Props = {
    url: string;
    filtros: Pick<ReporteVentasFiltros, 'fecha_desde' | 'fecha_hasta'>;
    search: string;
    onSearchChange: (value: string) => void;
    searchPlaceholder: string;
    extraQuery?: Record<string, string | undefined>;
    /** Namespace con claves `date_filter.*`. */
    translationNs?: string;
    /** Texto a la derecha. `null` oculta el hint. */
    hint?: string | null;
    /** Filtros extra (FilterChips, etc.). */
    children?: ReactNode;
};

export function ReporteVentasFilters({
    url,
    filtros,
    search,
    onSearchChange,
    searchPlaceholder,
    extraQuery = {},
    translationNs = 'reportes-ventas',
    hint,
    children,
}: Props) {
    const { t } = useTranslation(translationNs);
    const [loading, setLoading] = useState(false);

    const applyDates = useCallback(
        (desde: string, hasta: string) => {
            setLoading(true);
            router.get(
                url,
                {
                    fecha_desde: desde,
                    fecha_hasta: hasta,
                    ...Object.fromEntries(
                        Object.entries(extraQuery).filter(([, v]) => v != null && v !== ''),
                    ),
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                    onFinish: () => setLoading(false),
                },
            );
        },
        [extraQuery, url],
    );

    const hintText =
        hint === null
            ? null
            : (hint ??
              (translationNs === 'reportes-ventas' ? t('common.hint_catalogo') : null));

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <Input
                value={search}
                onChange={(e) => onSearchChange(e.target.value)}
                placeholder={searchPlaceholder}
                className="h-10 w-full sm:max-w-xs"
            />
            <AtencionDateRangeFilter
                desde={filtros.fecha_desde}
                hasta={filtros.fecha_hasta}
                defaultDesde={filtros.fecha_desde}
                defaultHasta={filtros.fecha_hasta}
                disabled={loading}
                translationNs={translationNs}
                triggerClassName="h-10 min-w-[12rem]"
                onApply={applyDates}
            />
            {children}
            {hintText ? (
                <p className="text-xs text-muted-foreground sm:ml-auto">{hintText}</p>
            ) : null}
        </div>
    );
}
