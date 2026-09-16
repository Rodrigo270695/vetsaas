import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircle2,
    Megaphone,
    Pause,
    Phone,
    Send,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
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
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import { formatWhatsAppPhone } from '@/lib/format-whatsapp-phone';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';
import type { WhatsAppProps } from '../components/whatsapp-connect-card';
import { DestinatariosPickerModal } from './components/destinatarios-picker-modal';

const ROUTE_URL = '/comunicaciones/campanas';

type Campana = {
    id: string;
    nombre: string;
    tope_diario: number;
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
        per_page: number;
    };
    whatsapp: WhatsAppProps;
};

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

    const { search, setSearch, isLoading } = useDataTablePage({
        routeUrl,
        initialFilters: {
            search: filters.search,
            per_page: filters.per_page,
            sort: null,
            direction: null,
        },
        only: ['lote', 'stats', 'filters', 'campana', 'whatsapp'],
    });

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
        ],
        [t],
    );

    const canStart =
        canManage &&
        (campana.estado === 'borrador' || campana.estado === 'pausada');
    const sendBlocked = !whatsappReady || stats.pendiente === 0;

    return (
        <>
            <Head title={campana.nombre} />
            <div className="flex flex-1 flex-col gap-3 p-4 sm:p-6">
                <PageHeader
                    title={campana.nombre}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <span>{t('campanas.only_mobile')}</span>
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
                        </span>
                    }
                    stats={[
                        { label: t('campanas.stats.pendiente'), value: stats.pendiente, variant: 'warning' },
                        { label: t('campanas.stats.enviado'), value: stats.enviado, variant: 'success' },
                        { label: t('campanas.stats.fallido'), value: stats.fallido, variant: stats.fallido > 0 ? 'danger' : 'muted' },
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
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={Megaphone}
                            title="Sin destinatarios aún"
                            description="Usá Destinatarios para marcar dueños con celular válido."
                        />
                    }
                    footer={
                        <DataPagination
                            meta={lote}
                            preservedQuery={{
                                search: filters.search || undefined,
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
