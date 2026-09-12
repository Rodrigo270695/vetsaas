import { Head, router } from '@inertiajs/react';
import {
    Activity,
    HeartHandshake,
    LogIn,
    MessageCircle,
    MousePointerClick,
    PhoneOff,
    Sparkles,
} from 'lucide-react';
import { useCallback, useMemo, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import {
    BulkAction,
    BulkActionBar,
    DataPagination,
    DataTable,
    DataToolbar,
    EmptyState,
    FilterChips,
    PageHeader,
    StatBadge,
} from '@/components/data-page';
import type { DataTableColumn, FilterChip } from '@/components/data-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import { useRowSelection } from '@/hooks/use-row-selection';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

const ROUTE_URL = '/plataforma/tenants/free-onboarding';
const DEFAULT_PER_PAGE = 15;

type PlanScope = 'todos' | 'free' | 'pago';
type Stage = 'todos' | 'nunca_entro' | 'activo' | 'inactivo' | 'sin_whatsapp';

type FreeRow = {
    id: string;
    tenant: {
        id: string;
        slug: string;
        nombre: string;
        estado: string | null;
        telefono: string | null;
        email: string | null;
        created_at: string | null;
    };
    plan: string;
    plan_codigo: string | null;
    last_login_at: string | null;
    last_seen_at: string | null;
    last_module: string | null;
    last_path: string | null;
    login_count: number;
    never_opened_welcome: boolean;
    has_phone: boolean;
    stage: Exclude<Stage, 'todos'>;
};

type PageFilters = {
    search: string;
    stage: Stage;
    plan: PlanScope;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
};

type Props = {
    items?: Paginated<FreeRow>;
    filters?: {
        search: string;
        stage: Stage;
        plan: PlanScope;
        per_page: number;
    };
    stats?: {
        total: number;
        nunca_entro: number;
        activo: number;
        inactivo: number;
        sin_whatsapp: number;
    };
};

const EMPTY_PAGINATED: Paginated<FreeRow> = {
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

function formatWhen(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString('es-PE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function stageBadgeClass(stage: FreeRow['stage']): string {
    if (stage === 'activo') {
        return 'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300';
    }
    if (stage === 'nunca_entro') {
        return 'border-amber-500/40 bg-amber-500/10 text-amber-900 dark:text-amber-300';
    }
    if (stage === 'sin_whatsapp') {
        return 'border-destructive/40 bg-destructive/10 text-destructive';
    }

    return 'border-sky-500/40 bg-sky-500/10 text-sky-900 dark:text-sky-300';
}

export default function FreeOnboardingIndex({
    items: paginated = EMPTY_PAGINATED,
    filters = { search: '', stage: 'todos', plan: 'free', per_page: DEFAULT_PER_PAGE },
    stats = {
        total: 0,
        nunca_entro: 0,
        activo: 0,
        inactivo: 0,
        sin_whatsapp: 0,
    },
}: Props) {
    const { t } = useTranslation(['tenants', 'common']);
    const { can } = usePermission();
    const canUpdate = can('plataforma-tenants.update');

    const initialFilters: PageFilters = {
        search: filters.search,
        stage: filters.stage,
        plan: filters.plan,
        per_page: filters.per_page,
        sort: null,
        direction: null,
    };

    const { search, setSearch, isLoading, setPerPage, applyFilter } =
        useDataTablePage<{ stage: Stage; plan: PlanScope }>({
            routeUrl: ROUTE_URL,
            initialFilters,
            only: ['items', 'filters', 'stats'],
            errorMessage: t('free_onboarding.toast_load_error'),
        });

    const stageOptions: readonly FilterChip<Stage>[] = useMemo(
        () => [
            { value: 'todos', label: t('free_onboarding.filters.all') },
            { value: 'nunca_entro', label: t('free_onboarding.filters.nunca_entro') },
            { value: 'activo', label: t('free_onboarding.filters.activo') },
            { value: 'inactivo', label: t('free_onboarding.filters.inactivo') },
            { value: 'sin_whatsapp', label: t('free_onboarding.filters.sin_whatsapp') },
        ],
        [t],
    );

    const selection = useRowSelection<FreeRow, string>({
        rows: paginated.data,
        rowKey: (row) => row.tenant.id,
        persistAcrossPages: true,
    });

    const sendCheckIn = useCallback((row: FreeRow) => {
        if (
            !window.confirm(
                t('free_onboarding.confirm_send', { name: row.tenant.nombre }),
            )
        ) {
            return;
        }

        router.post(
            `/plataforma/tenants/${row.tenant.id}/free-onboarding/send`,
            {},
            { preserveScroll: true },
        );
    }, [t]);

    const sendBulk = useCallback(() => {
        const ids = [...selection.selectedIds];
        if (ids.length === 0) {
            return;
        }
        if (!window.confirm(t('free_onboarding.confirm_send_bulk', { count: ids.length }))) {
            return;
        }

        router.post(
            '/plataforma/tenants/free-onboarding/send',
            { ids },
            { preserveScroll: true, onSuccess: () => selection.clear() },
        );
    }, [selection, t]);

    const columns = useMemo<DataTableColumn<FreeRow>[]>(
        () => [
            {
                key: 'tenant',
                header: t('columns.tenant'),
                cell: (row) => (
                    <div className="min-w-0">
                        <p className="truncate font-semibold text-foreground">
                            {row.tenant.nombre}
                        </p>
                        <p className="truncate font-mono text-xs text-muted-foreground">
                            {row.tenant.slug}
                        </p>
                    </div>
                ),
            },
            {
                key: 'contact',
                header: t('columns.contact'),
                cell: (row) => (
                    <div className="text-xs leading-tight">
                        <p className="truncate">{row.tenant.email ?? '—'}</p>
                        <p className="font-mono text-muted-foreground">
                            {row.tenant.telefono || t('row.no_phone')}
                        </p>
                    </div>
                ),
            },
            {
                key: 'plan',
                header: t('columns.plan'),
                cell: (row) => (
                    <div className="flex items-center gap-1.5">
                        <Sparkles
                            className="size-3.5 shrink-0 text-amber-500"
                            strokeWidth={2.5}
                        />
                        <span className="text-xs font-medium">{row.plan}</span>
                    </div>
                ),
            },
            {
                key: 'stage',
                header: t('free_onboarding.columns.flujo'),
                cell: (row) => (
                    <Badge variant="outline" className={cn(stageBadgeClass(row.stage))}>
                        {t(`free_onboarding.stage.${row.stage}`)}
                    </Badge>
                ),
            },
            {
                key: 'login',
                header: t('free_onboarding.columns.login'),
                cell: (row) => (
                    <div className="text-xs">
                        <p className="tabular-nums">{formatWhen(row.last_login_at)}</p>
                        <p className="text-muted-foreground">
                            {t('free_onboarding.login_count', { count: row.login_count })}
                        </p>
                        {row.never_opened_welcome ? (
                            <p className="text-amber-700 dark:text-amber-300">
                                {t('free_onboarding.never_opened')}
                            </p>
                        ) : null}
                    </div>
                ),
            },
            {
                key: 'activity',
                header: t('free_onboarding.columns.uso'),
                cell: (row) => (
                    <div className="text-xs">
                        <p className="tabular-nums">{formatWhen(row.last_seen_at)}</p>
                        <p className="truncate text-muted-foreground">
                            {row.last_module || row.last_path || '—'}
                        </p>
                    </div>
                ),
            },
            {
                key: 'created',
                header: t('columns.created_at'),
                cell: (row) => (
                    <span className="text-xs tabular-nums text-muted-foreground">
                        {formatWhen(row.tenant.created_at)}
                    </span>
                ),
            },
            {
                key: 'actions',
                header: t('columns.acciones'),
                cell: (row) =>
                    canUpdate ? (
                        <Button
                            type="button"
                            size="icon"
                            className="size-8 cursor-pointer bg-emerald-600 text-white hover:bg-emerald-700 disabled:bg-muted disabled:text-muted-foreground"
                            disabled={!row.has_phone}
                            aria-label={t('free_onboarding.send')}
                            title={t('free_onboarding.send')}
                            onClick={() => sendCheckIn(row)}
                        >
                            <MessageCircle className="size-4" strokeWidth={2.5} />
                        </Button>
                    ) : null,
            },
        ],
        [canUpdate, sendCheckIn, t],
    );

    const isEmpty =
        paginated.total === 0 &&
        !filters.search &&
        filters.stage === 'todos' &&
        filters.plan === 'free';

    return (
        <>
            <Head title={t('free_onboarding.title')} />
            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('free_onboarding.title')}
                    description={t('free_onboarding.description')}
                    stats={[
                        { label: t('stats.total'), value: stats.total, variant: 'info', icon: Sparkles },
                        {
                            label: t('free_onboarding.filters.nunca_entro'),
                            value: stats.nunca_entro,
                            variant: 'warning',
                            icon: LogIn,
                        },
                        {
                            label: t('free_onboarding.filters.activo'),
                            value: stats.activo,
                            variant: 'success',
                            icon: MousePointerClick,
                        },
                        {
                            label: t('free_onboarding.filters.inactivo'),
                            value: stats.inactivo,
                            variant: 'primary',
                            icon: Activity,
                        },
                        {
                            label: t('free_onboarding.filters.sin_whatsapp'),
                            value: stats.sin_whatsapp,
                            variant: 'danger',
                            icon: PhoneOff,
                        },
                    ]}
                    action={
                        <Button asChild variant="outline" className="cursor-pointer gap-2">
                            <a href="/plataforma/tenants">
                                {t('free_onboarding.back_to_tenants')}
                            </a>
                        </Button>
                    }
                />

                <DataTable
                    columns={columns}
                    data={paginated.data}
                    rowKey={(row) => row.tenant.id}
                    isLoading={isLoading}
                    selection={canUpdate ? selection : undefined}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder={t('search_placeholder')}
                        >
                            <Select
                                value={filters.plan}
                                onValueChange={(plan) =>
                                    applyFilter({ plan: plan as PlanScope })
                                }
                            >
                                <SelectTrigger
                                    aria-label={t('free_onboarding.plan_filter_label')}
                                    className="h-9 w-44 cursor-pointer"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="todos">
                                        {t('free_onboarding.plan_filter.todos')}
                                    </SelectItem>
                                    <SelectItem value="free">
                                        {t('free_onboarding.plan_filter.free')}
                                    </SelectItem>
                                    <SelectItem value="pago">
                                        {t('free_onboarding.plan_filter.pago')}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <FilterChips
                                ariaLabel={t('free_onboarding.filter_label')}
                                value={filters.stage}
                                onChange={(stage) => applyFilter({ stage })}
                                options={stageOptions}
                            />
                        </DataToolbar>
                    }
                    footer={
                        <DataPagination
                            meta={paginated}
                            onPerPageChange={setPerPage}
                            preservedQuery={{
                                search: filters.search || undefined,
                                stage: filters.stage !== 'todos' ? filters.stage : undefined,
                                plan: filters.plan !== 'free' ? filters.plan : undefined,
                                per_page: filters.per_page,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={HeartHandshake}
                            title={
                                isEmpty
                                    ? t('free_onboarding.empty_title')
                                    : t('empty.no_results_title')
                            }
                            description={
                                isEmpty
                                    ? t('free_onboarding.empty_description')
                                    : t('empty.no_results_description')
                            }
                        />
                    }
                />
            </div>

            {canUpdate ? (
                <BulkActionBar
                    count={selection.count}
                    labels={{
                        singular: t('free_onboarding.selected_one'),
                        plural: t('free_onboarding.selected_many'),
                    }}
                    onClear={selection.clear}
                >
                    <BulkAction
                        type="button"
                        variant="default"
                        size="sm"
                        onClick={sendBulk}
                        className="cursor-pointer gap-1.5"
                    >
                        <MessageCircle className="size-4" strokeWidth={2.5} />
                        {t('free_onboarding.send_selected')}
                    </BulkAction>
                </BulkActionBar>
            ) : null}
        </>
    );
}

FreeOnboardingIndex.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma' },
            { title: 'Tenants', href: '/plataforma/tenants' },
            { title: 'Free onboarding', href: ROUTE_URL },
        ]}
    >
        {page}
    </AppLayout>
);
