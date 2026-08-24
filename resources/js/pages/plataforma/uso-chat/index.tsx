import { Head } from '@inertiajs/react';
import {
    Building2,
    MessageSquare,
    MessagesSquare,
    Radio,
    RefreshCw,
    Users,
} from 'lucide-react';
import { useMemo, type ReactNode } from 'react';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

const ROUTE_URL = '/plataforma/uso-chat';
const DEFAULT_PER_PAGE = 15;

type ChatUsageRow = {
    tenant: {
        id: string;
        slug: string;
        nombre: string;
        ruc: string | null;
        estado: string | null;
    };
    chat_ready: boolean;
    conversations: number;
    messages_7d: number;
    messages_30d: number;
    last_message_at: string | null;
    users_online: number;
    error: string | null;
};

type PageFilters = {
    search: string;
    scope: 'activos' | 'todos';
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
};

type Props = {
    items?: Paginated<ChatUsageRow>;
    filters?: {
        search: string;
        scope: 'activos' | 'todos';
        per_page: number;
    };
    stats?: {
        tenants_scanned: number;
        tenants_with_chat: number;
        tenants_active_7d: number;
        tenants_active_30d: number;
        messages_7d: number;
        messages_30d: number;
        users_online: number;
    };
};

const EMPTY_PAGINATED: Paginated<ChatUsageRow> = {
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

function rowStatus(
    row: ChatUsageRow,
): 'active' | 'idle' | 'not_ready' | 'error' {
    if (row.error) {
        return 'error';
    }
    if (!row.chat_ready) {
        return 'not_ready';
    }
    if (row.messages_30d > 0) {
        return 'active';
    }
    return 'idle';
}

export default function PlataformaUsoChatIndex({
    items: paginated = EMPTY_PAGINATED,
    filters = { search: '', scope: 'activos', per_page: DEFAULT_PER_PAGE },
    stats = {
        tenants_scanned: 0,
        tenants_with_chat: 0,
        tenants_active_7d: 0,
        tenants_active_30d: 0,
        messages_7d: 0,
        messages_30d: 0,
        users_online: 0,
    },
}: Props) {
    const { t } = useTranslation(['plataforma-uso-chat', 'common']);

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
            only: ['items', 'filters', 'stats'],
        });

    const { secondsSince, isRefreshing, refresh } = useAutoRefresh({
        only: ['items', 'filters', 'stats'],
        enabled: true,
        intervalMs: 60_000,
        busy: isLoading,
    });

    const columns = useMemo<DataTableColumn<ChatUsageRow>[]>(
        () => [
            {
                key: 'tenant',
                header: t('columns.tenant'),
                cell: (row) => (
                    <div className="min-w-0">
                        <p className="truncate font-medium text-foreground">
                            {row.tenant.nombre}
                        </p>
                        <p className="truncate text-xs text-muted-foreground">
                            {row.tenant.slug}
                            {row.tenant.estado ? ` · ${row.tenant.estado}` : ''}
                        </p>
                    </div>
                ),
            },
            {
                key: 'messages_7d',
                header: t('columns.messages_7d'),
                align: 'right',
                className: 'tabular-nums',
                cell: (row) => row.messages_7d,
            },
            {
                key: 'messages_30d',
                header: t('columns.messages_30d'),
                align: 'right',
                className: 'tabular-nums',
                cell: (row) => row.messages_30d,
            },
            {
                key: 'conversations',
                header: t('columns.conversations'),
                align: 'right',
                className: 'tabular-nums',
                cell: (row) => row.conversations,
            },
            {
                key: 'last_message',
                header: t('columns.last_message'),
                cell: (row) => (
                    <span className="text-xs tabular-nums text-muted-foreground">
                        {formatWhen(row.last_message_at)}
                    </span>
                ),
            },
            {
                key: 'online',
                header: t('columns.online'),
                align: 'right',
                className: 'tabular-nums',
                cell: (row) => (
                    <span
                        className={cn(
                            row.users_online > 0 &&
                                'font-semibold text-emerald-700 dark:text-emerald-400',
                        )}
                    >
                        {row.users_online}
                    </span>
                ),
            },
            {
                key: 'status',
                header: t('columns.status'),
                cell: (row) => {
                    const status = rowStatus(row);
                    return (
                        <Badge
                            variant="outline"
                            className={cn(
                                status === 'active' &&
                                    'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300',
                                status === 'idle' &&
                                    'border-border text-muted-foreground',
                                status === 'not_ready' &&
                                    'border-amber-500/40 bg-amber-500/10 text-amber-900 dark:text-amber-300',
                                status === 'error' &&
                                    'border-destructive/40 bg-destructive/10 text-destructive',
                            )}
                            title={row.error ?? undefined}
                        >
                            {t(`status.${status}`)}
                        </Badge>
                    );
                },
            },
        ],
        [t],
    );

    const isEmpty =
        paginated.total === 0 &&
        !filters.search &&
        filters.scope === 'activos';
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
                                    (isRefreshing || isLoading) &&
                                        'animate-spin',
                                )}
                            />
                            {t('refresh')}
                            <span className="text-xs text-muted-foreground">
                                {secondsSince}s
                            </span>
                        </Button>
                    }
                />

                <div className="flex flex-wrap gap-2">
                    <StatBadge
                        icon={MessageSquare}
                        label={t('stats.active_7d')}
                        value={stats.tenants_active_7d}
                        variant="success"
                    />
                    <StatBadge
                        icon={MessagesSquare}
                        label={t('stats.active_30d')}
                        value={stats.tenants_active_30d}
                        variant="info"
                    />
                    <StatBadge
                        icon={MessageSquare}
                        label={t('stats.messages_7d')}
                        value={stats.messages_7d}
                    />
                    <StatBadge
                        icon={MessagesSquare}
                        label={t('stats.messages_30d')}
                        value={stats.messages_30d}
                    />
                    <StatBadge
                        icon={Radio}
                        label={t('stats.online')}
                        value={stats.users_online}
                        variant="success"
                    />
                    <StatBadge
                        icon={Building2}
                        label={t('stats.with_chat')}
                        value={stats.tenants_with_chat}
                        variant="muted"
                    />
                </div>

                <DataToolbar
                    search={search}
                    onSearchChange={setSearch}
                    placeholder={t('search_placeholder')}
                    isSearching={isLoading}
                >
                    <div className="flex flex-wrap gap-1.5">
                        {(
                            [
                                ['activos', 'scope.activos'],
                                ['todos', 'scope.todos'],
                            ] as const
                        ).map(([value, labelKey]) => {
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
                                    {t(labelKey)}
                                </Button>
                            );
                        })}
                    </div>
                </DataToolbar>

                <p className="text-xs text-muted-foreground">{t('cache_note')}</p>

                {isEmpty ? (
                    <EmptyState
                        icon={Users}
                        title={t('empty.title')}
                        description={t('empty.description')}
                    />
                ) : isFilteredEmpty ? (
                    <EmptyState
                        icon={Users}
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
                                        filters.scope === 'activos'
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

PlataformaUsoChatIndex.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma', href: '/plataforma/operaciones' },
            { title: 'Sistema', href: '/plataforma/operaciones' },
            { title: 'Uso de chat', href: ROUTE_URL },
        ]}
    >
        {page}
    </AppLayout>
);
