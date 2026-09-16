import { Head, Link, router } from '@inertiajs/react';
import { Megaphone, Pause, Play, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import {
    BulkActionBar,
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
import { useRowSelection } from '@/hooks/use-row-selection';
import { formatWhatsAppPhone } from '@/lib/format-whatsapp-phone';
import type { Paginated } from '@/types';
import { WhatsAppConnectCard } from '../components/whatsapp-connect-card';
import type { WhatsAppProps } from '../components/whatsapp-connect-card';

const ROUTE_URL = '/comunicaciones/campanas';

type Campana = {
    id: string;
    nombre: string;
    imagen_url: string | null;
    variantes: string[];
    tope_diario: number;
    intervalo_minutos: number;
    hora_inicio: string;
    hora_fin: string;
    estado: string;
    last_sent_at: string | null;
};

type LoteRow = {
    id: string;
    nombre_snapshot: string;
    telefono_normalizado: string;
    mascota_nombres: string | null;
    estado: string;
    enviado_at: string | null;
    error: string | null;
};

type ElegibleRow = {
    id: string;
    nombre: string;
    telefono: string | null;
    telefono_alt: string | null;
};

type Props = {
    campana: Campana;
    lote: Paginated<LoteRow>;
    elegibles?: Paginated<ElegibleRow> | null;
    stats: {
        total: number;
        pendiente: number;
        enviado: number;
        fallido: number;
        omitido: number;
        enviados_hoy: number;
        elegibles: number;
    };
    filters: {
        search: string;
        scope: 'lote' | 'agregar';
        estado: string | null;
        per_page: number;
    };
    whatsapp: WhatsAppProps;
};

export default function CampanaShow({
    campana,
    lote,
    elegibles = null,
    stats,
    filters,
    whatsapp,
}: Props) {
    const { t } = useTranslation(['comunicaciones', 'common']);
    const { can } = usePermission();
    const canUpdate = can('comunicaciones-campanas.update');
    const canManage = can('comunicaciones-campanas.manage');
    const routeUrl = `${ROUTE_URL}/${campana.id}`;

    const { search, setSearch, isLoading, applyFilter } = useDataTablePage<{
        scope: string;
        estado: string | null;
    }>({
        routeUrl,
        initialFilters: {
            search: filters.search,
            scope: filters.scope,
            estado: filters.estado,
            per_page: filters.per_page,
            sort: null,
            direction: null,
        },
        only: ['lote', 'elegibles', 'stats', 'filters', 'campana', 'whatsapp'],
    });

    const isAgregar = filters.scope === 'agregar';
    const elegibleRows = elegibles?.data ?? [];
    const selection = useRowSelection({
        rows: elegibleRows,
        rowKey: (row) => row.id,
    });

    const loteColumns = useMemo<DataTableColumn<LoteRow>[]>(
        () => [
            {
                key: 'nombre',
                header: t('campanas.columns.destinatario'),
                cell: (row) => row.nombre_snapshot,
            },
            {
                key: 'telefono',
                header: t('campanas.columns.telefono'),
                cell: (row) => formatWhatsAppPhone(row.telefono_normalizado),
            },
            {
                key: 'mascota',
                header: t('campanas.columns.mascota'),
                cell: (row) => row.mascota_nombres ?? '—',
            },
            {
                key: 'estado',
                header: t('campanas.columns.estado'),
                cell: (row) => t(`campanas.estado.${row.estado}`),
            },
            {
                key: 'enviado',
                header: t('campanas.columns.enviado'),
                cell: (row) =>
                    row.enviado_at
                        ? new Date(row.enviado_at).toLocaleString('es-PE', {
                              dateStyle: 'short',
                              timeStyle: 'short',
                          })
                        : '—',
            },
            {
                key: 'acciones',
                header: '',
                cell: (row) =>
                    canUpdate && row.estado === 'pendiente' ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="cursor-pointer text-destructive"
                            onClick={() =>
                                router.delete(
                                    `${routeUrl}/destinatarios/${row.id}`,
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {t('campanas.remove')}
                        </Button>
                    ) : null,
            },
        ],
        [canUpdate, routeUrl, t],
    );

    const elegibleColumns = useMemo<DataTableColumn<ElegibleRow>[]>(
        () => [
            {
                key: 'nombre',
                header: t('campanas.columns.destinatario'),
                cell: (row) => row.nombre,
            },
            {
                key: 'telefono',
                header: t('campanas.columns.telefono'),
                cell: (row) =>
                    formatWhatsAppPhone(row.telefono ?? row.telefono_alt ?? ''),
            },
        ],
        [t],
    );

    const post = (path: string, extra: Record<string, unknown> = {}) => {
        router.post(path, extra, { preserveScroll: true });
    };

    return (
        <>
            <Head title={campana.nombre} />
            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={campana.nombre}
                    description={t('campanas.only_mobile')}
                    action={
                        <div className="flex flex-wrap gap-2">
                            {canUpdate && campana.estado === 'borrador' ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={`${routeUrl}/edit`}>
                                        {t('campanas.edit')}
                                    </Link>
                                </Button>
                            ) : null}
                            {canManage &&
                            (campana.estado === 'borrador' ||
                                campana.estado === 'pausada') ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    className="cursor-pointer gap-2"
                                    onClick={() => {
                                        if (
                                            !window.confirm(
                                                t('campanas.start_confirm'),
                                            )
                                        ) {
                                            return;
                                        }
                                        post(`${routeUrl}/start`);
                                    }}
                                >
                                    <Play className="size-4" />
                                    {campana.estado === 'pausada'
                                        ? t('campanas.resume')
                                        : t('campanas.start')}
                                </Button>
                            ) : null}
                            {canManage && campana.estado === 'enviando' ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="cursor-pointer gap-2"
                                    onClick={() => {
                                        if (
                                            !window.confirm(
                                                t('campanas.pause_confirm'),
                                            )
                                        ) {
                                            return;
                                        }
                                        post(`${routeUrl}/pause`);
                                    }}
                                >
                                    <Pause className="size-4" />
                                    {t('campanas.pause')}
                                </Button>
                            ) : null}
                            {canUpdate && campana.estado === 'borrador' ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="cursor-pointer gap-2 text-destructive"
                                    onClick={() => {
                                        if (
                                            !window.confirm(
                                                t('campanas.delete_confirm'),
                                            )
                                        ) {
                                            return;
                                        }
                                        router.delete(routeUrl);
                                    }}
                                >
                                    <Trash2 className="size-4" />
                                    {t('campanas.delete')}
                                </Button>
                            ) : null}
                        </div>
                    }
                />

                <WhatsAppConnectCard
                    whatsapp={whatsapp}
                    canManage={can('comunicaciones-cola.manage')}
                />

                <div className="flex flex-wrap gap-2">
                    <StatBadge
                        label={t(`campanas.estado.${campana.estado}`)}
                        value=""
                    />
                    <StatBadge
                        label={t('campanas.stats.pendiente')}
                        value={stats.pendiente}
                        variant="warning"
                    />
                    <StatBadge
                        label={t('campanas.stats.enviado')}
                        value={stats.enviado}
                        variant="success"
                    />
                    <StatBadge
                        label={t('campanas.stats.fallido')}
                        value={stats.fallido}
                        variant={stats.fallido > 0 ? 'danger' : 'muted'}
                    />
                    <StatBadge
                        label={t('campanas.today', {
                            count: stats.enviados_hoy,
                            tope: campana.tope_diario,
                        })}
                        value=""
                    />
                    <StatBadge
                        label={t('campanas.stats.elegibles')}
                        value={stats.elegibles}
                        variant="info"
                    />
                </div>

                {campana.imagen_url ? (
                    <img
                        src={campana.imagen_url}
                        alt=""
                        className="h-32 w-32 rounded-lg object-cover ring-1 ring-border"
                    />
                ) : null}

                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant={!isAgregar ? 'default' : 'outline'}
                        className="cursor-pointer"
                        onClick={() => applyFilter({ scope: 'lote' })}
                    >
                        {t('campanas.lote')}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant={isAgregar ? 'default' : 'outline'}
                        className="cursor-pointer"
                        onClick={() => applyFilter({ scope: 'agregar' })}
                    >
                        {t('campanas.agregar')}
                    </Button>
                </div>

                <DataToolbar
                    search={search}
                    onSearchChange={setSearch}
                    placeholder={t('campanas.search_placeholder')}
                    isSearching={isLoading}
                />

                {isAgregar ? (
                    <>
                        {(elegibles?.data.length ?? 0) === 0 ? (
                            <EmptyState
                                icon={Megaphone}
                                title={t('campanas.empty')}
                                description={t('campanas.only_mobile')}
                            />
                        ) : (
                            <DataTable
                                columns={elegibleColumns}
                                data={elegibleRows}
                                rowKey={(row) => row.id}
                                selection={canUpdate ? selection : undefined}
                                isLoading={isLoading}
                                footer={
                                    elegibles ? (
                                        <DataPagination
                                            meta={elegibles}
                                            preservedQuery={{
                                                search: filters.search || undefined,
                                                scope: 'agregar',
                                                per_page: filters.per_page,
                                            }}
                                        />
                                    ) : null
                                }
                            />
                        )}
                        {canUpdate ? (
                            <BulkActionBar
                                count={selection.count}
                                labels={{
                                    singular: 'dueño seleccionado',
                                    plural: 'dueños seleccionados',
                                }}
                                onClear={selection.clear}
                            >
                                <Button
                                    type="button"
                                    size="sm"
                                    className="cursor-pointer"
                                    onClick={() => {
                                        post(`${routeUrl}/destinatarios`, {
                                            propietario_ids: [
                                                ...selection.selectedIds,
                                            ],
                                        });
                                        selection.clear();
                                    }}
                                >
                                    {t('campanas.add_selected')}
                                </Button>
                            </BulkActionBar>
                        ) : null}
                        {canUpdate ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="w-fit cursor-pointer"
                                onClick={() => {
                                    if (
                                        !window.confirm(
                                            t('campanas.add_all_confirm'),
                                        )
                                    ) {
                                        return;
                                    }
                                    post(`${routeUrl}/destinatarios/todos`, {
                                        search: filters.search,
                                    });
                                }}
                            >
                                {t('campanas.add_all')}
                            </Button>
                        ) : null}
                    </>
                ) : lote.data.length === 0 ? (
                    <EmptyState
                        icon={Megaphone}
                        title={t('campanas.empty')}
                        description={t('campanas.only_mobile')}
                    />
                ) : (
                    <DataTable
                        columns={loteColumns}
                        data={lote.data}
                        rowKey={(row) => row.id}
                        isLoading={isLoading}
                        footer={
                            <DataPagination
                                meta={lote}
                                preservedQuery={{
                                    search: filters.search || undefined,
                                    scope: 'lote',
                                    estado: filters.estado ?? undefined,
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

CampanaShow.layout = {
    breadcrumbs: [
        { title: 'Comunicaciones', href: '#' },
        { title: 'Campañas', href: ROUTE_URL },
    ],
};
