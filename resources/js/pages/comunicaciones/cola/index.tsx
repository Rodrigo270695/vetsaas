import { Head, router } from '@inertiajs/react';
import { Inbox, RotateCcw, XCircle } from 'lucide-react';
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
import type { DataTableColumn, StatBadgeVariant } from '@/components/data-page';
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
import { WhatsAppConnectCard } from '../components/whatsapp-connect-card';
import type { ColaPageProps } from '../types';

const ROUTE_URL = '/comunicaciones/cola';
const DEFAULT_PER_PAGE = 15;

function estadoVariant(estado: string): StatBadgeVariant {
    if (estado === 'pendiente') return 'warning';
    if (estado === 'procesando') return 'info';
    if (estado === 'fallido') return 'danger';

    return 'muted';
}

export default function Index({
    items: paginated,
    filters,
    stats,
    estado_options,
    tipo_options,
    whatsapp,
}: ColaPageProps) {
    const { t, i18n } = useTranslation(['comunicaciones', 'common']);
    const { can } = usePermission();
    const canManage = can('comunicaciones-cola.manage');

    const { search, setSearch, isLoading, setPerPage, applyFilter } = useDataTablePage<{
        estado: string;
        tipo: string | null;
    }>({
        routeUrl: ROUTE_URL,
        initialFilters: filters,
        only: ['items', 'filters', 'stats', 'tipo_options', 'whatsapp'],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.comunicaciones.cola.prefs',
        defaults: { per_page: DEFAULT_PER_PAGE, sort: null, direction: null },
    });

    const cancelItem = useCallback(
        (id: string) => {
            if (!window.confirm(t('actions.cancel_confirm'))) return;
            router.post(`/comunicaciones/cola/${id}/cancel`, {}, { preserveScroll: true });
        },
        [t],
    );

    const retryItem = useCallback(
        (id: string) => {
            if (!window.confirm(t('actions.retry_confirm'))) return;
            router.post(`/comunicaciones/cola/${id}/retry`, {}, { preserveScroll: true });
        },
        [t],
    );

    const columns = useMemo((): DataTableColumn<(typeof paginated.data)[number]>[] => {
        return [
            {
                key: 'tipo',
                header: t('columns.tipo'),
                cell: (row) => (
                    <span className="text-sm">
                        {t(`tipo.${row.tipo}`, { defaultValue: row.tipo })}
                    </span>
                ),
            },
            {
                key: 'destinatario',
                header: t('columns.destinatario'),
                cell: (row) => (
                    <div className="min-w-[10rem]">
                        <p className="text-sm font-medium">{row.destinatario_nombre ?? '—'}</p>
                        <p className="text-xs text-muted-foreground">{row.destinatario}</p>
                    </div>
                ),
            },
            {
                key: 'cuerpo',
                header: t('columns.mensaje'),
                cell: (row) => (
                    <p className="max-w-md truncate text-sm text-muted-foreground" title={row.cuerpo}>
                        {row.cuerpo}
                    </p>
                ),
            },
            {
                key: 'estado',
                header: t('columns.estado'),
                cell: (row) => (
                    <StatBadge
                        label={t(`estado.${row.estado}`, { defaultValue: row.estado })}
                        value=""
                        variant={estadoVariant(row.estado)}
                    />
                ),
            },
            {
                key: 'enviar_at',
                header: t('columns.programado'),
                cell: (row) =>
                    row.enviar_at
                        ? new Date(row.enviar_at).toLocaleString(i18n.language)
                        : '—',
            },
            {
                key: 'intentos',
                header: t('columns.intentos'),
                cell: (row) => (
                    <span className="text-sm tabular-nums">
                        {row.intentos}/{row.max_intentos}
                    </span>
                ),
            },
            {
                key: 'actions',
                header: '',
                cell: (row) =>
                    canManage ? (
                        <div className="flex justify-end gap-1">
                            {row.estado === 'fallido' ? (
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    className="size-8"
                                    onClick={() => retryItem(row.id)}
                                    title={t('actions.retry')}
                                >
                                    <RotateCcw className="size-4" />
                                </Button>
                            ) : null}
                            {row.estado === 'pendiente' || row.estado === 'fallido' ? (
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    className="size-8 text-destructive"
                                    onClick={() => cancelItem(row.id)}
                                    title={t('actions.cancel')}
                                >
                                    <XCircle className="size-4" />
                                </Button>
                            ) : null}
                        </div>
                    ) : null,
            },
        ];
    }, [t, i18n.language, canManage, cancelItem, retryItem]);

    return (
        <>
            <Head title={t('cola.title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('cola.title')}
                    description={t('cola.description')}
                    icon={Inbox}
                />

                <Can permission="comunicaciones-cola.view">
                    <WhatsAppConnectCard whatsapp={whatsapp} canManage={canManage} />
                </Can>

                <div className="flex flex-wrap gap-2">
                    {estado_options.map((estado) => (
                        <button
                            key={estado}
                            type="button"
                            className="cursor-pointer"
                            onClick={() => applyFilter('estado', estado)}
                        >
                            <StatBadge
                                label={t(`stats.${estado}`, { defaultValue: estado })}
                                value={stats[estado] ?? 0}
                                variant={estadoVariant(estado)}
                            />
                        </button>
                    ))}
                </div>

                <DataToolbar
                    search={search}
                    onSearchChange={setSearch}
                    placeholder={t('cola.search_placeholder')}
                    isSearching={isLoading}
                >
                    {tipo_options.length > 0 ? (
                        <Select
                            value={filters.tipo ?? 'all'}
                            onValueChange={(v) => applyFilter('tipo', v === 'all' ? null : v)}
                        >
                            <SelectTrigger className="h-9 w-[180px]">
                                <SelectValue placeholder={t('columns.tipo')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                {tipo_options.map((tipo) => (
                                    <SelectItem key={tipo} value={tipo}>
                                        {t(`tipo.${tipo}`, { defaultValue: tipo })}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    ) : null}
                </DataToolbar>

                <DataTable
                    columns={columns}
                    data={paginated.data}
                    rowKey={(row) => row.id}
                    isLoading={isLoading}
                    emptyState={
                        <EmptyState
                            title={t('cola.empty')}
                            description={t('cola.description')}
                        />
                    }
                />

                <DataPagination
                    meta={paginated}
                    onPerPageChange={setPerPage}
                    preservedQuery={{
                        search: filters.search || undefined,
                        per_page: filters.per_page,
                        estado: filters.estado,
                        tipo: filters.tipo ?? undefined,
                    }}
                />
            </div>
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Comunicaciones', href: '#' },
        { title: 'Cola saliente', href: ROUTE_URL },
    ],
};
