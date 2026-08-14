import { Head, Link, router } from '@inertiajs/react';
import { FlaskConical, MapPin, Navigation } from 'lucide-react';
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
};

type Props = {
    markers: TenantMapMarker[];
    summary: Summary;
};

function MapaDemos({ markers, summary }: Props) {
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
