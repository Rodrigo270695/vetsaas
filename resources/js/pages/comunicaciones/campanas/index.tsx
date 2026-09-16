import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircle2,
    History,
    Loader2,
    Megaphone,
    Pause,
    Pencil,
    Play,
    Plus,
    Send,
    Users,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
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
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';
import type { WhatsAppProps } from '../components/whatsapp-connect-card';
import {
    CampanaFormModal,
    type CampanaFormValues,
} from './components/campana-form-modal';
import { DestinatariosPickerModal } from './components/destinatarios-picker-modal';

const ROUTE_URL = '/comunicaciones/campanas';
const DEFAULT_PER_PAGE = 15;

type CampanaRow = CampanaFormValues & {
    estado: string;
    last_sent_at: string | null;
    pendientes_count: number;
    enviados_count: number;
    total_count: number;
    pacing_hint?: { code: string; label: string };
};

type EstadoFilter = 'todos' | 'borrador' | 'enviando' | 'pausada' | 'terminada';

type Props = {
    items?: Paginated<CampanaRow>;
    stats?: {
        total: number;
        borrador: number;
        enviando: number;
        pausada: number;
        terminada: number;
    };
    filters?: {
        search: string;
        estado: string | null;
        per_page: number;
    };
    whatsapp?: WhatsAppProps;
};

const EMPTY: Paginated<CampanaRow> = {
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

const EMPTY_STATS = {
    total: 0,
    borrador: 0,
    enviando: 0,
    pausada: 0,
    terminada: 0,
};

function estadoVariant(
    estado: string,
): 'muted' | 'info' | 'warning' | 'success' | 'primary' {
    if (estado === 'enviando') return 'info';
    if (estado === 'pausada') return 'warning';
    if (estado === 'terminada') return 'success';
    if (estado === 'borrador') return 'muted';

    return 'primary';
}

export default function CampanasIndex({
    items: paginated = EMPTY,
    stats = EMPTY_STATS,
    filters = { search: '', estado: null, per_page: DEFAULT_PER_PAGE },
    whatsapp = { enabled: false, configured: false, session: null },
}: Props) {
    const { t } = useTranslation(['comunicaciones', 'common']);
    const { can } = usePermission();
    const canCreate = can('comunicaciones-campanas.create');
    const canUpdate = can('comunicaciones-campanas.update');
    const canManage = can('comunicaciones-campanas.manage');
    const whatsappReady = Boolean(whatsapp.session?.is_ready);
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<CampanaFormValues | null>(null);
    const [pickerId, setPickerId] = useState<string | null>(null);
    const [pickerNombre, setPickerNombre] = useState('');
    const [busyId, setBusyId] = useState<string | null>(null);

    const estadoFilter = (filters.estado ?? 'todos') as EstadoFilter;

    const { search, setSearch, isLoading, applyFilter, setPerPage } =
        useDataTablePage<{ estado: EstadoFilter }>({
            routeUrl: ROUTE_URL,
            initialFilters: {
                search: filters.search,
                estado: estadoFilter,
                per_page: filters.per_page,
                sort: null,
                direction: null,
            },
            only: ['items', 'filters', 'stats', 'whatsapp'],
        });

    useEffect(() => {
        if (stats.enviando < 1) {
            return;
        }

        const timer = window.setInterval(() => {
            router.reload({ only: ['items', 'filters', 'stats'] });
        }, 20000);

        return () => window.clearInterval(timer);
    }, [stats.enviando]);

    const estadoOptions: readonly FilterChip<EstadoFilter>[] = useMemo(
        () => [
            { value: 'todos', label: 'Todos' },
            { value: 'borrador', label: t('campanas.estado.borrador') },
            { value: 'enviando', label: t('campanas.estado.enviando') },
            { value: 'pausada', label: t('campanas.estado.pausada') },
            { value: 'terminada', label: t('campanas.estado.terminada') },
        ],
        [t],
    );

    const post = (path: string) => {
        router.post(path, {}, { preserveScroll: true, onFinish: () => setBusyId(null) });
    };

    const columns = useMemo<DataTableColumn<CampanaRow>[]>(
        () => [
            {
                key: 'nombre',
                header: t('campanas.columns.nombre'),
                cell: (row) => (
                    <div className="flex items-center gap-2">
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <Megaphone className="size-4" strokeWidth={2.25} />
                        </span>
                        <div className="flex min-w-0 flex-col leading-tight">
                            <Link
                                href={`${ROUTE_URL}/${row.id}`}
                                className="truncate text-sm font-semibold text-foreground hover:text-primary"
                            >
                                {row.nombre}
                            </Link>
                            <span className="truncate text-xs text-muted-foreground">
                                {String(row.hora_inicio).slice(0, 5)}–
                                {String(row.hora_fin).slice(0, 5)} · cada {row.intervalo_minutos} min · {row.tope_diario}/día
                            </span>
                        </div>
                    </div>
                ),
            },
            {
                key: 'progreso',
                header: t('campanas.columns.progreso'),
                cell: (row) => (
                    <div className="flex flex-col leading-tight">
                        <span className="text-sm font-medium">
                            {t('campanas.progress', {
                                enviados: row.enviados_count,
                                total: row.total_count,
                            })}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {row.pendientes_count} pendientes
                            {row.pacing_hint && row.estado === 'enviando'
                                ? ` · ${row.pacing_hint.label}`
                                : ''}
                        </span>
                    </div>
                ),
            },
            {
                key: 'estado',
                header: t('campanas.columns.estado'),
                cell: (row) => (
                    <StatBadge
                        label={t(`campanas.estado.${row.estado}`)}
                        value=""
                        variant={estadoVariant(row.estado)}
                    />
                ),
            },
            {
                key: 'acciones',
                header: <span className="sr-only">Acciones</span>,
                align: 'right',
                className: 'w-56',
                cell: (row) => {
                    const busy = busyId === row.id;
                    const canEdit = canUpdate && row.estado !== 'terminada';
                    const canPick =
                        canUpdate && row.estado !== 'terminada';
                    const canStart =
                        canManage &&
                        (row.estado === 'borrador' || row.estado === 'pausada');
                    const canPause = canManage && row.estado === 'enviando';
                    const sendBlocked =
                        !whatsappReady || row.pendientes_count === 0;
                    let sendHint = t('campanas.start');
                    if (!whatsappReady) {
                        sendHint = 'Conectá WhatsApp para enviar';
                    } else if (row.pendientes_count === 0) {
                        sendHint = 'Elegí destinatarios primero';
                    } else if (row.estado === 'pausada') {
                        sendHint = t('campanas.resume');
                    }

                    return (
                        <div className="flex items-center justify-end gap-1.5">
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        className="size-8 cursor-pointer bg-violet-500/10 text-violet-700 hover:bg-violet-500/20 hover:text-violet-800"
                                        asChild
                                    >
                                        <Link href={`${ROUTE_URL}/${row.id}`}>
                                            <History className="size-4" strokeWidth={2.25} />
                                        </Link>
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>Historial de envíos</TooltipContent>
                            </Tooltip>
                            {canPick ? (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            className="size-8 cursor-pointer bg-sky-500/10 text-sky-700 hover:bg-sky-500/20 hover:text-sky-800"
                                            onClick={() => {
                                                setPickerId(row.id);
                                                setPickerNombre(row.nombre);
                                            }}
                                        >
                                            <Users className="size-4" strokeWidth={2.25} />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Elegir destinatarios</TooltipContent>
                                </Tooltip>
                            ) : null}
                            {canEdit ? (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            className="size-8 cursor-pointer bg-amber-500/10 text-amber-700 hover:bg-amber-500/20 hover:text-amber-800"
                                            onClick={() => {
                                                setEditing(row);
                                                setModalOpen(true);
                                            }}
                                        >
                                            <Pencil className="size-4" strokeWidth={2.25} />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Editar campaña</TooltipContent>
                                </Tooltip>
                            ) : null}
                            {canPause ? (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            className="size-8 cursor-pointer bg-orange-500/10 text-orange-700 hover:bg-orange-500/20"
                                            disabled={busy}
                                            onClick={() => {
                                                if (!window.confirm(t('campanas.pause_confirm'))) {
                                                    return;
                                                }
                                                setBusyId(row.id);
                                                post(`${ROUTE_URL}/${row.id}/pause`);
                                            }}
                                        >
                                            <Pause className="size-4" strokeWidth={2.25} />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>{t('campanas.pause')}</TooltipContent>
                                </Tooltip>
                            ) : null}
                            {canStart ? (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <span>
                                            <Button
                                                type="button"
                                                size="icon"
                                                className={cn(
                                                    'size-8 cursor-pointer',
                                                    sendBlocked
                                                        ? 'bg-muted text-muted-foreground'
                                                        : 'bg-emerald-600 text-white hover:bg-emerald-700',
                                                )}
                                                disabled={busy || sendBlocked}
                                                onClick={() => {
                                                    if (!window.confirm(t('campanas.start_confirm'))) {
                                                        return;
                                                    }
                                                    setBusyId(row.id);
                                                    post(`${ROUTE_URL}/${row.id}/start`);
                                                }}
                                            >
                                                {busy ? (
                                                    <Loader2 className="size-4 animate-spin" />
                                                ) : row.estado === 'pausada' ? (
                                                    <Play className="size-4" strokeWidth={2.25} />
                                                ) : (
                                                    <Send className="size-4" strokeWidth={2.25} />
                                                )}
                                            </Button>
                                        </span>
                                    </TooltipTrigger>
                                    <TooltipContent>{sendHint}</TooltipContent>
                                </Tooltip>
                            ) : null}
                            {row.estado === 'terminada' ? (
                                <CheckCircle2 className="size-4 text-emerald-500" />
                            ) : null}
                        </div>
                    );
                },
            },
        ],
        [busyId, canManage, canUpdate, t, whatsappReady],
    );

    return (
        <>
            <Head title={t('campanas.title')} />
            <div className="flex flex-1 flex-col gap-3 p-4 sm:p-6">
                <PageHeader
                    title={t('campanas.title')}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <span>{t('campanas.description')}</span>
                            {whatsappReady ? (
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                                    <span className="size-1.5 rounded-full bg-emerald-500" />
                                    WhatsApp listo
                                    {whatsapp.session?.phone
                                        ? ` · ${whatsapp.session.phone}`
                                        : ''}
                                </span>
                            ) : (
                                <Link
                                    href="/comunicaciones/cola"
                                    className="inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 px-2.5 py-0.5 text-xs font-medium text-amber-800 hover:bg-amber-500/20 dark:text-amber-300"
                                >
                                    <span className="size-1.5 rounded-full bg-amber-500" />
                                    WhatsApp desconectado — ir a vincular
                                </Link>
                            )}
                        </span>
                    }
                    stats={[
                        { label: 'Total', value: stats.total, variant: 'info', icon: Megaphone },
                        { label: t('campanas.estado.borrador'), value: stats.borrador, variant: 'muted' },
                        { label: t('campanas.estado.enviando'), value: stats.enviando, variant: 'info', icon: Send },
                        { label: t('campanas.estado.pausada'), value: stats.pausada, variant: 'warning' },
                        { label: t('campanas.estado.terminada'), value: stats.terminada, variant: 'success' },
                    ]}
                    action={
                        canCreate ? (
                            <Button
                                type="button"
                                size="sm"
                                className="cursor-pointer gap-1.5"
                                onClick={() => {
                                    setEditing(null);
                                    setModalOpen(true);
                                }}
                            >
                                <Plus className="size-3.5" />
                                {t('campanas.new')}
                            </Button>
                        ) : null
                    }
                />

                <DataTable
                    columns={columns}
                    data={paginated.data}
                    rowKey={(row) => row.id}
                    isLoading={isLoading}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder="Buscar campaña…"
                        >
                            <FilterChips
                                ariaLabel={t('campanas.columns.estado')}
                                value={estadoFilter}
                                onChange={(estado) => applyFilter({ estado })}
                                options={estadoOptions}
                            />
                        </DataToolbar>
                    }
                    emptyState={
                        <EmptyState
                            icon={Megaphone}
                            title={t('campanas.empty')}
                            description={t('campanas.empty_hint')}
                        />
                    }
                    footer={
                        <DataPagination
                            meta={paginated}
                            onPerPageChange={setPerPage}
                            preservedQuery={{
                                search: filters.search || undefined,
                                estado: filters.estado ?? undefined,
                                per_page: filters.per_page,
                            }}
                        />
                    }
                />
            </div>

            <CampanaFormModal
                open={modalOpen}
                onOpenChange={setModalOpen}
                campana={editing}
            />
            <DestinatariosPickerModal
                open={pickerId !== null}
                campanaId={pickerId}
                campanaNombre={pickerNombre}
                onOpenChange={(open) => {
                    if (!open) {
                        setPickerId(null);
                    }
                }}
            />
        </>
    );
}

CampanasIndex.layout = {
    breadcrumbs: [
        { title: 'Comunicaciones', href: '#' },
        { title: 'Campañas', href: ROUTE_URL },
    ],
};
