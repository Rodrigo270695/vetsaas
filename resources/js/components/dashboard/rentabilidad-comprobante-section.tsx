import { useTranslation } from 'react-i18next';
import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';
import type {
    RentabilidadComprobanteFiltros,
    RentabilidadPeriodo,
    RentabilidadPorComprobante,
} from '@/pages/dashboard/types';

export const DEFAULT_RENTABILIDAD_FILTROS: RentabilidadComprobanteFiltros = {
    boleta: true,
    factura: true,
    ticket: true,
};

type ComprobanteKey = keyof RentabilidadComprobanteFiltros;

const COMPROBANTE_KEYS: ComprobanteKey[] = ['boleta', 'factura', 'ticket'];

export function buildRentabilidadUrl(
    endpoint: string,
    periodo: RentabilidadPeriodo,
    filtros: RentabilidadComprobanteFiltros,
): string {
    const params = new URLSearchParams({ periodo });
    if (!filtros.boleta) {
        params.set('boleta', '0');
    }
    if (!filtros.factura) {
        params.set('factura', '0');
    }
    if (!filtros.ticket) {
        params.set('ticket', '0');
    }

    return `${endpoint}?${params.toString()}`;
}

type FiltrosProps = {
    filtros: RentabilidadComprobanteFiltros;
    loading: boolean;
    onChange: (next: RentabilidadComprobanteFiltros) => void;
};

export function RentabilidadComprobanteFilterBar({ filtros, loading, onChange }: FiltrosProps) {
    const { t } = useTranslation('dashboard');

    const toggle = (key: ComprobanteKey) => {
        const activeCount = COMPROBANTE_KEYS.filter((k) => filtros[k]).length;
        if (filtros[key] && activeCount <= 1) {
            return;
        }

        onChange({ ...filtros, [key]: !filtros[key] });
    };

    return (
        <div
            role="group"
            aria-label={t('rentabilidad.comprobantes_label')}
            className="flex flex-wrap items-center gap-x-4 gap-y-2"
        >
            {COMPROBANTE_KEYS.map((key) => (
                <label
                    key={key}
                    className={cn(
                        'inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-muted-foreground',
                        loading && 'pointer-events-none opacity-60',
                    )}
                >
                    <Checkbox
                        checked={filtros[key]}
                        disabled={loading}
                        onCheckedChange={() => toggle(key)}
                    />
                    <span>{t(`rentabilidad.comprobante.${key}`)}</span>
                </label>
            ))}
        </div>
    );
}

type PorComprobanteProps = {
    data: RentabilidadPorComprobante;
    moneda: string;
    locale: string;
    unidadesLabel: string;
};

function formatMoney(value: number, moneda: string, locale: string): string {
    try {
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency: moneda,
            minimumFractionDigits: 2,
        }).format(value);
    } catch {
        return `${moneda} ${value.toFixed(2)}`;
    }
}

export function RentabilidadPorComprobanteGrid({ data, moneda, locale, unidadesLabel }: PorComprobanteProps) {
    const { t } = useTranslation('dashboard');

    return (
        <div className="space-y-2">
            <p className="text-xs font-medium text-muted-foreground">{t('rentabilidad.por_comprobante_title')}</p>
            <div className="grid gap-2 sm:grid-cols-3">
                {COMPROBANTE_KEYS.map((key) => {
                    const slice = data[key];
                    const hasData = slice.ingresos !== 0 || slice.costo !== 0 || slice.unidades !== 0;
                    const gananciaTone =
                        slice.ganancia < 0
                            ? 'text-rose-600 dark:text-rose-400'
                            : slice.ganancia > 0
                              ? 'text-emerald-600 dark:text-emerald-400'
                              : 'text-muted-foreground';

                    return (
                        <div
                            key={key}
                            className="rounded-lg border border-border/60 bg-card/50 px-3 py-2.5"
                        >
                            <p className="text-xs font-semibold text-foreground">
                                {t(`rentabilidad.comprobante.${key}`)}
                            </p>
                            {hasData ? (
                                <div className="mt-1.5 space-y-0.5 text-xs">
                                    <p className={cn('font-semibold tabular-nums', gananciaTone)}>
                                        {formatMoney(slice.ganancia, moneda, locale)}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {slice.unidades.toLocaleString(locale)} {unidadesLabel}
                                        {slice.margen_pct !== null ? ` · ${slice.margen_pct.toFixed(1)}%` : ''}
                                    </p>
                                </div>
                            ) : (
                                <p className="mt-1.5 text-xs text-muted-foreground">—</p>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
