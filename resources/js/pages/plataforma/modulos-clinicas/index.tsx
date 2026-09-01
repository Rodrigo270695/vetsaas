import { Head } from '@inertiajs/react';
import { Check, LayoutGrid, Pencil, RefreshCw, X } from 'lucide-react';
import { useMemo, useState, type ReactNode } from 'react';
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
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAutoRefresh } from '@/hooks/use-auto-refresh';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';
import type { TenantModuleGroup } from '@/types/tenant-modules';
import { TenantModulesFormModal } from '@/pages/plataforma/tenants/components/tenant-modules-form-modal';

const ROUTE_URL = '/plataforma/modulos-clinicas';
const DEFAULT_PER_PAGE = 15;

const FLAG_KEYS = [
    'grooming',
    'hotel',
    'laboratorio',
    'hospitalizacion',
    'cirugias',
    'documentos',
    'bot_ia',
    'comunicaciones_cola',
] as const;

type Scope =
    | 'todos'
    | 'con_apagados'
    | 'sin_grooming'
    | 'sin_hotel'
    | 'sin_laboratorio'
    | 'sin_bot_nav'
    | 'sin_bot_addon'
    | 'sin_whatsapp'
    | 'sin_fel'
    | 'upsell_bot';

type ModuleRow = {
    tenant: {
        id: string;
        slug: string;
        nombre: string;
        estado: string | null;
    };
    plan: string | null;
    disabled_count: number;
    flags: Record<string, boolean>;
    bot_addon: boolean;
    whatsapp: boolean;
    sunat: boolean;
    boletas: boolean;
    facturas: boolean;
    module_groups: TenantModuleGroup[];
};

type PageFilters = {
    search: string;
    scope: Scope;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
};

type Props = {
    items?: Paginated<ModuleRow>;
    filters?: {
        search: string;
        scope: Scope;
        per_page: number;
    };
    stats?: {
        living: number;
        con_apagados: number;
        modules_on: Record<string, number>;
        whatsapp_ready: number;
        bot_addon: number;
        sunat: number;
    };
};

const EMPTY_PAGINATED: Paginated<ModuleRow> = {
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

const FILTER_GROUPS: { label: string; scopes: Scope[] }[] = [
    { label: 'filter_groups.general', scopes: ['todos', 'con_apagados'] },
    {
        label: 'filter_groups.servicios',
        scopes: ['sin_grooming', 'sin_hotel', 'sin_laboratorio'],
    },
    {
        label: 'filter_groups.bot',
        scopes: ['sin_bot_nav', 'sin_bot_addon', 'upsell_bot'],
    },
    { label: 'filter_groups.conexion', scopes: ['sin_whatsapp', 'sin_fel'] },
];

function FlagCell({ on }: { on: boolean }) {
    return on ? (
        <Check className="size-4 text-emerald-600 dark:text-emerald-400" aria-label="on" />
    ) : (
        <X className="size-4 text-muted-foreground/50" aria-label="off" />
    );
}

export default function PlataformaModulosClinicasIndex({
    items: paginated = EMPTY_PAGINATED,
    filters = { search: '', scope: 'todos', per_page: DEFAULT_PER_PAGE },
    stats = {
        living: 0,
        con_apagados: 0,
        modules_on: {},
        whatsapp_ready: 0,
        bot_addon: 0,
        sunat: 0,
    },
}: Props) {
    const { t } = useTranslation('plataforma-modulos-clinicas');
    const { can } = usePermission();
    const canEdit = can('plataforma-tenants.update');
    const [editing, setEditing] = useState<ModuleRow | null>(null);

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
            only: ['items', 'filters', 'stats', 'columns'],
        });

    const { secondsSince, isRefreshing, refresh } = useAutoRefresh({
        only: ['items', 'filters', 'stats', 'columns'],
        enabled: true,
        intervalMs: 60_000,
        busy: isLoading,
    });

    const columns = useMemo<DataTableColumn<ModuleRow>[]>(() => {
        const flag = (
            key: (typeof FLAG_KEYS)[number],
            group: string,
        ): DataTableColumn<ModuleRow> => ({
            key,
            header: t(`columns.${key}`),
            headerGroup: group,
            className: 'w-11 px-2 text-center',
            align: 'center',
            showInMobile: false,
            cell: (row) => <FlagCell on={Boolean(row.flags[key])} />,
        });

        return [
            {
                key: 'tenant',
                header: t('columns.tenant'),
                headerGroup: t('groups.clinica'),
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
                key: 'plan',
                header: t('columns.plan'),
                headerGroup: t('groups.clinica'),
                showInMobile: false,
                cell: (row) => (
                    <span className="text-sm text-muted-foreground">{row.plan ?? '—'}</span>
                ),
            },
            {
                key: 'off',
                header: t('columns.off'),
                headerGroup: t('groups.clinica'),
                align: 'right',
                className: 'tabular-nums',
                cell: (row) => (
                    <span className={cn(row.disabled_count > 0 && 'font-medium text-amber-700 dark:text-amber-400')}>
                        {row.disabled_count}
                    </span>
                ),
            },
            flag('grooming', t('groups.servicios')),
            flag('hotel', t('groups.servicios')),
            flag('laboratorio', t('groups.atencion')),
            flag('hospitalizacion', t('groups.atencion')),
            flag('cirugias', t('groups.atencion')),
            flag('bot_ia', t('groups.bot')),
            {
                key: 'bot_addon',
                header: t('columns.bot_addon'),
                headerGroup: t('groups.bot'),
                align: 'center',
                className: 'w-11 px-2 text-center',
                showInMobile: false,
                cell: (row) => <FlagCell on={row.bot_addon} />,
            },
            flag('comunicaciones_cola', t('groups.bot')),
            {
                key: 'whatsapp',
                header: t('columns.whatsapp'),
                headerGroup: t('groups.bot'),
                align: 'center',
                className: 'w-11 px-2 text-center',
                showInMobile: false,
                cell: (row) => <FlagCell on={row.whatsapp} />,
            },
            flag('documentos', t('groups.fel')),
            {
                key: 'sunat',
                header: t('columns.sunat'),
                headerGroup: t('groups.fel'),
                align: 'center',
                className: 'w-11 px-2 text-center',
                showInMobile: false,
                cell: (row) => <FlagCell on={row.sunat} />,
            },
            {
                key: 'boletas',
                header: t('columns.boletas'),
                headerGroup: t('groups.fel'),
                align: 'center',
                className: 'w-11 px-2 text-center',
                showInMobile: false,
                cell: (row) => <FlagCell on={row.boletas} />,
            },
            {
                key: 'facturas',
                header: t('columns.facturas'),
                headerGroup: t('groups.fel'),
                align: 'center',
                className: 'w-11 px-2 text-center',
                showInMobile: false,
                cell: (row) => <FlagCell on={row.facturas} />,
            },
            {
                key: 'actions',
                header: t('columns.actions'),
                headerGroup: '',
                align: 'right',
                className: 'w-12',
                cell: (row) =>
                    canEdit ? (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    className="size-8 cursor-pointer"
                                    aria-label={t('edit')}
                                    onClick={() => setEditing(row)}
                                >
                                    <Pencil className="size-3.5" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>{t('edit')}</TooltipContent>
                        </Tooltip>
                    ) : null,
            },
        ];
    }, [canEdit, t]);

    const isEmpty = paginated.total === 0 && !filters.search && filters.scope === 'todos';
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

                <div className="flex flex-wrap gap-2">
                    <StatBadge
                        icon={LayoutGrid}
                        label={t('stats.living')}
                        value={stats.living}
                        variant="muted"
                    />
                    <StatBadge
                        label={t('stats.apagados')}
                        value={stats.con_apagados}
                        variant={stats.con_apagados > 0 ? 'warning' : 'muted'}
                    />
                    <StatBadge
                        label={t('columns.grooming')}
                        value={stats.modules_on.grooming ?? 0}
                        variant="success"
                    />
                    <StatBadge
                        label={t('columns.hotel')}
                        value={stats.modules_on.hotel ?? 0}
                        variant="success"
                    />
                    <StatBadge
                        label={t('stats.whatsapp')}
                        value={stats.whatsapp_ready}
                        variant={stats.whatsapp_ready > 0 ? 'info' : 'muted'}
                    />
                    <StatBadge
                        label={t('stats.bot_addon')}
                        value={stats.bot_addon}
                        variant="info"
                    />
                    <StatBadge
                        label={t('stats.sunat')}
                        value={stats.sunat}
                        variant={stats.sunat > 0 ? 'success' : 'muted'}
                    />
                </div>

                <DataToolbar
                    search={search}
                    onSearchChange={setSearch}
                    placeholder={t('search_placeholder')}
                    isSearching={isLoading}
                    searchWrapperClassName="sm:max-w-md"
                >
                    <div className="flex w-full flex-col gap-2">
                        {FILTER_GROUPS.map((group) => (
                            <div
                                key={group.label}
                                className="flex flex-wrap items-center gap-1.5"
                            >
                                <span className="w-20 shrink-0 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                                    {t(group.label)}
                                </span>
                                {group.scopes.map((value) => (
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
                        ))}
                    </div>
                </DataToolbar>

                <p className="text-xs text-muted-foreground">{t('note')}</p>

                {isEmpty ? (
                    <EmptyState
                        icon={LayoutGrid}
                        title={t('empty.title')}
                        description={t('empty.description')}
                    />
                ) : isFilteredEmpty ? (
                    <EmptyState
                        icon={LayoutGrid}
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
                                        filters.scope === 'todos' ? undefined : filters.scope,
                                    per_page: filters.per_page,
                                }}
                            />
                        }
                    />
                )}
            </div>

            <TenantModulesFormModal
                open={editing !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditing(null);
                    }
                }}
                tenant={
                    editing
                        ? {
                              id: editing.tenant.id,
                              nombre: editing.tenant.nombre,
                          }
                        : null
                }
                groups={editing?.module_groups ?? []}
            />
        </>
    );
}

PlataformaModulosClinicasIndex.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma', href: '/plataforma/tenants' },
            { title: 'Clínicas', href: '/plataforma/tenants' },
            { title: 'Módulos por clínica', href: ROUTE_URL },
        ]}
    >
        {page}
    </AppLayout>
);
