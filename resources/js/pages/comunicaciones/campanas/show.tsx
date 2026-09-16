import { Head, Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    Clock,
    History,
    Pause,
    Phone,
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
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import { formatWhatsAppPhone } from '@/lib/format-whatsapp-phone';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';
import type { WhatsAppProps } from '../components/whatsapp-connect-card';
import { DestinatariosPickerModal } from './components/destinatarios-picker-modal';

const ROUTE_URL = '/comunicaciones/campanas';

type EstadoLote = 'todos' | 'pendiente' | 'enviado' | 'fallido' | 'omitido';

type Campana = {
    id: string;
    nombre: string;
    tope_diario: number;
    intervalo_minutos: number;
    hora_inicio: string;
    hora_fin: string;
    estado: string;
    pacing_hint?: { code: string; label: string };
};

type LoteRow = {
    id: string;
    nombre_snapshot: string;
    telefono_normalizado: string;
    mascota_nombres: string | null;
    estado: string;
    cuerpo_enviado: string | null;
    error: string | null;
    enviado_at: string | null;
};

type Props = {
    campana: Campana;
    lote: Paginated<LoteRow>;
    stats: {
        pendiente: number;
        enviado: number;
        fallido: number;
        enviados_hoy: number;
    };
    filters: {
        search: string;
        estado: string | null;
        per_page: number;
    };
    whatsapp: WhatsAppProps;
};

function formatWhen(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString('es-PE', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

export default function CampanaShow({
    campana,
    lote,
    stats,
    filters,
    whatsapp,
}: Props) {
    const { t } = useTranslation(['comunicaciones', 'common']);
    const { can } = usePermission();
    const canUpdate = can('comunicaciones-campanas.update');
    const canManage = can('comunicaciones-campanas.manage');
    const whatsappReady = Boolean(whatsapp.session?.is_ready);
    const [pickerOpen, setPickerOpen] = useState(false);
    const routeUrl = `${ROUTE_URL}/${campana.id}`;
    const estadoFilter = (filters.estado ?? 'todos') as EstadoLote;

    const { search, setSearch, isLoading, applyFilter } = useDataTablePage<{
        estado: EstadoLote;
    }>({
        routeUrl,
        initialFilters: {
            search: filters.search,
            estado: estadoFilter,
            per_page: filters.per_page,
            sort: null,
            direction: null,
        },
        only: ['lote', 'stats', 'filters', 'campana', 'whatsapp'],
    });

    useEffect(() => {
        if (campana.estado !== 'enviando') {
            return;
        }

        const timer = window.setInterval(() => {
            router.reload({
                only: ['lote', 'stats', 'campana', 'whatsapp'],
            });
        }, 20000);

        return () => window.clearInterval(timer);
    }, [campana.estado, campana.id]);

    const estadoOptions: readonly FilterChip<EstadoLote>[] = useMemo(
        () => [
            { value: 'todos', label: 'Todos' },
            { value: 'enviado', label: t('campanas.estado.enviado') },
            { value: 'pendiente', label: t('campanas.estado.pendiente') },
            { value: 'fallido', label: t('campanas.estado.fallido') },
            { value: 'omitido', label: t('campanas.estado.omitido') },
        ],
        [t],
    );

    const columns = useMemo<DataTableColumn<LoteRow>[]>(
        () => [
            {
                key: 'nombre',
                header: t('campanas.columns.destinatario'),
                cell: (row) => (
                    <div className="flex min-w-0 flex-col leading-tight">
                        <span className="truncate text-sm font-semibold">{row.nombre_snapshot}</span>
                        <span className="truncate text-xs text-muted-foreground">
                            {row.mascota_nombres ?? '—'}
                        </span>
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
                key: 'mensaje',
                header: 'Mensaje',
                cell: (row) => (
                    <div className="max-w-sm">
                        {row.cuerpo_enviado ? (
                            <p className="line-clamp-2 text-xs text-foreground/80">{row.cuerpo_enviado}</p>
                        ) : (
                            <span className="text-xs text-muted-foreground">Aún no sale</span>
                        )}
                        {row.error ? (
                            <p className="mt-1 flex items-start gap-1 text-xs text-destructive">
                                <AlertCircle className="mt-0.5 size-3 shrink-0" />
                                {row.error}
                            </p>
                        ) : null}
                    </div>
                ),
            },
            {
                key: 'estado',
                header: t('campanas.columns.estado'),
                cell: (row) =>
                    row.estado === 'enviado' ? (
                        <span className="flex flex-col text-xs text-emerald-700">
                            <span className="flex items-center gap-1 font-medium">
                                <CheckCircle2 className="size-3.5" />
                                {t('campanas.estado.enviado')}
                            </span>
                            <span className="text-muted-foreground">{formatWhen(row.enviado_at)}</span>
                        </span>
                    ) : (
                        <StatBadge
                            label={t(`campanas.estado.${row.estado}`)}
                            value=""
                            variant={row.estado === 'fallido' ? 'danger' : 'warning'}
                        />
                    ),
            },
        ],
        [t],
    );

    const canStart =
        canManage &&
        (campana.estado === 'borrador' || campana.estado === 'pausada');
    const sendBlocked = !whatsappReady || stats.pendiente === 0;
    const hint = campana.pacing_hint;

    return (
        <>
            <Head title={campana.nombre} />
            <div className="flex flex-1 flex-col gap-3 p-4 sm:p-6">
                <PageHeader
                    title={campana.nombre}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                <Clock className="size-3.5" />
                                {String(campana.hora_inicio).slice(0, 5)}–
                                {String(campana.hora_fin).slice(0, 5)} · cada {campana.intervalo_minutos} min
                            </span>
                            {whatsappReady ? (
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                                    <span className="size-1.5 rounded-full bg-emerald-500" />
                                    WhatsApp listo
                                </span>
                            ) : (
                                <Link
                                    href="/comunicaciones/cola"
                                    className="inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 px-2.5 py-0.5 text-xs font-medium text-amber-800"
                                >
                                    WhatsApp desconectado
                                </Link>
                            )}
                            {hint && campana.estado === 'enviando' ? (
                                <span
                                    className={cn(
                                        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        hint.code === 'window'
                                            ? 'bg-amber-500/10 text-amber-800'
                                            : 'bg-sky-500/10 text-sky-800',
                                    )}
                                >
                                    {hint.label}
                                </span>
                            ) : null}
                        </span>
                    }
                    stats={[
                        { label: t('campanas.stats.pendiente'), value: stats.pendiente, variant: 'warning' },
                        { label: t('campanas.stats.enviado'), value: stats.enviado, variant: 'success' },
                        { label: t('campanas.stats.fallido'), value: stats.fallido, variant: stats.fallido > 0 ? 'danger' : 'muted' },
                        { label: 'Hoy', value: stats.enviados_hoy, variant: 'info' },
                    ]}
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link href={ROUTE_URL}>{t('common:actions.back')}</Link>
                            </Button>
                            {canUpdate && campana.estado !== 'terminada' ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    className="cursor-pointer gap-1.5 bg-sky-600 text-white hover:bg-sky-700"
                                    onClick={() => setPickerOpen(true)}
                                >
                                    <Users className="size-3.5" />
                                    Destinatarios
                                </Button>
                            ) : null}
                            {canStart ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    className={cn(
                                        'cursor-pointer gap-1.5',
                                        sendBlocked
                                            ? 'bg-muted text-muted-foreground'
                                            : 'bg-emerald-600 text-white hover:bg-emerald-700',
                                    )}
                                    disabled={sendBlocked}
                                    onClick={() => {
                                        if (!window.confirm(t('campanas.start_confirm'))) {
                                            return;
                                        }
                                        router.post(`${routeUrl}/start`, {}, { preserveScroll: true });
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
                                    onClick={() =>
                                        router.post(`${routeUrl}/pause`, {}, { preserveScroll: true })
                                    }
                                >
                                    <Pause className="size-3.5" />
                                    {t('campanas.pause')}
                                </Button>
                            ) : null}
                        </div>
                    }
                />

                <DataTable
                    columns={columns}
                    data={lote.data}
                    rowKey={(row) => row.id}
                    isLoading={isLoading}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            placeholder={t('campanas.search_placeholder')}
                            isSearching={isLoading}
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
                            icon={History}
                            title="Sin movimientos aún"
                            description="Cuando salga un mensaje vas a ver acá el texto, la hora y si llegó o falló."
                        />
                    }
                    footer={
                        <DataPagination
                            meta={lote}
                            preservedQuery={{
                                search: filters.search || undefined,
                                estado: filters.estado ?? undefined,
                                per_page: filters.per_page,
                            }}
                        />
                    }
                />
            </div>

            <DestinatariosPickerModal
                open={pickerOpen}
                campanaId={pickerOpen ? campana.id : null}
                campanaNombre={campana.nombre}
                onOpenChange={setPickerOpen}
            />
        </>
    );
}

CampanaShow.layout = {
    breadcrumbs: [
        { title: 'Comunicaciones', href: '#' },
        { title: 'Campañas', href: ROUTE_URL },
    ],
};
