import { Head, resetLayoutProps, setLayoutProps, usePage } from '@inertiajs/react';
import { LineChart, Package, ReceiptText, TrendingUp, Wallet } from 'lucide-react';
import { useCallback, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { DashboardChartCard } from '@/components/dashboard/dashboard-chart-card';
import { DashboardFelChart } from '@/components/dashboard/dashboard-fel-chart';
import { DashboardMonthlyRevenueChart } from '@/components/dashboard/dashboard-monthly-revenue-chart';
import { DashboardRentabilidadCard } from '@/components/dashboard/dashboard-rentabilidad-card';
import { DashboardRentabilidadClinicaCard } from '@/components/dashboard/dashboard-rentabilidad-clinica-card';
import { DashboardRentabilidadGroomingCard } from '@/components/dashboard/dashboard-rentabilidad-grooming-card';
import { DashboardTopProductsChart } from '@/components/dashboard/dashboard-top-products-chart';
import { usePermission } from '@/hooks/use-permission';
import type {
    ComparacionIngresosMes,
    FelEstadoRow,
    IngresosMensualRow,
    RentabilidadClinicaResumen,
    RentabilidadGroomingResumen,
    RentabilidadResumen,
    TopProductoRow,
} from '@/pages/dashboard/types';

type Capabilities = {
    ventas: boolean;
    productos: boolean;
    grooming: boolean;
};

type FinancieroPageProps = {
    capabilities?: Capabilities | null;
    moneda?: string;
    ingresos_mensuales?: IngresosMensualRow[];
    comparacion_ingresos_mes?: ComparacionIngresosMes | null;
    top_productos_mes?: TopProductoRow[];
    rentabilidad?: RentabilidadResumen | null;
    rentabilidad_grooming?: RentabilidadGroomingResumen | null;
    rentabilidad_clinica?: RentabilidadClinicaResumen | null;
    fel_estado_mes?: FelEstadoRow[];
};

export default function ReporteFinancieroIndex() {
    const { t, i18n } = useTranslation(['dashboard', 'common']);
    const { can } = usePermission();
    const locale = i18n.language?.startsWith('en') ? 'en-US' : 'es-PE';

    const {
        capabilities: capsFromServer,
        moneda = 'PEN',
        ingresos_mensuales = [],
        comparacion_ingresos_mes = null,
        top_productos_mes = [],
        rentabilidad = null,
        rentabilidad_grooming = null,
        rentabilidad_clinica = null,
        fel_estado_mes = [],
    } = usePage().props as FinancieroPageProps;

    const capabilities: Capabilities = {
        ventas: capsFromServer != null ? Boolean(capsFromServer.ventas) : can('ventas.view'),
        productos:
            capsFromServer != null ? Boolean(capsFromServer.productos) : can('productos.view'),
        grooming:
            capsFromServer != null ? Boolean(capsFromServer.grooming) : can('grooming.view'),
    };

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: 'Reportes', href: '#' },
                { title: 'Análisis financiero', href: '/reportes/financiero' },
            ],
        });

        return () => {
            resetLayoutProps();
        };
    }, []);

    const felEstadoLabel = useCallback(
        (estado: string) => t(`estados_fel.${estado}`, { defaultValue: estado }),
        [t],
    );

    const hasVentasCharts = capabilities.ventas;
    const hasRentabilidad =
        (capabilities.productos && rentabilidad !== null) ||
        (capabilities.grooming && rentabilidad_grooming !== null) ||
        rentabilidad_clinica !== null;

    return (
        <>
            <Head title={t('sections.financial')} />
            <div className="flex flex-col gap-8 p-4 md:p-6">
                <div className="space-y-1">
                    <div className="flex items-center gap-2">
                        <Wallet className="size-5 text-emerald-600 dark:text-emerald-400" />
                        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                            {t('sections.financial')}
                        </h1>
                    </div>
                    <p className="text-sm text-muted-foreground">{t('sections.financial_hint')}</p>
                </div>

                {!hasVentasCharts && !hasRentabilidad ? (
                    <div className="rounded-xl border border-dashed border-border/70 bg-muted/20 px-6 py-12 text-center">
                        <LineChart className="mx-auto mb-3 size-8 text-muted-foreground/70" />
                        <p className="text-sm text-muted-foreground">
                            {t('reportes.financiero_empty', {
                                defaultValue:
                                    'No hay datos financieros disponibles con tus permisos o módulos activos.',
                            })}
                        </p>
                    </div>
                ) : (
                    <div className="space-y-6">
                        {(capabilities.productos && rentabilidad) ||
                        (capabilities.grooming && rentabilidad_grooming) ||
                        rentabilidad_clinica ? (
                            <div className="grid min-w-0 items-start gap-4 lg:grid-cols-2">
                                {capabilities.productos && rentabilidad && (
                                    <DashboardRentabilidadCard
                                        initial={rentabilidad}
                                        moneda={moneda}
                                        locale={locale}
                                    />
                                )}
                                {capabilities.grooming && rentabilidad_grooming && (
                                    <DashboardRentabilidadGroomingCard
                                        initial={rentabilidad_grooming}
                                        moneda={moneda}
                                        locale={locale}
                                    />
                                )}
                                {rentabilidad_clinica && (
                                    <DashboardRentabilidadClinicaCard
                                        initial={rentabilidad_clinica}
                                        moneda={moneda}
                                        locale={locale}
                                    />
                                )}
                            </div>
                        ) : null}

                        {hasVentasCharts ? (
                            <div className="grid min-w-0 gap-4 lg:grid-cols-2">
                                <DashboardChartCard
                                    title={t('charts.ingresos_mensuales')}
                                    description={t('charts.ingresos_mensuales_hint')}
                                    icon={TrendingUp}
                                    accent="brand"
                                    className="lg:col-span-2"
                                >
                                    <DashboardMonthlyRevenueChart
                                        data={ingresos_mensuales}
                                        comparacion={comparacion_ingresos_mes}
                                        moneda={moneda}
                                        locale={locale}
                                        labels={{
                                            vsPrevious: t('charts.vs_mes_anterior'),
                                            ticketAvg: t('charts.ticket_promedio'),
                                            sales: t('charts.ventas_label'),
                                            noChange: t('charts.sin_mes_anterior'),
                                        }}
                                    />
                                </DashboardChartCard>

                                <DashboardChartCard
                                    title={t('charts.top_productos')}
                                    description={t('charts.top_productos_hint')}
                                    icon={Package}
                                    accent="emerald"
                                >
                                    <DashboardTopProductsChart
                                        data={top_productos_mes}
                                        moneda={moneda}
                                        locale={locale}
                                        qtyLabel={t('charts.unidades')}
                                    />
                                </DashboardChartCard>

                                <DashboardChartCard
                                    title={t('charts.fel_mes')}
                                    description={t('charts.fel_mes_hint')}
                                    icon={ReceiptText}
                                    accent="amber"
                                >
                                    <DashboardFelChart
                                        data={fel_estado_mes}
                                        estadoLabel={felEstadoLabel}
                                    />
                                </DashboardChartCard>
                            </div>
                        ) : null}
                    </div>
                )}
            </div>
        </>
    );
}
