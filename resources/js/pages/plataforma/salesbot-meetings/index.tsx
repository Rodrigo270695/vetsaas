import { Head } from '@inertiajs/react';
import { CalendarDays, ExternalLink, Video } from 'lucide-react';
import { useMemo } from 'react';
import {
    DataPagination,
    DataTable,
    DataToolbar,
    EmptyState,
    FilterChips,
    PageHeader,
    StatBadge,
} from '@/components/data-page';
import type { DataTableColumn, FilterChip } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import AppLayout from '@/layouts/app-layout';
import type { Paginated } from '@/types';

type Meeting = {
    id: string;
    phone: string;
    prospect_name: string | null;
    meet_status: string | null;
    meet_at: string | null;
    meet_proposed_at: string | null;
    meet_link: string | null;
    google_event_id: string | null;
    meet_notified_at: string | null;
    last_message_at: string | null;
};

type EstadoFilter = 'confirmadas' | 'propuestas' | 'proximas' | 'todas';

type Props = {
    meetings: Paginated<Meeting>;
    filters: {
        search: string;
        estado: EstadoFilter;
        per_page: number;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
    stats: {
        confirmadas: number;
        propuestas: number;
        proximas: number;
        coincidencias: number;
    };
};

const DEFAULT_PER_PAGE = 15;

const formatWhen = (iso: string | null): string => {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('es-PE', {
        timeZone: 'America/Lima',
        dateStyle: 'medium',
        timeStyle: 'short',
    });
};

const formatPhone = (phone: string): string => {
    const d = phone.replace(/\D/g, '');
    if (d.length === 11 && d.startsWith('51')) {
        return `+51 ${d.slice(2, 3)} ${d.slice(3, 6)} ${d.slice(6, 9)} ${d.slice(9)}`;
    }
    return phone;
};

export default function SalesBotMeetingsIndex({ meetings, filters, stats }: Props) {
    const { search, setSearch, isLoading, setPerPage, applyFilter } = useDataTablePage<{
        estado: EstadoFilter;
    }>({
        routeUrl: '/plataforma/salesbot-meetings',
        initialFilters: filters,
        only: ['meetings', 'filters', 'stats'],
        errorMessage: 'Error al cargar las reuniones',
        storageKey: 'vetsaas.plataforma.salesbot-meetings.prefs',
        defaults: { per_page: DEFAULT_PER_PAGE, sort: null, direction: null },
    });

    const estadoOptions: FilterChip[] = useMemo(
        () => [
            { value: 'confirmadas', label: `Confirmadas ${stats.confirmadas}` },
            { value: 'propuestas', label: `Propuestas ${stats.propuestas}` },
            { value: 'proximas', label: `Próximas ${stats.proximas}` },
            { value: 'todas', label: 'Todas' },
        ],
        [stats],
    );

    const columns = useMemo<DataTableColumn<Meeting>[]>(
        () => [
            {
                key: 'prospect_name',
                header: 'Lead',
                cell: (row) => (
                    <div className="min-w-0">
                        <p className="truncate font-medium text-foreground">
                            {row.prospect_name || 'Sin nombre'}
                        </p>
                        <p className="font-mono text-xs text-muted-foreground">
                            {formatPhone(row.phone)}
                        </p>
                    </div>
                ),
            },
            {
                key: 'meet_at',
                header: 'Fecha / hora',
                cell: (row) => (
                    <div className="text-sm">
                        <p>{formatWhen(row.meet_at ?? row.meet_proposed_at)}</p>
                        <p className="text-xs text-muted-foreground">Hora Perú</p>
                    </div>
                ),
            },
            {
                key: 'meet_status',
                header: 'Estado',
                cell: (row) => {
                    if (row.meet_link && (row.meet_status === 'confirmed' || !row.meet_status)) {
                        return <StatBadge label="Confirmada" value="" variant="success" />;
                    }
                    if (row.meet_status === 'proposed') {
                        return (
                            <StatBadge label="Pendiente confirmación" value="" variant="warning" />
                        );
                    }
                    return <StatBadge label={row.meet_status || '—'} value="" variant="default" />;
                },
            },
            {
                key: 'meet_link',
                header: 'Meet',
                cell: (row) =>
                    row.meet_link ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="h-8 gap-1.5 text-xs"
                            asChild
                        >
                            <a href={row.meet_link} target="_blank" rel="noreferrer">
                                <Video className="size-3.5" />
                                Abrir
                                <ExternalLink className="size-3" />
                            </a>
                        </Button>
                    ) : (
                        <span className="text-xs text-muted-foreground">Sin link aún</span>
                    ),
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Reuniones SalesBot" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Reuniones agendadas"
                    description="Tours Meet propuestos y confirmados por el bot de ventas."
                    stats={[
                        {
                            label: 'Confirmadas',
                            value: String(stats.confirmadas),
                            variant: 'success',
                        },
                        {
                            label: 'Propuestas',
                            value: String(stats.propuestas),
                            variant: 'warning',
                        },
                        {
                            label: 'Próximas',
                            value: String(stats.proximas),
                            variant: 'info',
                        },
                        {
                            label: 'Filtro',
                            value: String(stats.coincidencias),
                            variant: 'default',
                        },
                    ]}
                />

                <DataTable
                    columns={columns}
                    data={meetings.data}
                    rowKey={(m) => m.id}
                    isLoading={isLoading}
                    ariaLiveMessage={`${stats.coincidencias} reuniones`}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder="Buscar por nombre, teléfono o link…"
                        >
                            <FilterChips
                                ariaLabel="Filtrar reuniones"
                                value={filters.estado}
                                onChange={(estado) => applyFilter({ estado: estado as EstadoFilter })}
                                options={estadoOptions}
                            />
                        </DataToolbar>
                    }
                    footer={
                        <DataPagination
                            meta={meetings}
                            onPerPageChange={setPerPage}
                            preservedQuery={{
                                search: filters.search || undefined,
                                per_page: filters.per_page,
                                estado:
                                    filters.estado !== 'confirmadas' ? filters.estado : undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={CalendarDays}
                            title="Sin reuniones todavía"
                            description="Cuando un lead confirme un tour, aparecerá aquí con el link de Meet."
                        />
                    }
                />
            </div>
        </>
    );
}

SalesBotMeetingsIndex.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma', href: '/plataforma/operaciones' },
            { title: 'Reuniones', href: '/plataforma/salesbot-meetings' },
        ]}
    >
        {page}
    </AppLayout>
);
