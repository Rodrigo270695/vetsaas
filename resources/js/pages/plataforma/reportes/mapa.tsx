import { Head, Link } from '@inertiajs/react';
import {
    Gift,
    MapPin,
    Navigation,
    Wallet,
} from 'lucide-react';
import { type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import {
    DashboardKpiGrid,
    type DashboardKpiItem,
} from '@/components/dashboard/dashboard-kpi-grid';
import { PageHeader, StatBadge } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import {
    TenantsMarketingMap,
    type TenantMapMarker,
} from '@/pages/plataforma/reportes/tenants-marketing-map';

type Summary = {
    total_vivos: number;
    paid: number;
    free: number;
    markers: number;
    gps: number;
    departamento: number;
    cobertura_geo_pct: number;
    gps_consents: number;
};

type Props = {
    markers: TenantMapMarker[];
    summary: Summary;
};

function Mapa({ markers, summary }: Props) {
    const { t } = useTranslation(['plataforma-reportes', 'common']);

    const kpiItems: DashboardKpiItem[] = [
        {
            key: 'markers',
            title: t('mapa.kpis.markers'),
            value: String(summary.markers),
            icon: MapPin,
            tone: 'default',
        },
        {
            key: 'gps',
            title: t('mapa.kpis.gps'),
            value: String(summary.gps),
            icon: Navigation,
            tone: 'success',
        },
        {
            key: 'departamento',
            title: t('mapa.kpis.departamento'),
            value: String(summary.departamento),
            icon: MapPin,
            tone: 'warning',
        },
        {
            key: 'paid',
            title: t('kpis.paid'),
            value: String(summary.paid),
            icon: Wallet,
            tone: 'success',
        },
        {
            key: 'free',
            title: t('kpis.free'),
            value: String(summary.free),
            icon: Gift,
            tone: 'info',
        },
    ];

    return (
        <>
            <Head title={t('mapa.title')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('mapa.title')}
                    description={t('mapa.subtitle')}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <StatBadge
                                label="Geo"
                                value={`${summary.cobertura_geo_pct}%`}
                                variant={
                                    summary.cobertura_geo_pct >= 70
                                        ? 'success'
                                        : 'warning'
                                }
                                icon={MapPin}
                            />
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/plataforma/reportes">
                                    {t('mapa.back_reportes')}
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <p className="max-w-3xl text-sm text-muted-foreground">
                    {t('mapa.legend')}
                </p>

                <DashboardKpiGrid items={kpiItems} />

                <TenantsMarketingMap
                    markers={markers}
                    hideChrome
                    mapClassName="h-[min(70vh,720px)] min-h-[480px]"
                />
            </div>
        </>
    );
}

Mapa.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma', href: '/plataforma/tenants' },
            { title: 'Reportes', href: '/plataforma/reportes' },
            { title: 'Mapa', href: '/plataforma/reportes/mapa' },
        ]}
    >
        {page}
    </AppLayout>
);

export default Mapa;
