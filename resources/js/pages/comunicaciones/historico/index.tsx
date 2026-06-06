import { Head } from '@inertiajs/react';
import { History } from 'lucide-react';
import { useMemo } from 'react';
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
import { useDataTablePage } from '@/hooks/use-data-table-page';
import type { HistoricoPageProps } from '../types';

const ROUTE_URL = '/comunicaciones/historico';
const DEFAULT_PER_PAGE = 15;

const EMPTY_PAGINATED: HistoricoPageProps['items'] = {
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
    filters = {
        search: '',
        per_page: DEFAULT_PER_PAGE,
        estado: 'enviado',
        tipo: null,
    },
    stats = { enviado: 0 },
}: HistoricoPageProps) {
    const { t, i18n } = useTranslation('comunicaciones');

    const { search, setSearch, isLoading, setPerPage } = useDataTablePage<{
        tipo: string | null;
    }>({
        routeUrl: ROUTE_URL,
        initialFilters: filters,
        only: ['items', 'filters', 'stats', 'tipo_options'],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.comunicaciones.historico.prefs',
        defaults: { per_page: DEFAULT_PER_PAGE, sort: null, direction: null },
    });

    const columns = useMemo((): DataTableColumn<(typeof paginated.data)[number]>[] => {
        return [
            {
                key: 'tipo',
                header: t('columns.tipo'),
                cell: (row) => t(`tipo.${row.tipo}`, { defaultValue: row.tipo }),
            },
            {
                key: 'destinatario',
                header: t('columns.destinatario'),
                cell: (row) => (
                    <div>
                        <p className="text-sm font-medium">{row.destinatario_nombre ?? '—'}</p>
                        <p className="text-xs text-muted-foreground">{row.destinatario}</p>
                    </div>
                ),
            },
            {
                key: 'cuerpo',
                header: t('columns.mensaje'),
                cell: (row) => (
                    <p className="max-w-lg truncate text-sm text-muted-foreground" title={row.cuerpo}>
                        {row.cuerpo}
                    </p>
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
                key: 'proveedor',
                header: 'ID',
                cell: (row) => (
                    <span className="font-mono text-xs text-muted-foreground">
                        {row.proveedor_msg_id ?? '—'}
                    </span>
                ),
            },
        ];
    }, [t, i18n.language]);

    return (
        <>
            <Head title={t('historico.title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('historico.title')}
                    description={t('historico.description')}
                    icon={History}
                />

                <StatBadge
                    label={t('stats.enviado')}
                    value={stats.enviado ?? 0}
                    variant="success"
                />

                <DataToolbar
                    search={search}
                    onSearchChange={setSearch}
                    placeholder={t('historico.search_placeholder')}
                    isSearching={isLoading}
                />

                <DataTable
                    columns={columns}
                    data={paginated.data}
                    rowKey={(row) => row.id}
                    isLoading={isLoading}
                    emptyState={<EmptyState title={t('historico.empty')} />}
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
        { title: 'Histórico', href: ROUTE_URL },
    ],
};
