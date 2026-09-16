import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircle2,
    Megaphone,
    Pause,
    Phone,
    Send,
    Users,
} from 'lucide-react';
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
    cuerpo: string;
    tope_diario: number;
    intervalo_minutos: number;
    hora_inicio: string;
    hora_fin: string;
    estado: string;
};

type LoteRow = {
    id: string;
    nombre_snapshot: string;
    telefono_normalizado: string;
    mascota_nombres: string | null;
    estado: string;
    enviado_at: string | null;
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

    const post = (path: string, extra: Record<string, unknown> = {}) => {
        router.post(path, extra, { preserveScroll: true });
    };

    const loteColumns = useMemo<DataTableColumn<LoteRow>[]>(
        () => [
            {
                key: 'nombre',
                header: t('campanas.columns.destinatario'),
                cell: (row) => (
                    <div className="flex items-center gap-2">
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <Users className="size-4" strokeWidth={2.25} />
                        </span>
                        <div className="flex min-w-0 flex-col leading-tight">
                            <span className="truncate text-sm font-semibold">
                                {row.nombre_snapshot}
                            </span>
                            <span className="truncate text-xs text-muted-foreground">
                                {row.mascota_nombres ?? '—'}
                            </span>
                        </div>
                    </div>
                ),
            },
            {
                key: 'telefono',
                header: t('campanas.columns.telefono'),
                cell: (row) => (
                    <span className="flex items-center gap-1 font-mono text-xs">
                        <Phone className="size-3" />
                        {formatWhatsAppPhone(row.telefono_normalizado)}
                    </span>
                ),
            },
            {
                key: 'estado',
                header: t('campanas.columns.estado'),
                cell: (row) =>
                    row.estado === 'enviado' ? (
                        <span className="flex items-center gap-1 text-xs text-emerald-600">
                            <CheckCircle2 className="size-3.5" />
                            {t('campanas.estado.enviado')}
                            {row.enviado_at
                                ? ` · ${new Date(row.enviado_at).toLocaleString('es-PE', {
                                      dateStyle: 'short',
                                      timeStyle: 'short',
                                  })}`
                                : ''}
                        </span>
                    ) : (
                        <StatBadge
                            label={t(`campanas.estado.${row.estado}`)}
                            value=""
                            variant={row.estado === 'fallido' ? 'danger' : 'warning'}
                        />
                    ),
            },
            {
                key: 'acciones',
                header: <span className="sr-only">Acciones</span>,
                align: 'right',
                cell: (row) =>
                    canUpdate && row.estado === 'pendiente' ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="cursor-pointer text-destructive"
                            onClick={() =>
                                router.delete(`${routeUrl}/destinatarios/${row.id}`, {
                                    preserveScroll: true,
                                })
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
                cell: (row) => (
                    <span className="text-sm font-semibold">{row.nombre}</span>
                ),
            },
            {
                key: 'telefono',
                header: t('campanas.columns.telefono'),
                cell: (row) => (
                    <span className="font-mono text-xs">
                        {formatWhatsAppPhone(row.telefono ?? row.telefono_alt ?? '')}
                    </span>
                ),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={campana.nombre} />
            <div className="flex flex-1 flex-col gap-3 p-4 sm:p-6">
                <PageHeader
                    title={campana.nombre}
                    description={t('campanas.only_mobile')}
                    stats={[
                        { label: t('campanas.stats.pendiente'), value: stats.pendiente, variant: 'warning' },
                        { label: t('campanas.stats.enviado'), value: stats.enviado, variant: 'success' },
                        { label: t('campanas.stats.fallido'), value: stats.fallido, variant: stats.fallido > 0 ? 'danger' : 'muted' },
                        { label: t('campanas.stats.elegibles'), value: stats.elegibles, variant: 'info' },
                        {
                            label: t('campanas.today', {
                                count: stats.enviados_hoy,
                                tope: campana.tope_diario,
                            }),
                            value: '',
                            variant: 'primary',
                        },
                    ]}
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link href={ROUTE_URL}>{t('common:actions.back')}</Link>
                            </Button>
                            {canManage &&
                            (campana.estado === 'borrador' ||
                                campana.estado === 'pausada') ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    className="cursor-pointer gap-1.5 bg-emerald-600 text-white hover:bg-emerald-700"
                                    onClick={() => {
                                        if (!window.confirm(t('campanas.start_confirm'))) {
                                            return;
                                        }
                                        post(`${routeUrl}/start`);
                                    }}
                                >
                                    <Send className="size-3.5" />
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
                                    className="cursor-pointer gap-1.5"
                                    onClick={() => {
                                        if (!window.confirm(t('campanas.pause_confirm'))) {
                                            return;
                                        }
                                        post(`${routeUrl}/pause`);
                                    }}
                                >
                                    <Pause className="size-3.5" />
                                    {t('campanas.pause')}
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
                        className="cursor-pointer gap-1.5"
                        onClick={() => applyFilter({ scope: 'agregar' })}
                    >
                        <Users className="size-3.5" />
                        {t('campanas.agregar')}
                    </Button>
                </div>

                <DataTable
                    columns={isAgregar ? elegibleColumns : loteColumns}
                    data={isAgregar ? elegibleRows : lote.data}
                    rowKey={(row) => row.id}
                    selection={isAgregar && canUpdate ? selection : undefined}
                    isLoading={isLoading}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            placeholder={t('campanas.search_placeholder')}
                            isSearching={isLoading}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={Megaphone}
                            title={t('campanas.empty')}
                            description={t('campanas.only_mobile')}
                        />
                    }
                    footer={
                        <DataPagination
                            meta={isAgregar && elegibles ? elegibles : lote}
                            preservedQuery={{
                                search: filters.search || undefined,
                                scope: filters.scope,
                                per_page: filters.per_page,
                            }}
                        />
                    }
                />

                {isAgregar && canUpdate ? (
                    <>
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
                                        propietario_ids: [...selection.selectedIds],
                                    });
                                    selection.clear();
                                }}
                            >
                                {t('campanas.add_selected')}
                            </Button>
                        </BulkActionBar>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="w-fit cursor-pointer"
                            onClick={() => {
                                if (!window.confirm(t('campanas.add_all_confirm'))) {
                                    return;
                                }
                                post(`${routeUrl}/destinatarios/todos`, {
                                    search: filters.search,
                                });
                            }}
                        >
                            {t('campanas.add_all')}
                        </Button>
                    </>
                ) : null}
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
