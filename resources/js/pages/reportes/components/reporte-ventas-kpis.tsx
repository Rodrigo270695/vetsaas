import { Coins, Package, PiggyBank, Receipt, TrendingUp } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';
import { formatMoney, formatNumber, formatPct } from './reporte-format';
import type { ReporteVentasTotales } from './types';

type Props = {
    totales: ReporteVentasTotales;
    moneda: string;
    locale: string;
    className?: string;
};

export function ReporteVentasKpis({ totales, moneda, locale, className }: Props) {
    const { t } = useTranslation('reportes-ventas');

    const cards = [
        {
            key: 'unidades',
            label: t('common.kpis.unidades'),
            value: formatNumber(totales.unidades, locale),
            icon: Package,
            tone: 'text-sky-600 dark:text-sky-400',
        },
        {
            key: 'ventas',
            label: t('common.kpis.ventas'),
            value: formatNumber(totales.ventas, locale, 0),
            icon: Receipt,
            tone: 'text-violet-600 dark:text-violet-400',
        },
        {
            key: 'ingresos',
            label: t('common.kpis.ingresos'),
            value: formatMoney(totales.ingresos, moneda, locale),
            icon: Coins,
            tone: 'text-emerald-600 dark:text-emerald-400',
        },
        {
            key: 'costo',
            label: t('common.kpis.costo'),
            value: formatMoney(totales.costo, moneda, locale),
            icon: PiggyBank,
            tone: 'text-amber-600 dark:text-amber-400',
        },
        {
            key: 'utilidad',
            label: t('common.kpis.utilidad'),
            value:
                totales.utilidad === null
                    ? t('common.na')
                    : `${formatMoney(totales.utilidad, moneda, locale)} (${formatPct(totales.margen_pct, locale)})`,
            icon: TrendingUp,
            tone:
                totales.utilidad === null
                    ? 'text-muted-foreground'
                    : totales.utilidad < 0
                      ? 'text-rose-600 dark:text-rose-400'
                      : 'text-emerald-600 dark:text-emerald-400',
        },
    ];

    return (
        <div className={cn('grid gap-3 sm:grid-cols-2 xl:grid-cols-5', className)}>
            {cards.map((card) => (
                <div
                    key={card.key}
                    className="rounded-xl border border-border/70 bg-card px-4 py-3 shadow-sm"
                >
                    <div className="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                        <card.icon className={cn('size-3.5', card.tone)} />
                        {card.label}
                    </div>
                    <p className={cn('mt-1.5 text-lg font-semibold tracking-tight', card.tone)}>
                        {card.value}
                    </p>
                </div>
            ))}
        </div>
    );
}
