import { Head, router } from '@inertiajs/react';
import { BellRing, Play } from 'lucide-react';
import { useCallback, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Can } from '@/components/can';
import {
    DataPagination,
    DataTable,
    DataToolbar,
    EmptyState,
    PageHeader,
    StatBadge,
} from '@/components/data-page';
import type { DataTableColumn } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import type { Paginated } from '@/types';
import {
    WhatsAppConnectCard,
    type WhatsAppApiRoutes,
    type WhatsAppProps,
} from '@/pages/comunicaciones/components/whatsapp-connect-card';

const ROUTE_URL = '/plataforma/avisos-renovacion';
const DEFAULT_PER_PAGE = 15;

const PLATFORM_WHATSAPP_ROUTES: WhatsAppApiRoutes = {
    sync: '/plataforma/avisos-renovacion/whatsapp/sync',
    qr: '/plataforma/avisos-renovacion/whatsapp/qr',
    logout: '/plataforma/avisos-renovacion/whatsapp/logout',
    test: '/plataforma/avisos-renovacion/whatsapp/test',
};

type ReminderRow = {
    id: string;
    reminder_kind: string;
    anchor_at: string | null;
    channel: string;
    destinatario: string;
    sent_at: string | null;
    tenant: {
        slug: string | null;
        nombre: string | null;
    };
    subscription: {
        ciclo: string | null;
        estado: string | null;
    };
};

type PageProps = {
    items: Paginated<ReminderRow>;
    filters: {
        search: string;
        per_page: number;
    };
    stats: {
        total: number;
        last_7_days: number;
        kind_7d: number;
        kind_1d: number;
    };
    whatsapp: WhatsAppProps;
};

const EMPTY_PAGINATED: PageProps['items'] = {
    data: [],
    current_page: 1,
    last_page: 1,
    per_page: DEFAULT_PER_PAGE,
    total: 0,
    from: null,
    to: null,
    path: ROUTE_URL,
    links: [],
};

export default function Index({
    items: paginated = EMPTY_PAGINATED,
    filters = { search: '', per_page: DEFAULT_PER_PAGE },
    stats = { total: 0, last_7_days: 0, kind_7d: 0, kind_1d: 0 },
    whatsapp = { enabled: false, configured: false, session: null },
}: PageProps) {
    const { t, i18n } = useTranslation(['avisos-renovacion', 'common']);
    const { can } = usePermission();
    const canManage = can('plataforma-suscripciones.update');

    const { search, setSearch, isLoading, setPerPage } = useDataTablePage({
        routeUrl: ROUTE_URL,
        initialFilters: filters,
        only: ['items', 'filters', 'stats', 'whatsapp'],
    });

    const runScan = useCallback(() => {
        router.post('/plataforma/avisos-renovacion/run', {}, { preserveScroll: true });
    }, []);

    const columns = useMemo<DataTableColumn<ReminderRow>[]>(
        () => [
            {
                key: 'tenant',
                header: t('columns.tenant'),
                cell: (row) => (
                    <div>
                        <p className="font-medium">{row.tenant.nombre ?? '—'}</p>
                        {row.tenant.slug ? (
                            <p className="text-xs text-muted-foreground">{row.tenant.slug}</p>
                        ) : null}
                    </div>
                ),
            },
            {
                key: 'kind',
                header: t('columns.kind'),
                cell: (row) => t(`kind.${row.reminder_kind}` as 'kind.7d' | 'kind.1d'),
            },
            {
                key: 'vence',
                header: t('columns.vence'),
                cell: (row) =>
                    row.anchor_at
                        ? new Date(row.anchor_at).toLocaleDateString(i18n.language)
                        : '—',
            },
            {
                key: 'destinatario',
                header: t('columns.destinatario'),
                cell: (row) => row.destinatario.replace('@c.us', ''),
            },
            {
                key: 'sent_at',
                header: t('columns.sent_at'),
                cell: (row) =>
                    row.sent_at
                        ? new Date(row.sent_at).toLocaleString(i18n.language)
                        : '—',
            },
        ],
        [i18n.language, t],
    );

    return (
        <>
            <Head title={t('title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('title')}
                    description={t('subtitle')}
                    action={
                        <Can permission="plataforma-suscripciones.update">
                            <Button type="button" size="sm" onClick={runScan} className="gap-2">
                                <Play className="size-4" />
                                {t('run_scan')}
                            </Button>
                        </Can>
                    }
                />

                <WhatsAppConnectCard
                    whatsapp={whatsapp}
                    canManage={canManage}
                    apiRoutes={PLATFORM_WHATSAPP_ROUTES}
                    translationNs="avisos-renovacion"
                />

                <div className="flex flex-wrap gap-2">
                    <StatBadge label={t('stats.total')} value={stats.total} variant="info" />
                    <StatBadge
                        label={t('stats.last_7_days')}
                        value={stats.last_7_days}
                        variant="muted"
                    />
                    <StatBadge label={t('stats.kind_7d')} value={stats.kind_7d} variant="warning" />
                    <StatBadge label={t('stats.kind_1d')} value={stats.kind_1d} variant="danger" />
                </div>

                <DataToolbar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder={t('columns.tenant')}
                    perPage={filters.per_page}
                    onPerPageChange={setPerPage}
                    isLoading={isLoading}
                />

                <DataTable
                    columns={columns}
                    data={paginated.data}
                    rowKey={(row) => row.id}
                    isLoading={isLoading}
                    emptyState={
                        <EmptyState
                            icon={BellRing}
                            title={t('empty')}
                            description={t('subtitle')}
                        />
                    }
                />

                <DataPagination meta={paginated} />
            </div>
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma', href: '/plataforma/tenants' },
            { title: 'Avisos de renovación', href: ROUTE_URL },
        ]}
    >
        {page}
    </AppLayout>
);
