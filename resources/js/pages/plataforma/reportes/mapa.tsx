import { Head, Link, router } from '@inertiajs/react';
import {
    Crosshair,
    Gift,
    MapPin,
    Navigation,
    RefreshCw,
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
    gps_refresh_pending?: number;
};

type Props = {
    markers: TenantMapMarker[];
    summary: Summary;
};

function Mapa({ markers, summary }: Props) {
    const { t } = useTranslation(['plataforma-reportes', 'common']);
    const [showPaid, setShowPaid] = useState(true);
    const [showFree, setShowFree] = useState(true);
    const [soliciting, setSoliciting] = useState(false);

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
            label: t('mapa.kpis.gps'),
            value: summary.gps,
            hint: t('mapa.kpis.gps_hint'),
            icon: Navigation,
            accent: 'emerald',
        },
        {
            key: 'departamento',
            label: t('mapa.kpis.departamento'),
            value: summary.departamento,
            hint: t('mapa.kpis.departamento_hint'),
            icon: MapPin,
            accent: 'amber',
        },
        {
            key: 'paid',
            label: t('mapa.kpis.paid'),
            value: summary.paid,
            hint: t('mapa.kpis.paid_hint'),
            icon: Wallet,
            accent: 'brand',
        },
        {
            key: 'free',
            label: t('mapa.kpis.free'),
            value: summary.free,
            hint: t('mapa.kpis.free_hint'),
            icon: Gift,
            accent: 'sky',
        },
    ];

    const solicitarGps = () => {
        setSoliciting(true);
        router.post(
            '/plataforma/reportes/mapa/solicitar-gps',
            {},
            {
                preserveScroll: true,
                onFinish: () => setSoliciting(false),
            },
        );
    };

    return (
        <>
            <Head title={t('mapa.title')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('mapa.title')}
                    description={t('mapa.subtitle')}
                    action={
                        <div className="flex flex-wrap items-center gap-2">
                            <StatBadge
                                label={t('mapa.cobertura_label')}
                                value={`${summary.cobertura_geo_pct}%`}
                                variant={
                                    summary.cobertura_geo_pct >= 70
                                        ? 'success'
                                        : 'warning'
                                }
                                icon={MapPin}
                            />
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/plataforma/reportes/mapa-demos">
                                    {t('mapa_demos.open')}
                                </Link>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/plataforma/reportes">
                                    {t('mapa.back_reportes')}
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <p className="max-w-3xl text-sm text-muted-foreground">
                    {t('mapa.capture_hint')}
                </p>

                <div className="flex flex-col gap-3 rounded-xl border border-border/70 bg-card px-4 py-3 shadow-sm sm:flex-row sm:flex-wrap sm:items-center">
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            size="sm"
                            className="gap-1.5"
                            disabled={soliciting}
                            onClick={solicitarGps}
                        >
                            <Crosshair className="size-3.5" aria-hidden />
                            {t('mapa.solicitar_gps')}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="gap-1.5"
                            onClick={() =>
                                router.reload({ only: ['markers', 'summary'] })
                            }
                        >
                            <RefreshCw className="size-3.5" aria-hidden />
                            {t('mapa.refresh')}
                        </Button>
                        {(summary.gps_refresh_pending ?? 0) > 0 ? (
                            <span className="text-xs text-amber-700 dark:text-amber-400">
                                {t('mapa.refresh_pending', {
                                    count: summary.gps_refresh_pending,
                                })}
                            </span>
                        ) : null}
                    </div>
                    <div className="flex flex-wrap items-center gap-4 sm:ml-auto">
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
                        <p className="text-xs text-muted-foreground">
                            {t('mapa.filter_hint', {
                                gps: gpsMarkers.length,
                                approx: approxMarkers.length,
                            })}
                        </p>
                    </div>
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
