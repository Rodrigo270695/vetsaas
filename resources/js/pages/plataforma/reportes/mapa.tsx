import { Head, Link } from '@inertiajs/react';
import {
    Gift,
    MapPin,
    Navigation,
    Wallet,
} from 'lucide-react';
import { type ReactNode, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    DashboardKpiGrid,
    type DashboardKpiItem,
} from '@/components/dashboard/dashboard-kpi-grid';
import { PageHeader, StatBadge } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
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
    const [showPaid, setShowPaid] = useState(true);
    const [showFree, setShowFree] = useState(true);

    const filtered = useMemo(() => {
        return markers.filter((m) => {
            if (m.segment === 'paid' && !showPaid) {
                return false;
            }
            if (m.segment === 'free' && !showFree) {
                return false;
            }
            return true;
        });
    }, [markers, showPaid, showFree]);

    // Mutuamente excluyentes: con GPS no aparece en el mapa aproximado.
    const gpsMarkers = useMemo(
        () => filtered.filter((m) => m.source === 'gps'),
        [filtered],
    );
    const approxMarkers = useMemo(
        () => filtered.filter((m) => m.source === 'departamento'),
        [filtered],
    );

    const kpiItems: DashboardKpiItem[] = [
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

                <div className="flex flex-wrap items-center gap-4 rounded-xl border border-border/70 bg-card px-4 py-3 shadow-sm">
                    <p className="text-sm font-medium">{t('mapa.filters')}</p>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="filter-paid"
                            checked={showPaid}
                            onCheckedChange={(v) => setShowPaid(v === true)}
                        />
                        <Label
                            htmlFor="filter-paid"
                            className="cursor-pointer text-sm font-normal"
                        >
                            {t('mapa.filter_paid')}
                        </Label>
                    </div>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="filter-free"
                            checked={showFree}
                            onCheckedChange={(v) => setShowFree(v === true)}
                        />
                        <Label
                            htmlFor="filter-free"
                            className="cursor-pointer text-sm font-normal"
                        >
                            {t('mapa.filter_free')}
                        </Label>
                    </div>
                    <p className="w-full text-xs text-muted-foreground sm:w-auto sm:ml-auto">
                        {t('mapa.filter_hint', {
                            gps: gpsMarkers.length,
                            approx: approxMarkers.length,
                        })}
                    </p>
                </div>

                <DashboardKpiGrid items={kpiItems} />

                <div className="grid gap-6 xl:grid-cols-2">
                    <TenantsMarketingMap
                        markers={gpsMarkers}
                        title={t('mapa.gps_title')}
                        description={t('mapa.gps_hint')}
                        emptyLabel={t('mapa.empty_gps')}
                        mapClassName="h-[min(55vh,560px)] min-h-[360px]"
                    />
                    <TenantsMarketingMap
                        markers={approxMarkers}
                        title={t('mapa.approx_title')}
                        description={t('mapa.approx_hint')}
                        emptyLabel={t('mapa.empty_approx')}
                        mapClassName="h-[min(55vh,560px)] min-h-[360px]"
                    />
                </div>
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
