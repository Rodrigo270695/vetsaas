import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Phone,
    PlugZap,
    RefreshCw,
    Smartphone,
    Square,
    WifiOff,
} from 'lucide-react';
import { useCallback, useMemo, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import {
    DataPagination,
    DataTable,
    DataToolbar,
    EmptyState,
    PageHeader,
    StatBadge,
} from '@/components/data-page';
import type { DataTableColumn } from '@/components/data-page';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

const ROUTE_URL = '/plataforma/whatsapp-salud';
const DEFAULT_PER_PAGE = 15;

type HealthRow = {
    tenant: {
        id: string;
        slug: string;
        nombre: string;
        estado: string | null;
    };
    has_session: boolean;
    status: string | null;
    phone: string | null;
    session_name: string | null;
    last_error: string | null;
    last_synced_at: string | null;
    auto_reconnect: boolean | null;
    stale: boolean;
    can_act: boolean;
};

type Scope =
    | 'problemas'
    | 'todos'
    | 'listos'
    | 'error'
    | 'desconectados'
    | 'sin_sesion'
    | 'stale'
    | 'sin_reconnect';

type PageFilters = {
    search: string;
    scope: Scope;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
};

type Props = {
    items?: Paginated<HealthRow>;
    filters?: {
        search: string;
        scope: Scope;
        per_page: number;
    };
    stats?: {
        living: number;
        with_session: number;
        without_session: number;
        ready: number;
        not_ready: number;
        with_error: number;
        disconnected: number;
        stale: number;
        reconnect_off: number;
        openwa_configured: boolean;
        rate_limited: boolean;
        stale_minutes: number;
    };
    platform?: {
        status: string | null;
        phone: string | null;
        last_error: string | null;
        last_synced_at: string | null;
        auto_reconnect: boolean | null;
        ready: boolean;
    };
};

const EMPTY_PAGINATED: Paginated<HealthRow> = {
    data: [],
    current_page: 1,
    last_page: 1,
    per_page: DEFAULT_PER_PAGE,
    from: null,
    to: null,
    total: 0,
    path: ROUTE_URL,
    first_page_url: null,
    last_page_url: null,
    next_page_url: null,
    prev_page_url: null,
    links: [],
};

const SCOPES: Scope[] = [
    'problemas',
    'todos',
    'listos',
    'error',
    'desconectados',
    'sin_sesion',
    'stale',
    'sin_reconnect',
];

const formatWhen = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString('es-PE', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

const statusKey = (row: HealthRow): string => {
    if (!row.has_session || !row.status) {
        return 'sin_sesion';
    }

    return row.status;
};

export default function PlataformaWhatsAppSaludIndex({
    items: paginated = EMPTY_PAGINATED,
    filters = { search: '', scope: 'problemas', per_page: DEFAULT_PER_PAGE },
    stats = {
        living: 0,
        with_session: 0,
        without_session: 0,
        ready: 0,
        not_ready: 0,
        with_error: 0,
        disconnected: 0,
        stale: 0,
        reconnect_off: 0,
        openwa_configured: false,
        rate_limited: false,
        stale_minutes: 15,
    },
    platform = {
        status: null,
        phone: null,
        last_error: null,
        last_synced_at: null,
        auto_reconnect: null,
        ready: false,
    },
}: Props) {
    const { t } = useTranslation('plataforma-whatsapp-salud');
    const { can } = usePermission();
    const canRestart = can('plataforma-tenants.whatsapp-restart');
    const canStop = can('plataforma-tenants.whatsapp-stop');

    const initialFilters: PageFilters = {
        search: filters.search,
        scope: filters.scope,
        per_page: filters.per_page,
        sort: null,
        direction: null,
    };

    const { search, setSearch, isLoading, setPerPage, applyFilter } =
        useDataTablePage<{ scope: string }>({
            routeUrl: ROUTE_URL,
            initialFilters,
            only: ['items', 'filters', 'stats', 'platform'],
        });

    const { secondsSince, isRefreshing, refresh } = useAutoRefresh({
        only: ['items', 'filters', 'stats', 'platform'],
        enabled: true,
        intervalMs: 60_000,
        busy: isLoading,
    });

    const postAction = useCallback((path: string) => {
        router.post(path, {}, { preserveScroll: true });
    }, []);

    const restart = useCallback(
        (row: HealthRow) => {
            if (
                !window.confirm(
                    t('restart_confirm', { name: row.tenant.nombre }),
                )
            ) {
                return;
            }
            postAction(`/plataforma/tenants/${row.tenant.id}/whatsapp/restart`);
        },
        [postAction, t],
    );

    const stop = useCallback(
        (row: HealthRow) => {
            if (
                !window.confirm(t('stop_confirm', { name: row.tenant.nombre }))
            ) {
                return;
            }
            postAction(`/plataforma/tenants/${row.tenant.id}/whatsapp/stop`);
        },
        [postAction, t],
    );

    const columns = useMemo<DataTableColumn<HealthRow>[]>(
        () => [
            {
                key: 'tenant',
                header: t('columns.tenant'),
                cell: (row) => (
                    <div className="min-w-0">
                        <p className="truncate font-medium text-foreground">
                            {row.tenant.nombre}
                        </p>
                        <p className="truncate font-mono text-xs text-muted-foreground">
                            {row.tenant.slug}
                            {row.tenant.estado ? ` · ${row.tenant.estado}` : ''}
                        </p>
                    </div>
                ),
            },
            {
                key: 'status',
                header: t('columns.status'),
                cell: (row) => {
                    const key = statusKey(row);
                    const ready = key === 'ready';
                    const bad =
                        key === 'failed' ||
                        key === 'disconnected' ||
                        key === 'sin_sesion';

                    return (
                        <div className="flex flex-wrap items-center gap-1">
                            <Badge
                                variant="outline"
                                className={cn(
                                    ready &&
                                        'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300',
                                    bad &&
                                        'border-destructive/40 bg-destructive/10 text-destructive',
                                    !ready &&
                                        !bad &&
                                        'border-amber-500/40 bg-amber-500/10 text-amber-900 dark:text-amber-300',
                                )}
                            >
                                {t(`status.${key}`, { defaultValue: key })}
                            </Badge>
                            {row.stale ? (
                                <Badge variant="outline" className="text-muted-foreground">
                                    {t('stale_badge')}
                                </Badge>
                            ) : null}
                        </div>
                    );
                },
            },
            {
                key: 'phone',
                header: t('columns.phone'),
                cell: (row) => (
                    <span className="font-mono text-xs">{row.phone ?? '—'}</span>
                ),
            },
            {
                key: 'error',
                header: t('columns.error'),
                cell: (row) => (
                    <span
                        className={cn(
                            'line-clamp-2 max-w-xs text-xs',
                            row.last_error && 'text-destructive',
                        )}
                        title={row.last_error ?? undefined}
                    >
                        {row.last_error ?? '—'}
                    </span>
                ),
            },
            {
                key: 'synced',
                header: t('columns.synced'),
                cell: (row) => (
                    <span
                        className={cn(
                            'text-xs tabular-nums',
                            row.stale
                                ? 'text-amber-700 dark:text-amber-400'
                                : 'text-muted-foreground',
                        )}
                    >
                        {formatWhen(row.last_synced_at)}
                    </span>
                ),
            },
            {
                key: 'reconnect',
                header: t('columns.reconnect'),
                cell: (row) =>
                    row.auto_reconnect === null
                        ? '—'
                        : row.auto_reconnect
                          ? t('reconnect_on')
                          : t('reconnect_off'),
            },
            {
                key: 'actions',
                header: t('columns.actions'),
                align: 'right',
                cell: (row) => {
                    if (!row.can_act || (!canRestart && !canStop)) {
                        return null;
                    }

                    return (
                        <div className="flex justify-end gap-1">
                            {canRestart ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="h-8 cursor-pointer"
                                    disabled={stats.rate_limited}
                                    onClick={() => restart(row)}
                                >
                                    <PlugZap className="size-3.5" />
                                    {t('restart')}
                                </Button>
                            ) : null}
                            {canStop ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="h-8 cursor-pointer text-destructive"
                                    disabled={stats.rate_limited}
                                    onClick={() => stop(row)}
                                >
                                    <Square className="size-3.5" />
                                    {t('stop')}
                                </Button>
                            ) : null}
                        </div>
                    );
                },
            },
        ],
        [canRestart, canStop, restart, stats.rate_limited, stop, t],
    );

    const isEmpty =
        paginated.total === 0 && !filters.search && filters.scope === 'problemas';
    const isFilteredEmpty = paginated.total === 0 && !isEmpty;

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    action={
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="cursor-pointer gap-2"
                            disabled={isRefreshing || isLoading}
                            onClick={() => refresh()}
                        >
                            <RefreshCw
                                className={cn(
                                    'size-4',
                                    (isRefreshing || isLoading) && 'animate-spin',
                                )}
                            />
                            {t('refresh')}
                            <span className="text-xs text-muted-foreground">
                                {secondsSince}s
                            </span>
                        </Button>
                    }
                />

                {stats.rate_limited ? (
                    <Alert variant="destructive">
                        <AlertTriangle />
                        <AlertTitle>{t('rate_limited')}</AlertTitle>
                    </Alert>
                ) : null}

                {!stats.openwa_configured ? (
                    <Alert>
                        <WifiOff />
                        <AlertDescription>{t('openwa_off')}</AlertDescription>
                    </Alert>
                ) : null}

                <div className="rounded-xl border border-border/60 bg-card px-4 py-3">
                    <p className="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        {t('platform.title')}
                    </p>
                    <div className="flex flex-wrap items-center gap-2">
                        <StatBadge
                            icon={Smartphone}
                            label={t('platform.title')}
                            value={
                                platform.ready
                                    ? t('platform.ready')
                                    : t('platform.not_ready')
                            }
                            variant={platform.ready ? 'success' : 'warning'}
                        />
                        <StatBadge
                            icon={Phone}
                            label={t('platform.phone')}
                            value={platform.phone ?? '—'}
                            variant="muted"
                        />
                        <span className="text-xs text-muted-foreground">
                            {t('platform.synced')}: {formatWhen(platform.last_synced_at)}
                        </span>
                    </div>
                    {platform.last_error ? (
                        <p className="mt-2 text-xs text-destructive">{platform.last_error}</p>
                    ) : null}
                </div>

                <div className="flex flex-wrap gap-2">
                    <StatBadge
                        icon={Smartphone}
                        label={t('stats.ready')}
                        value={stats.ready}
                        variant="success"
                    />
                    <StatBadge
                        icon={WifiOff}
                        label={t('stats.not_ready')}
                        value={stats.not_ready}
                        variant={stats.not_ready > 0 ? 'warning' : 'muted'}
                    />
                    <StatBadge
                        icon={AlertTriangle}
                        label={t('stats.with_error')}
                        value={stats.with_error}
                        variant={stats.with_error > 0 ? 'danger' : 'muted'}
                    />
                    <StatBadge
                        icon={PlugZap}
                        label={t('stats.without_session')}
                        value={stats.without_session}
                        variant={stats.without_session > 0 ? 'warning' : 'muted'}
                    />
                    <StatBadge
                        label={t('stats.stale')}
                        value={stats.stale}
                        variant={stats.stale > 0 ? 'warning' : 'muted'}
                    />
                    <StatBadge
                        label={t('stats.reconnect_off')}
                        value={stats.reconnect_off}
                        variant={stats.reconnect_off > 0 ? 'warning' : 'muted'}
                    />
                </div>

                <DataToolbar
                    search={search}
                    onSearchChange={setSearch}
                    placeholder={t('search_placeholder')}
                    isSearching={isLoading}
                    searchWrapperClassName="sm:max-w-md"
                >
                    <div className="flex flex-wrap gap-1.5">
                        {SCOPES.map((value) => {
                            const active = filters.scope === value;

                            return (
                                <Button
                                    key={value}
                                    type="button"
                                    size="sm"
                                    variant={active ? 'default' : 'outline'}
                                    className="cursor-pointer"
                                    onClick={() => applyFilter({ scope: value })}
                                >
                                    {t(`scope.${value}`)}
                                </Button>
                            );
                        })}
                    </div>
                </DataToolbar>

                <p className="text-xs text-muted-foreground">
                    {t('cache_note', { minutes: stats.stale_minutes })}
                </p>

                {isEmpty ? (
                    <EmptyState
                        icon={Smartphone}
                        title={t('empty.title')}
                        description={t('empty.description')}
                    />
                ) : isFilteredEmpty ? (
                    <EmptyState
                        icon={Smartphone}
                        title={t('empty.filtered_title')}
                        description={t('empty.filtered_description')}
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={paginated.data}
                        rowKey={(row) => row.tenant.id}
                        isLoading={isLoading}
                        footer={
                            <DataPagination
                                meta={paginated}
                                onPerPageChange={setPerPage}
                                preservedQuery={{
                                    search: filters.search || undefined,
                                    scope:
                                        filters.scope === 'problemas'
                                            ? undefined
                                            : filters.scope,
                                    per_page: filters.per_page,
                                }}
                            />
                        }
                    />
                )}
            </div>
        </>
    );
}

PlataformaWhatsAppSaludIndex.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma', href: '/plataforma/operaciones' },
            { title: 'Sistema', href: '/plataforma/operaciones' },
            { title: 'Salud WhatsApp', href: ROUTE_URL },
        ]}
    >
        {page}
    </AppLayout>
);
