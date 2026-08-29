import { Head, Link, router } from '@inertiajs/react';
import { FlaskConical, MapPin, Navigation, UserRound } from 'lucide-react';
import { type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import {
    DashboardKpiGrid,
    type DashboardKpiItem,
} from '@/components/dashboard/dashboard-kpi-grid';
import { PageHeader } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import {
    TenantsMarketingMap,
    type TenantMapMarker,
} from '@/pages/plataforma/reportes/tenants-marketing-map';

type Summary = {
    total_logs: number;
    with_gps: number;
    without_gps: number;
    with_lead: number;
};

type LeadRow = {
    id: string;
    clinic_name: string | null;
    phone: string | null;
    email: string | null;
    has_gps: boolean;
    ip: string | null;
    captured_at: string | null;
};

type Props = {
    markers: TenantMapMarker[];
    summary: Summary;
    leads: LeadRow[];
};

function MapaDemos({ markers, summary, leads = [] }: Props) {
    const { t } = useTranslation(['plataforma-reportes', 'common']);

    const kpiItems: DashboardKpiItem[] = [
        {
            key: 'total',
            label: t('mapa_demos.kpis.total'),
            value: summary.total_logs,
            hint: t('mapa_demos.kpis.total_hint'),
            icon: FlaskConical,
            accent: 'violet',
        },
        {
            key: 'gps',
            label: t('mapa_demos.kpis.gps'),
            value: summary.with_gps,
            hint: t('mapa_demos.kpis.gps_hint'),
            icon: Navigation,
            accent: 'emerald',
        },
        {
            key: 'no_gps',
            label: t('mapa_demos.kpis.no_gps'),
            value: summary.without_gps,
            hint: t('mapa_demos.kpis.no_gps_hint'),
            icon: MapPin,
            accent: 'amber',
        },
        {
            key: 'lead',
            label: t('mapa_demos.kpis.lead'),
            value: summary.with_lead,
            hint: t('mapa_demos.kpis.lead_hint'),
            icon: UserRound,
            accent: 'sky',
        },
    ];

    return (
        <>
            <Head title={t('mapa_demos.title')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('mapa_demos.title')}
                    description={t('mapa_demos.subtitle')}
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                type="button"
                                onClick={() => router.reload()}
                            >
                                {t('mapa_demos.refresh')}
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/plataforma/reportes/mapa">
                                    {t('mapa_demos.back_mapa')}
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <DashboardKpiGrid items={kpiItems} />

                <TenantsMarketingMap
                    markers={markers}
                    title={t('mapa_demos.map_title')}
                    description={t('mapa_demos.map_hint')}
                    emptyLabel={t('mapa_demos.empty')}
                    mapClassName="h-[min(70vh,720px)] min-h-[420px]"
                />

                <div className="overflow-hidden rounded-xl border border-border/70 bg-card shadow-sm">
                    <div className="border-b border-border/60 px-4 py-3">
                        <h3 className="text-sm font-semibold">
                            {t('mapa_demos.leads_title')}
                        </h3>
                        <p className="text-xs text-muted-foreground">
                            {t('mapa_demos.leads_hint')}
                        </p>
                    </div>
                    {leads.length === 0 ? (
                        <p className="px-4 py-8 text-center text-sm text-muted-foreground">
                            {t('mapa_demos.leads_empty')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[640px] text-left text-[12px]">
                                <thead className="bg-muted/40 text-[10px] uppercase tracking-wide text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            {t('mapa_demos.leads_cols.when')}
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            {t('mapa_demos.leads_cols.clinic')}
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            {t('mapa_demos.leads_cols.phone')}
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            {t('mapa_demos.leads_cols.email')}
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            {t('mapa_demos.leads_cols.gps')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {leads.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-t border-border/40"
                                        >
                                            <td className="px-3 py-2.5 tabular-nums text-muted-foreground">
                                                {row.captured_at ?? '—'}
                                            </td>
                                            <td className="px-3 py-2.5 font-medium">
                                                {row.clinic_name ?? '—'}
                                            </td>
                                            <td className="px-3 py-2.5 font-mono">
                                                {row.phone ?? '—'}
                                            </td>
                                            <td className="px-3 py-2.5">
                                                {row.email ?? '—'}
                                            </td>
                                            <td className="px-3 py-2.5 text-muted-foreground">
                                                {row.has_gps
                                                    ? t('mapa_demos.leads_gps_yes')
                                                    : t('mapa_demos.leads_gps_no')}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

MapaDemos.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma', href: '/plataforma/tenants' },
            { title: 'Reportes', href: '/plataforma/reportes' },
            { title: 'Mapa demos', href: '/plataforma/reportes/mapa-demos' },
        ]}
    >
        {page}
    </AppLayout>
);

export default MapaDemos;
