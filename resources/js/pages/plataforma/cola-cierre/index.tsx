import { Head, Link } from '@inertiajs/react';
import {
    Copy,
    ExternalLink,
    Flame,
    Gift,
    Loader2,
    MessageCircle,
    Radar,
    RefreshCw,
    Timer,
    UserRound,
} from 'lucide-react';
import { useCallback, useMemo, useRef, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import {
    BulkAction,
    BulkActionBar,
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { useRowSelection } from '@/hooks/use-row-selection';
import AppLayout from '@/layouts/app-layout';
import { toastManager } from '@/lib/toast';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

const ROUTE_URL = '/plataforma/cola-cierre';
const DEFAULT_PER_PAGE = 15;

type Kind = 'lead' | 'trial' | 'prospecto' | 'referido';

type Scope = 'hoy' | 'leads' | 'trials' | 'prospectos' | 'referidos';

type ClosingRow = {
    id: string;
    kind: Kind;
    name: string;
    phone: string | null;
    reason: string;
    detail: string | null;
    script: string;
    wa_url: string | null;
    panel_url: string;
};

type PageFilters = {
    search: string;
    scope: Scope;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
};

type Props = {
    items?: Paginated<ClosingRow>;
    filters?: {
        search: string;
        scope: Scope;
        per_page: number;
    };
    stats?: {
        paying: number;
        goal: number;
        remaining: number;
        leads: number;
        trials: number;
        prospectos: number;
        referidos: number;
    };
    wa_ready?: boolean;
    wa_from_phone?: string | null;
    wa_bulk_max?: number;
    wa_delay_min?: number;
    wa_delay_max?: number;
};

const EMPTY_PAGINATED: Paginated<ClosingRow> = {
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

const SCOPES: Scope[] = ['hoy', 'leads', 'trials', 'prospectos', 'referidos'];

function csrfPostJson(
    url: string,
    body: Record<string, unknown>,
): Promise<Response> {
    const xsrf =
        document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1] ?? '';

    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': decodeURIComponent(xsrf),
        },
        body: JSON.stringify(body),
    });
}

function kindClass(kind: Kind): string {
    if (kind === 'trial') {
        return 'border-amber-500/40 bg-amber-500/10 text-amber-900 dark:text-amber-300';
    }
    if (kind === 'lead') {
        return 'border-sky-500/40 bg-sky-500/10 text-sky-900 dark:text-sky-300';
    }
    if (kind === 'prospecto') {
        return 'border-violet-500/40 bg-violet-500/10 text-violet-900 dark:text-violet-300';
    }

    return 'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300';
}

export default function PlataformaColaCierreIndex({
    items: paginated = EMPTY_PAGINATED,
    filters = { search: '', scope: 'hoy', per_page: DEFAULT_PER_PAGE },
    stats = {
        paying: 0,
        goal: 25,
        remaining: 25,
        leads: 0,
        trials: 0,
        prospectos: 0,
        referidos: 0,
    },
    wa_ready = false,
    wa_from_phone = null,
    wa_bulk_max = 15,
    wa_delay_min = 45,
    wa_delay_max = 75,
}: Props) {
    const { t } = useTranslation('plataforma-cola-cierre');

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
        only: ['items', 'filters', 'stats', 'wa_ready'],
        enabled: true,
        intervalMs: 60_000,
        busy: isLoading,
    });

    const selection = useRowSelection<ClosingRow, string>({
        rows: paginated.data,
        rowKey: (row) => row.id,
    });

    const [sendingId, setSendingId] = useState<string | null>(null);
    const sendingLock = useRef(false);
    const [bulkOpen, setBulkOpen] = useState(false);
    const [bulkBusy, setBulkBusy] = useState(false);

    const sendOne = useCallback(async (row: ClosingRow) => {
        if (!row.phone || sendingLock.current) {
            return;
        }
        sendingLock.current = true;
        setSendingId(row.id);
        try {
            const res = await csrfPostJson(`${ROUTE_URL}/send`, { id: row.id });
            const data = (await res.json()) as {
                ok?: boolean;
                message?: string;
                from_phone?: string | null;
            };
            if (!res.ok || !data.ok) {
                toastManager.error({
                    title: t('actions.send_failed'),
                    description: data.message ?? t('actions.send_failed'),
                });

                return;
            }
            toastManager.success({
                title: data.message ?? t('actions.sent'),
                description: t('actions.sent_from', {
                    phone: data.from_phone || wa_from_phone || 'plataforma',
                }),
            });
        } catch {
            toastManager.error({ title: t('actions.send_failed') });
        } finally {
            sendingLock.current = false;
            setSendingId(null);
        }
    }, [t, wa_from_phone]);

    const sendBulk = useCallback(async () => {
        const ids = Array.from(selection.selectedIds).slice(0, wa_bulk_max);
        if (ids.length === 0 || bulkBusy) {
            return;
        }
        setBulkBusy(true);
        try {
            const res = await csrfPostJson(`${ROUTE_URL}/send-bulk`, { ids });
            const data = (await res.json()) as {
                ok?: boolean;
                message?: string;
            };
            if (!res.ok || !data.ok) {
                toastManager.error({
                    title: t('actions.send_failed'),
                    description: data.message ?? t('actions.send_failed'),
                });

                return;
            }
            toastManager.success({
                title: t('bulk.queued_title'),
                description: data.message,
            });
            setBulkOpen(false);
            selection.clear();
        } catch {
            toastManager.error({ title: t('actions.send_failed') });
        } finally {
            setBulkBusy(false);
        }
    }, [bulkBusy, selection, t, wa_bulk_max]);

    const columns = useMemo<DataTableColumn<ClosingRow>[]>(
        () => [
            {
                key: 'who',
                header: t('columns.who'),
                cell: (row) => (
                    <div className="min-w-0">
                        <p className="truncate font-medium text-foreground">
                            {row.name}
                        </p>
                        <p className="truncate font-mono text-xs text-muted-foreground">
                            {row.phone ?? '—'}
                        </p>
                    </div>
                ),
            },
            {
                key: 'kind',
                header: t('columns.kind'),
                cell: (row) => (
                    <Badge variant="outline" className={cn(kindClass(row.kind))}>
                        {t(`kind.${row.kind}`)}
                    </Badge>
                ),
            },
            {
                key: 'reason',
                header: t('columns.reason'),
                cell: (row) => (
                    <div className="max-w-xs text-sm">
                        <p>{row.reason}</p>
                        {row.detail ? (
                            <p className="truncate text-xs text-muted-foreground">
                                {row.detail}
                            </p>
                        ) : null}
                    </div>
                ),
            },
            {
                key: 'script',
                header: t('columns.script'),
                showInMobile: true,
                cell: (row) => (
                    <p className="max-w-md text-xs leading-relaxed text-muted-foreground">
                        {row.script}
                    </p>
                ),
            },
            {
                key: 'actions',
                header: t('columns.actions'),
                align: 'right',
                cell: (row) => (
                    <div className="flex justify-end gap-1">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className={cn(
                                'size-8 border-[#25D366]/35 bg-[#25D366]/10 text-[#128C7E] hover:bg-[#25D366]/20 hover:text-[#075E54]',
                                'dark:border-[#25D366]/40 dark:bg-[#25D366]/15 dark:text-[#4ADE80] dark:hover:bg-[#25D366]/25 dark:hover:text-[#86EFAC]',
                                !row.phone && 'opacity-40',
                            )}
                            disabled={!row.phone || sendingId !== null}
                            title={
                                wa_ready
                                    ? t('actions.whatsapp')
                                    : t('actions.wa_offline')
                            }
                            onClick={() => void sendOne(row)}
                        >
                            {sendingId === row.id ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <MessageCircle className="size-4" />
                            )}
                            <span className="sr-only">
                                {t('actions.whatsapp')}
                            </span>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className="size-8 border-amber-500/35 bg-amber-500/10 text-amber-800 hover:bg-amber-500/20 hover:text-amber-950 dark:border-amber-400/40 dark:bg-amber-400/10 dark:text-amber-300 dark:hover:bg-amber-400/20 dark:hover:text-amber-200"
                            title={t('actions.copy')}
                            onClick={() => {
                                void navigator.clipboard.writeText(row.script);
                                toastManager.success({
                                    title: t('actions.copied'),
                                });
                            }}
                        >
                            <Copy className="size-4" />
                            <span className="sr-only">{t('actions.copy')}</span>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className="size-8 border-sky-500/35 bg-sky-500/10 text-sky-700 hover:bg-sky-500/20 hover:text-sky-900 dark:border-sky-400/40 dark:bg-sky-400/10 dark:text-sky-300 dark:hover:bg-sky-400/20 dark:hover:text-sky-200"
                            asChild
                            title={t('actions.open')}
                        >
                            <Link href={row.panel_url}>
                                <ExternalLink className="size-4" />
                                <span className="sr-only">
                                    {t('actions.open')}
                                </span>
                            </Link>
                        </Button>
                    </div>
                ),
            },
        ],
        [sendOne, sendingId, t, wa_ready],
    );

    const isEmpty =
        paginated.total === 0 && !filters.search && filters.scope === 'hoy';
    const isFilteredEmpty = paginated.total === 0 && !isEmpty;

    return (
        <>
            <Head title={t('title')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description', {
                        paying: stats.paying,
                        goal: stats.goal,
                    })}
                    actions={
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="cursor-pointer"
                            disabled={isRefreshing}
                            onClick={() => refresh()}
                        >
                            <RefreshCw
                                className={cn(
                                    'size-4',
                                    isRefreshing && 'animate-spin',
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
                        icon={Flame}
                        label={t('stats.remaining', { goal: stats.goal })}
                        value={stats.remaining}
                        variant={stats.remaining > 0 ? 'warning' : 'success'}
                    />
                    <StatBadge
                        icon={UserRound}
                        label={t('stats.paying')}
                        value={stats.paying}
                        variant="success"
                    />
                    <StatBadge
                        icon={MessageCircle}
                        label={t('stats.leads')}
                        value={stats.leads}
                        variant={stats.leads > 0 ? 'info' : 'muted'}
                    />
                    <StatBadge
                        icon={Timer}
                        label={t('stats.trials')}
                        value={stats.trials}
                        variant={stats.trials > 0 ? 'warning' : 'muted'}
                    />
                    <StatBadge
                        icon={Radar}
                        label={t('stats.prospectos')}
                        value={stats.prospectos}
                        variant={stats.prospectos > 0 ? 'info' : 'muted'}
                    />
                    <StatBadge
                        icon={Gift}
                        label={t('stats.referidos')}
                        value={stats.referidos}
                        variant="muted"
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
                        {SCOPES.map((value) => (
                            <Button
                                key={value}
                                type="button"
                                size="sm"
                                variant={
                                    filters.scope === value
                                        ? 'default'
                                        : 'outline'
                                }
                                className="cursor-pointer"
                                onClick={() => applyFilter({ scope: value })}
                            >
                                {t(`scope.${value}`)}
                            </Button>
                        ))}
                    </div>
                </DataToolbar>

                <p className="text-xs text-muted-foreground">{t('note')}</p>

                {isEmpty ? (
                    <EmptyState
                        icon={Flame}
                        title={t('empty.title')}
                        description={t('empty.description')}
                    />
                ) : isFilteredEmpty ? (
                    <EmptyState
                        icon={Flame}
                        title={t('empty.filtered_title')}
                        description={t('empty.filtered_description')}
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={paginated.data}
                        rowKey={(row) => row.id}
                        isLoading={isLoading}
                        selection={selection}
                        footer={
                            <DataPagination
                                meta={paginated}
                                onPerPageChange={setPerPage}
                                preservedQuery={{
                                    search: filters.search || undefined,
                                    scope:
                                        filters.scope === 'hoy'
                                            ? undefined
                                            : filters.scope,
                                    per_page: filters.per_page,
                                }}
                            />
                        }
                    />
                )}
            </div>

            <BulkActionBar
                count={selection.count}
                labels={{
                    singular: t('bulk.selected_singular'),
                    plural: t('bulk.selected_plural'),
                }}
                onClear={selection.clear}
            >
                <BulkAction
                    type="button"
                    size="sm"
                    className="cursor-pointer gap-1.5 bg-[#25D366] text-white hover:bg-[#1ebe57]"
                    disabled={!wa_ready || bulkBusy}
                    onClick={() => setBulkOpen(true)}
                >
                    <MessageCircle className="size-4" />
                    {t('bulk.send')}
                </BulkAction>
            </BulkActionBar>

            <Dialog open={bulkOpen} onOpenChange={setBulkOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{t('bulk.title')}</DialogTitle>
                        <DialogDescription>
                            {t('bulk.body', {
                                count: Math.min(selection.count, wa_bulk_max),
                                min: wa_delay_min,
                                max: wa_delay_max,
                                max_bulk: wa_bulk_max,
                            })}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setBulkOpen(false)}
                            disabled={bulkBusy}
                        >
                            {t('bulk.cancel')}
                        </Button>
                        <Button
                            type="button"
                            className="bg-[#25D366] text-white hover:bg-[#1ebe57]"
                            disabled={bulkBusy || selection.count === 0}
                            onClick={() => void sendBulk()}
                        >
                            {bulkBusy ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <MessageCircle className="size-4" />
                            )}
                            {t('bulk.confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

PlataformaColaCierreIndex.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma', href: '/plataforma/operaciones' },
            { title: 'Ventas', href: '/plataforma/salesbot-conversations' },
            { title: 'Cola de cierre', href: ROUTE_URL },
        ]}
    >
        {page}
    </AppLayout>
);
