import { Head } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    BedDouble,
    CalendarDays,
    Dog,
    FileText,
    Home,
    Package,
    PieChart,
    ReceiptText,
    Scissors,
    Stethoscope,
    Syringe,
    TrendingUp,
    Users,
    Wallet,
} from 'lucide-react';
import { useCallback, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { DashboardAppointmentsChart } from '@/components/dashboard/dashboard-appointments-chart';
import { DashboardAppointmentsList } from '@/components/dashboard/dashboard-appointments-list';
import { DashboardCajaStatus } from '@/components/dashboard/dashboard-caja-status';
import { DashboardChartCard } from '@/components/dashboard/dashboard-chart-card';
import { DashboardConsultasChart } from '@/components/dashboard/dashboard-consultas-chart';
import { DashboardHero } from '@/components/dashboard/dashboard-hero';
import {
    DashboardKpiGrid,
    type DashboardKpiItem,
} from '@/components/dashboard/dashboard-kpi-grid';
import { DashboardPaymentChart } from '@/components/dashboard/dashboard-payment-chart';
import {
    DashboardQuickActions,
    type QuickActionItem,
} from '@/components/dashboard/dashboard-quick-actions';
import { DashboardClientesMensualesChart } from '@/components/dashboard/dashboard-clientes-mensuales-chart';
import { DashboardFelChart } from '@/components/dashboard/dashboard-fel-chart';
import { DashboardMonthlyRevenueChart } from '@/components/dashboard/dashboard-monthly-revenue-chart';
import { DashboardRentabilidadCard } from '@/components/dashboard/dashboard-rentabilidad-card';
import { DashboardSalesChart } from '@/components/dashboard/dashboard-sales-chart';
import { DashboardSectionTitle } from '@/components/dashboard/dashboard-section-title';
import { DashboardTopProductsChart } from '@/components/dashboard/dashboard-top-products-chart';
import { DashboardVacunacionesChart } from '@/components/dashboard/dashboard-vacunaciones-chart';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type {
    CitasPorEstadoRow,
    ComparacionIngresosMes,
    ConsultasPorDiaRow,
    DashboardCapabilities,
    DashboardKpis,
    FelEstadoRow,
    IngresosMensualRow,
    NuevosClientesMensualRow,
    ProximaCitaRow,
    RentabilidadResumen,
    TopProductoRow,
    VacunacionesPorDiaRow,
    VentasPorDiaRow,
    VentasPorMetodoRow,
} from '@/pages/dashboard/types';
import { es, enUS } from 'date-fns/locale';

type Props = {
    clinic_label: string;
    capabilities: DashboardCapabilities;
    moneda: string;
    kpis: DashboardKpis;
    ventas_por_dia: VentasPorDiaRow[];
    consultas_por_dia: ConsultasPorDiaRow[];
    ventas_por_metodo: VentasPorMetodoRow[];
    citas_por_estado: CitasPorEstadoRow[];
    proximas_citas: ProximaCitaRow[];
    ingresos_mensuales: IngresosMensualRow[];
    comparacion_ingresos_mes: ComparacionIngresosMes | null;
    top_productos_mes: TopProductoRow[];
    rentabilidad: RentabilidadResumen | null;
    fel_estado_mes: FelEstadoRow[];
    vacunaciones_por_dia: VacunacionesPorDiaRow[];
    nuevos_clientes_mensuales: NuevosClientesMensualRow[];
    citas_asistencia_mes: CitasPorEstadoRow[];
};

function formatMoney(value: string | number, moneda: string, locale: string): string {
    const n = typeof value === 'string' ? Number(value) : value;

    if (Number.isNaN(n)) {
        return String(value);
    }

    try {
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency: moneda,
            minimumFractionDigits: 2,
        }).format(n);
    } catch {
        return `${moneda} ${n.toFixed(2)}`;
    }
}

export default function DashboardIndex({
    clinic_label,
    capabilities,
    moneda,
    kpis,
    ventas_por_dia,
    consultas_por_dia,
    ventas_por_metodo,
    citas_por_estado,
    proximas_citas,
    ingresos_mensuales,
    comparacion_ingresos_mes,
    top_productos_mes,
    rentabilidad,
    fel_estado_mes,
    vacunaciones_por_dia,
    nuevos_clientes_mensuales,
    citas_asistencia_mes,
}: Props) {
    const { t, i18n } = useTranslation(['dashboard', 'common']);
    const { can } = usePermission();
    const locale = i18n.language?.startsWith('en') ? 'en-US' : 'es-PE';
    const dateFnsLocale = i18n.language?.startsWith('en') ? enUS : es;

    const estadoLabel = useCallback(
        (estado: string) => t(`estados_cita.${estado}`, { defaultValue: estado }),
        [t],
    );

    const metodoLabel = useCallback(
        (metodo: string) => t(`metodos_pago.${metodo}`, { defaultValue: metodo }),
        [t],
    );

    const felEstadoLabel = useCallback(
        (estado: string) => t(`estados_fel.${estado}`, { defaultValue: estado }),
        [t],
    );

    const { clinicalKpis, operationsKpis, inventoryKpis } = useMemo(() => {
        const clinical: DashboardKpiItem[] = [];
        const operations: DashboardKpiItem[] = [];
        const inventory: DashboardKpiItem[] = [];

        const push = (group: DashboardKpiItem[], item: DashboardKpiItem): void => {
            group.push(item);
        };

        if (capabilities.citas) {
            push(clinical, {
                key: 'citas_hoy',
                label: t('kpis.citas_hoy'),
                value: kpis.citas_hoy,
                icon: CalendarDays,
                accent: 'brand',
            });
            push(clinical, {
                key: 'citas_pendientes',
                label: t('kpis.citas_pendientes'),
                value: kpis.citas_pendientes_hoy,
                icon: CalendarDays,
                accent: 'sky',
            });
        }

        if (capabilities.consultas) {
            push(clinical, {
                key: 'consultas_hoy',
                label: t('kpis.consultas_hoy'),
                value: kpis.consultas_hoy,
                icon: Stethoscope,
                accent: 'brand',
            });
            push(clinical, {
                key: 'consultas_abiertas',
                label: t('kpis.consultas_abiertas'),
                value: kpis.consultas_abiertas,
                icon: FileText,
                accent: 'amber',
                highlight: kpis.consultas_abiertas > 0,
            });
        }

        if (capabilities.vacunaciones) {
            push(clinical, {
                key: 'vacunaciones_mes',
                label: t('kpis.vacunaciones_mes'),
                value: kpis.vacunaciones_mes,
                icon: Syringe,
                accent: 'violet',
            });
        }

        if (capabilities.grooming) {
            push(clinical, {
                key: 'grooming_hoy',
                label: t('kpis.grooming_hoy'),
                value: kpis.grooming_hoy,
                icon: Scissors,
                accent: 'rose',
            });
        }

        if (capabilities.hotel) {
            push(clinical, {
                key: 'hotel_estancia',
                label: t('kpis.hotel_estancia'),
                value: kpis.hotel_en_estancia,
                icon: Home,
                accent: 'sky',
            });
        }

        if (capabilities.hospitalizacion) {
            push(clinical, {
                key: 'internamientos',
                label: t('kpis.internamientos'),
                value: kpis.internamientos_activos,
                icon: BedDouble,
                accent: 'amber',
                highlight: kpis.internamientos_activos > 0,
            });
        }

        if (capabilities.ventas) {
            push(operations, {
                key: 'ventas_hoy',
                label: t('kpis.ventas_hoy'),
                value: kpis.ventas_hoy_count,
                icon: ReceiptText,
                accent: 'emerald',
            });
            push(operations, {
                key: 'ventas_monto',
                label: t('kpis.ventas_monto'),
                value: formatMoney(kpis.ventas_hoy_total, moneda, locale),
                icon: Wallet,
                accent: 'emerald',
            });

            if (kpis.fel_pendientes > 0) {
                push(operations, {
                    key: 'fel_pendientes',
                    label: t('kpis.fel_pendientes'),
                    value: kpis.fel_pendientes,
                    icon: ReceiptText,
                    accent: 'amber',
                    highlight: true,
                });
            }
        }

        if (capabilities.pacientes) {
            push(inventory, {
                key: 'pacientes_mes',
                label: t('kpis.pacientes_mes'),
                value: kpis.pacientes_nuevos_mes,
                icon: Dog,
                accent: 'brand',
            });
        }

        if (capabilities.propietarios) {
            push(inventory, {
                key: 'propietarios_mes',
                label: t('kpis.propietarios_mes'),
                value: kpis.propietarios_nuevos_mes,
                icon: Users,
                accent: 'sky',
            });
        }

        if (capabilities.productos) {
            push(inventory, {
                key: 'productos_activos',
                label: t('kpis.productos_activos'),
                value: kpis.productos_activos,
                icon: Package,
                accent: 'slate',
            });
        }

        if (capabilities.alertas_stock) {
            push(inventory, {
                key: 'alertas_stock',
                label: t('kpis.alertas_stock'),
                value: kpis.alertas_stock,
                icon: AlertTriangle,
                accent: 'amber',
                highlight: kpis.alertas_stock > 0,
            });
        }

        return {
            clinicalKpis: clinical,
            operationsKpis: operations,
            inventoryKpis: inventory,
        };
    }, [capabilities, kpis, locale, moneda, t]);

    const quickActions = useMemo((): QuickActionItem[] => {
        const items: QuickActionItem[] = [];

        if (can('citas.create')) {
            items.push({
                key: 'cita',
                label: t('quick_actions.nueva_cita'),
                href: '/clinica/citas',
                icon: CalendarDays,
                accent: 'brand',
            });
        }
        if (can('ventas.create')) {
            items.push({
                key: 'venta',
                label: t('quick_actions.nueva_venta'),
                href: '/caja/ventas/nuevo',
                icon: ReceiptText,
                accent: 'emerald',
            });
        }
        if (can('pacientes.view')) {
            items.push({
                key: 'pacientes',
                label: t('quick_actions.pacientes'),
                href: '/clinica/pacientes',
                icon: Dog,
                accent: 'sky',
            });
        }
        if (can('historias-clinicas.view')) {
            items.push({
                key: 'historias',
                label: t('quick_actions.historias'),
                href: '/clinica/historias-clinicas',
                icon: FileText,
                accent: 'violet',
            });
        }
        if (can('alertas-stock.view')) {
            items.push({
                key: 'alertas',
                label: t('quick_actions.alertas'),
                href: '/inventario/alertas',
                icon: AlertTriangle,
                accent: 'amber',
            });
        }

        return items;
    }, [can, t]);

    const hasFinancialCharts = capabilities.ventas;
    const hasWeeklyCharts =
        capabilities.ventas || capabilities.consultas || capabilities.citas;
    const hasGrowthCharts =
        capabilities.vacunaciones ||
        capabilities.pacientes ||
        capabilities.propietarios ||
        (capabilities.citas && citas_asistencia_mes.length > 0);

    return (
        <>
            <Head title={t('title')} />

            <div className="flex min-w-0 flex-col gap-8 p-4 md:p-6">
                <DashboardHero clinicLabel={clinic_label} />

                {capabilities.caja_sesiones && (
                    <DashboardCajaStatus abierta={kpis.caja_abierta} />
                )}

                {clinicalKpis.length > 0 && (
                    <section className="space-y-4">
                        <DashboardSectionTitle
                            title={t('sections.clinical')}
                            description={t('sections.clinical_hint')}
                            icon={Stethoscope}
                            accent="brand"
                        />
                        <DashboardKpiGrid items={clinicalKpis} />
                    </section>
                )}

                {operationsKpis.length > 0 && (
                    <section className="space-y-4">
                        <DashboardSectionTitle
                            title={t('sections.operations')}
                            description={t('sections.operations_hint')}
                            icon={Wallet}
                            accent="emerald"
                        />
                        <DashboardKpiGrid items={operationsKpis} />
                    </section>
                )}

                {inventoryKpis.length > 0 && (
                    <section className="space-y-4">
                        <DashboardSectionTitle
                            title={t('sections.inventory')}
                            description={t('sections.inventory_hint')}
                            icon={Package}
                            accent="amber"
                        />
                        <DashboardKpiGrid items={inventoryKpis} />
                    </section>
                )}

                {hasFinancialCharts && (
                    <section className="space-y-4">
                        <DashboardSectionTitle
                            title={t('sections.financial')}
                            description={t('sections.financial_hint')}
                            icon={Wallet}
                            accent="emerald"
                        />
                        {capabilities.productos && rentabilidad && (
                            <DashboardRentabilidadCard
                                initial={rentabilidad}
                                moneda={moneda}
                                locale={locale}
                            />
                        )}
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
                    </section>
                )}

                {hasWeeklyCharts && (
                    <section className="space-y-4">
                        <DashboardSectionTitle
                            title={t('sections.analytics')}
                            description={t('sections.analytics_hint')}
                            icon={Activity}
                            accent="violet"
                        />
                        <div className="grid min-w-0 gap-4 lg:grid-cols-2">
                            {capabilities.ventas && (
                                <DashboardChartCard
                                    title={t('charts.ventas_7d')}
                                    description={t('charts.ventas_7d_hint')}
                                    icon={TrendingUp}
                                    accent="brand"
                                >
                                    <DashboardSalesChart
                                        data={ventas_por_dia}
                                        moneda={moneda}
                                        locale={locale}
                                    />
                                </DashboardChartCard>
                            )}

                            {capabilities.consultas && (
                                <DashboardChartCard
                                    title={t('charts.consultas_7d')}
                                    description={t('charts.consultas_7d_hint')}
                                    icon={Stethoscope}
                                    accent="sky"
                                >
                                    <DashboardConsultasChart data={consultas_por_dia} />
                                </DashboardChartCard>
                            )}

                            {capabilities.citas && (
                                <DashboardChartCard
                                    title={t('charts.citas_semana')}
                                    description={t('charts.citas_semana_hint')}
                                    icon={CalendarDays}
                                    accent="violet"
                                >
                                    <DashboardAppointmentsChart
                                        data={citas_por_estado}
                                        estadoLabel={estadoLabel}
                                    />
                                </DashboardChartCard>
                            )}

                            {capabilities.ventas && (
                                <DashboardChartCard
                                    title={t('charts.ventas_metodo')}
                                    description={t('charts.ventas_metodo_hint')}
                                    icon={PieChart}
                                    accent="emerald"
                                >
                                    <DashboardPaymentChart
                                        data={ventas_por_metodo}
                                        metodoLabel={metodoLabel}
                                        moneda={moneda}
                                        locale={locale}
                                    />
                                </DashboardChartCard>
                            )}
                        </div>
                    </section>
                )}

                {hasGrowthCharts && (
                    <section className="space-y-4">
                        <DashboardSectionTitle
                            title={t('sections.growth')}
                            description={t('sections.growth_hint')}
                            icon={Users}
                            accent="sky"
                        />
                        <div className="grid min-w-0 gap-4 lg:grid-cols-2">
                            {capabilities.vacunaciones && (
                                <DashboardChartCard
                                    title={t('charts.vacunaciones_7d')}
                                    description={t('charts.vacunaciones_7d_hint')}
                                    icon={Syringe}
                                    accent="violet"
                                >
                                    <DashboardVacunacionesChart data={vacunaciones_por_dia} />
                                </DashboardChartCard>
                            )}

                            {(capabilities.pacientes || capabilities.propietarios) && (
                                <DashboardChartCard
                                    title={t('charts.clientes_mensuales')}
                                    description={t('charts.clientes_mensuales_hint')}
                                    icon={Users}
                                    accent="sky"
                                >
                                    <DashboardClientesMensualesChart
                                        data={nuevos_clientes_mensuales}
                                        showPacientes={capabilities.pacientes}
                                        showPropietarios={capabilities.propietarios}
                                        labels={{
                                            pacientes: t('charts.pacientes_serie'),
                                            propietarios: t('charts.propietarios_serie'),
                                        }}
                                    />
                                </DashboardChartCard>
                            )}

                            {capabilities.citas && citas_asistencia_mes.length > 0 && (
                                <DashboardChartCard
                                    title={t('charts.citas_asistencia')}
                                    description={t('charts.citas_asistencia_hint')}
                                    icon={CalendarDays}
                                    accent="amber"
                                >
                                    <DashboardAppointmentsChart
                                        data={citas_asistencia_mes}
                                        estadoLabel={estadoLabel}
                                    />
                                </DashboardChartCard>
                            )}
                        </div>
                    </section>
                )}

                <section className="grid min-w-0 gap-4 lg:grid-cols-3">
                    {capabilities.citas && (
                        <DashboardAppointmentsList
                            citas={proximas_citas}
                            estadoLabel={estadoLabel}
                            dateLocale={dateFnsLocale}
                        />
                    )}

                    <DashboardQuickActions title={t('quick_actions.title')} items={quickActions} />
                </section>
            </div>
        </>
    );
}

DashboardIndex.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
