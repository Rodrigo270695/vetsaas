import { Head, router } from '@inertiajs/react';
import {
    Activity,
    Bot,
    CalendarDays,
    CheckCircle2,
    Filter,
    MessageCircle,
    PauseCircle,
    PlayCircle,
    RefreshCw,
    SendHorizonal,
    Snowflake,
    Trash2,
    User,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
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
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import { toastManager } from '@/lib/toast';
import AppLayout from '@/layouts/app-layout';
import salesbotConversations from '@/routes/plataforma/salesbot-conversations';
import type { Paginated } from '@/types';

type Conversation = {
    id: string;
    phone: string;
    prospect_name: string | null;
    turn_count: number;
    bot_active: boolean;
    converted: boolean;
    activation_trigger: string | null;
    reactivation_count: number;
    last_reactivation_at: string | null;
    last_message_at: string | null;
    last_message_body: string | null;
    last_message_role: string | null;
    created_at: string;
};

type EstadoFilter = 'todos' | 'activo' | 'pausado' | 'frio' | 'convertido';

type ConvFilters = {
    search: string;
    estado: EstadoFilter;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
};

type ConvStats = {
    total: number;
    activos: number;
    pausados: number;
    convertidos: number;
    frios: number;
    hoy: number;
    coincidencias: number;
};

type Props = {
    conversations: Paginated<Conversation>;
    filters: ConvFilters;
    stats: ConvStats;
};

const DEFAULT_PER_PAGE = 15;
const DEFAULT_ESTADO: EstadoFilter = 'todos';

const formatWhen = (iso: string | null): string => {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' });
};

/** Muestra el teléfono como número legible. */
const formatPhone = (raw: string): string => {
    const digits = raw.replace('@c.us', '').replace(/\D/g, '');
    if (digits.startsWith('51') && digits.length === 11) {
        return `+51 ${digits.slice(2, 3)} ${digits.slice(3, 6)} ${digits.slice(6, 9)} ${digits.slice(9)}`;
    }
    return `+${digits}`;
};

/** Tiempo relativo tipo "hace 3 min", "hace 2h", "hace 1d". */
const timeAgo = (iso: string | null): string => {
    if (!iso) return '—';
    const diffMs = Date.now() - new Date(iso).getTime();
    const secs = Math.floor(diffMs / 1000);
    if (secs < 60) return 'hace un momento';
    const mins = Math.floor(secs / 60);
    if (mins < 60) return `hace ${mins} min`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `hace ${hrs}h`;
    const days = Math.floor(hrs / 24);
    return `hace ${days}d`;
};

/** Hace un POST/DELETE simple usando fetch con el token CSRF. */
function csrfFetch(url: string, method: 'POST' | 'DELETE'): Promise<Response> {
    const xsrf = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1] ?? '';
    return fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': decodeURIComponent(xsrf),
        },
    });
}

export default function SalesBotConversationsIndex({ conversations, filters, stats }: Props) {
    const { t } = useTranslation(['salesbot-conversations', 'common']);
    const { can } = usePermission();
    const canUpdate = can('salesbot-knowledge.update');
    const canDelete = can('salesbot-knowledge.delete');

    // Estado local para reflejar cambios sin recargar la página entera.
    const [localBotActive, setLocalBotActive] = useState<Record<string, boolean>>({});
    const [localConverted, setLocalConverted] = useState<Record<string, boolean>>({});
    const [processingId, setProcessingId] = useState<string | null>(null);

    // ── Auto-refresh cada 15 s ──────────────────────────────────────────────
    const REFRESH_INTERVAL_MS = 15_000;
    const [secondsSince, setSecondsSince] = useState(0);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const tickRef    = useRef<ReturnType<typeof setInterval> | null>(null);

    const doRefresh = useCallback(() => {
        setIsRefreshing(true);
        router.reload({
            only: ['conversations', 'stats'],
            onFinish: () => {
                setIsRefreshing(false);
                setSecondsSince(0);
            },
        });
    }, []);

    useEffect(() => {
        intervalRef.current = setInterval(doRefresh, REFRESH_INTERVAL_MS);
        tickRef.current = setInterval(() => setSecondsSince((s) => s + 1), 1_000);
        return () => {
            if (intervalRef.current) clearInterval(intervalRef.current);
            if (tickRef.current) clearInterval(tickRef.current);
        };
    }, [doRefresh]);
    // ───────────────────────────────────────────────────────────────────────

    const getBotActive = (conv: Conversation): boolean =>
        conv.id in localBotActive ? localBotActive[conv.id] : conv.bot_active;

    const getConverted = (conv: Conversation): boolean =>
        conv.id in localConverted ? localConverted[conv.id] : conv.converted;

    const handleToggle = useCallback((conv: Conversation) => {
        const currentlyActive = getBotActive(conv);
        const url = currentlyActive
            ? salesbotConversations.pause(conv.id).url
            : salesbotConversations.resume(conv.id).url;

        setProcessingId(conv.id);
        csrfFetch(url, 'POST')
            .then((res) => {
                if (!res.ok) throw new Error();
                setLocalBotActive((prev) => ({ ...prev, [conv.id]: !currentlyActive }));
                toastManager.success({
                    title: currentlyActive
                        ? `Bot pausado para ${conv.prospect_name ?? conv.phone}`
                        : `Bot reactivado para ${conv.prospect_name ?? conv.phone}`,
                });
            })
            .catch(() => {
                toastManager.error({ title: 'Error al cambiar el estado del bot' });
            })
            .finally(() => setProcessingId(null));
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [localBotActive]);

    const handleConvert = useCallback((conv: Conversation) => {
        if (!confirm(`¿Marcar a ${conv.prospect_name ?? conv.phone} como convertido? Ya no recibirá mensajes de reactivación.`)) return;
        setProcessingId(conv.id);
        csrfFetch(salesbotConversations.convert(conv.id).url, 'POST')
            .then((res) => {
                if (!res.ok) throw new Error();
                setLocalConverted((prev) => ({ ...prev, [conv.id]: true }));
                setLocalBotActive((prev) => ({ ...prev, [conv.id]: false }));
                toastManager.success({ title: `✅ Lead convertido: ${conv.prospect_name ?? conv.phone}` });
            })
            .catch(() => toastManager.error({ title: 'Error al marcar como convertido' }))
            .finally(() => setProcessingId(null));
    }, []);

    const handleReactivate = useCallback((conv: Conversation) => {
        if (!confirm(`¿Enviar mensaje de reactivación a ${conv.prospect_name ?? conv.phone} ahora mismo?`)) return;
        setProcessingId(conv.id);
        csrfFetch(salesbotConversations.reactivate(conv.id).url, 'POST')
            .then(async (res) => {
                const data = await res.json();
                if (!res.ok) throw new Error(data?.error ?? 'Error');
                toastManager.success({
                    title: `Mensaje de reactivación enviado (intento #${data.reactivation_count})`,
                });
                doRefresh();
            })
            .catch((e: Error) => toastManager.error({ title: e.message || 'Error al reactivar' }))
            .finally(() => setProcessingId(null));
    }, [doRefresh]);

    const handleDelete = useCallback((conv: Conversation) => {
        if (!confirm(`¿Eliminar la conversación de ${conv.prospect_name ?? conv.phone}? El bot lo tratará como lead nuevo.`)) return;
        csrfFetch(salesbotConversations.destroy(conv.id).url, 'DELETE')
            .then((res) => {
                if (!res.ok) throw new Error();
                toastManager.success({ title: 'Conversación eliminada' });
                window.location.reload();
            })
            .catch(() => toastManager.error({ title: 'Error al eliminar' }));
    }, []);

    const estadoOptions: readonly FilterChip<EstadoFilter>[] = useMemo(
        () => [
            { value: 'todos',      label: 'Todos' },
            { value: 'activo',     label: 'Bot activo' },
            { value: 'pausado',    label: 'Pausado' },
            { value: 'frio',       label: '❄️ Fríos +3d' },
            { value: 'convertido', label: '✅ Convertidos' },
        ],
        [],
    );

    const { search, setSearch, isLoading, sort, setSort, setPerPage, applyFilter } =
        useDataTablePage<{ estado: EstadoFilter }>({
            routeUrl: salesbotConversations.index().url,
            initialFilters: filters,
            only: ['conversations', 'filters', 'stats'],
            errorMessage: 'Error al cargar las conversaciones',
            storageKey: 'vetsaas.plataforma.salesbot-conversations.prefs',
            defaults: { per_page: DEFAULT_PER_PAGE, sort: null, direction: null },
        });

    const activeFiltersCount = useMemo(() => {
        let n = 0;
        if (filters.search) n++;
        if (filters.estado !== DEFAULT_ESTADO) n++;
        if (filters.per_page !== DEFAULT_PER_PAGE) n++;
        return n;
    }, [filters.search, filters.estado, filters.per_page]);

    const columns = useMemo<DataTableColumn<Conversation>[]>(() => [
        {
            key: 'prospect_name',
            header: 'Lead',
            sortable: true,
            cell: (conv) => (
                <div className="flex items-center gap-2">
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <User className="size-4" strokeWidth={2.25} />
                    </span>
                    <div className="flex min-w-0 flex-col leading-tight">
                        <span className="truncate text-sm font-semibold text-foreground">
                            {conv.prospect_name ?? 'Sin nombre'}
                        </span>
                        <span className="truncate font-mono text-xs text-muted-foreground">
                            {formatPhone(conv.phone)}
                        </span>
                    </div>
                </div>
            ),
        },
        {
            key: 'last_message_at',
            header: 'Última actividad',
            sortable: true,
            cell: (conv) => (
                <div className="flex min-w-[120px] flex-col leading-tight">
                    <span className="text-xs font-medium text-foreground">
                        {timeAgo(conv.last_message_at)}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {formatWhen(conv.last_message_at)}
                    </span>
                </div>
            ),
        },
        {
            key: 'bot_active',
            header: 'Estado',
            cell: (conv) => {
                if (getConverted(conv)) {
                    return <StatBadge label="Convertido" value="" variant="success" />;
                }
                const active = getBotActive(conv);
                return active ? (
                    <StatBadge label="Bot activo" value="" variant="success" />
                ) : (
                    <StatBadge label="Pausado" value="" variant="warning" />
                );
            },
        },
        {
            key: 'reactivation_count',
            header: 'React.',
            cell: (conv) => (
                <div className="flex flex-col items-center leading-tight">
                    <span className="text-xs font-medium text-foreground">{conv.reactivation_count}/2</span>
                    {conv.last_reactivation_at && (
                        <span className="text-[10px] text-muted-foreground">
                            {timeAgo(conv.last_reactivation_at)}
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'last_message_body',
            header: 'Último mensaje',
            cell: (conv) => (
                <div className="flex max-w-xs flex-col leading-tight">
                    <span className="line-clamp-2 text-xs text-foreground/80">
                        {conv.last_message_body ?? '—'}
                    </span>
                </div>
            ),
        },
        {
            key: 'turn_count',
            header: 'Turnos',
            cell: (conv) => (
                <span className="text-xs text-muted-foreground">{conv.turn_count}</span>
            ),
        },
        {
            key: 'acciones',
            header: <span className="md:sr-only">Acciones</span>,
            align: 'right',
            className: 'w-40',
            cell: (conv) => {
                const active   = getBotActive(conv);
                const converted = getConverted(conv);
                const loading  = processingId === conv.id;
                const canReactivate = !converted && (conv.reactivation_count < 2);
                return (
                    <div className="flex items-center justify-end gap-1">
                        {canUpdate && !converted && (
                            <Button
                                type="button"
                                size="sm"
                                variant={active ? 'outline' : 'default'}
                                disabled={loading}
                                onClick={() => handleToggle(conv)}
                                className="cursor-pointer gap-1.5 text-xs"
                            >
                                {active ? (
                                    <>
                                        <PauseCircle className="size-3.5" strokeWidth={2.5} />
                                        <span className="hidden sm:inline">Pausar</span>
                                    </>
                                ) : (
                                    <>
                                        <PlayCircle className="size-3.5" strokeWidth={2.5} />
                                        <span className="hidden sm:inline">Reanudar</span>
                                    </>
                                )}
                            </Button>
                        )}
                        {canUpdate && canReactivate && (
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                disabled={loading}
                                onClick={() => handleReactivate(conv)}
                                title="Enviar mensaje de reactivación ahora"
                                className="size-8 cursor-pointer text-blue-500 hover:text-blue-600"
                            >
                                <SendHorizonal className="size-4" strokeWidth={2.5} />
                            </Button>
                        )}
                        {canUpdate && !converted && (
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                disabled={loading}
                                onClick={() => handleConvert(conv)}
                                title="Marcar como convertido (cerrado)"
                                className="size-8 cursor-pointer text-emerald-500 hover:text-emerald-600"
                            >
                                <CheckCircle2 className="size-4" strokeWidth={2.5} />
                            </Button>
                        )}
                        {canDelete && (
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                onClick={() => handleDelete(conv)}
                                className="size-8 cursor-pointer text-destructive hover:text-destructive"
                            >
                                <Trash2 className="size-4" strokeWidth={2.5} />
                            </Button>
                        )}
                    </div>
                );
            },
        },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    ], [canUpdate, canDelete, processingId, localBotActive, localConverted, handleToggle, handleReactivate, handleConvert, handleDelete]);

    return (
        <>
            <Head title="Conversaciones del bot" />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title="Conversaciones del bot"
                    description={
                        <span className="flex items-center gap-3">
                            <span>Leads que el bot ha atendido. Pausa el bot por lead para tomar el control manual desde WhatsApp.</span>
                            <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <span
                                    className={`inline-block size-2 rounded-full ${isRefreshing ? 'animate-ping bg-amber-400' : 'bg-emerald-400'}`}
                                />
                                {isRefreshing
                                    ? 'Actualizando…'
                                    : `Actualizado hace ${secondsSince}s`}
                                <button
                                    type="button"
                                    onClick={doRefresh}
                                    disabled={isRefreshing}
                                    className="ml-1 cursor-pointer rounded p-0.5 hover:text-foreground disabled:opacity-50"
                                    title="Actualizar ahora"
                                >
                                    <RefreshCw className={`size-3 ${isRefreshing ? 'animate-spin' : ''}`} />
                                </button>
                            </span>
                        </span>
                    }
                    stats={[
                        { label: 'Total', value: stats.total, variant: 'info', icon: MessageCircle },
                        { label: 'Bot activo', value: stats.activos, variant: 'success', icon: Bot },
                        { label: 'Pausados', value: stats.pausados, variant: 'warning', icon: PauseCircle },
                        { label: 'Fríos', value: stats.frios, variant: 'warning', icon: Snowflake },
                        { label: 'Convertidos', value: stats.convertidos, variant: 'success', icon: CheckCircle2 },
                        { label: 'Hoy', value: stats.hoy, variant: 'primary', icon: CalendarDays },
                        { label: 'Filtros', value: activeFiltersCount, variant: 'warning', icon: Filter },
                        { label: 'Coincidencias', value: stats.coincidencias, variant: 'primary', icon: Activity },
                    ]}
                />

                <DataTable
                    columns={columns}
                    data={conversations.data}
                    rowKey={(c) => c.id}
                    sort={sort}
                    onSortChange={setSort}
                    isLoading={isLoading}
                    ariaLiveMessage={`${stats.coincidencias} conversaciones`}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder="Buscar por nombre o teléfono..."
                        >
                            <FilterChips
                                ariaLabel="Filtrar por estado del bot"
                                value={filters.estado}
                                onChange={(estado) => applyFilter({ estado })}
                                options={estadoOptions}
                            />
                        </DataToolbar>
                    }
                    footer={
                        <DataPagination
                            meta={conversations}
                            onPerPageChange={setPerPage}
                            preservedQuery={{
                                search: filters.search || undefined,
                                per_page: filters.per_page,
                                estado: filters.estado !== DEFAULT_ESTADO ? filters.estado : undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={MessageCircle}
                            title="Sin conversaciones todavía"
                            description="Cuando un prospecto escriba al número de Orvae con palabras clave de VetSaaS, aparecerá aquí."
                        />
                    }
                />
            </div>
        </>
    );
}

SalesBotConversationsIndex.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma' },
            { title: 'Conversaciones bot', href: '/plataforma/salesbot-conversations' },
        ]}
    >
        {page}
    </AppLayout>
);
