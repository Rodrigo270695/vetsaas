import { Head } from '@inertiajs/react';
import {
    Activity,
    CheckCircle2,
    Clock3,
    DollarSign,
    Download,
    Filter,
    Receipt,
    ScreenShare,
    Undo2,
    Wallet,
    XCircle,
} from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
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
import type {
    DataTableColumn,
    FilterChip,
} from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { useDataTablePage } from '@/hooks/use-data-table-page';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import cobros from '@/routes/plataforma/cobros';
import type { Paginated } from '@/types';
import { PaymentDetailModal } from './components/payment-detail-modal';
import { PaymentNoteDialog } from './components/payment-note-dialog';
import { PaymentRefundDialog } from './components/payment-refund-dialog';
import { PaymentResendInvoiceDialog } from './components/payment-resend-invoice-dialog';
import { PaymentRowActions } from './components/payment-row-actions';
import type {
    PaymentEstadoFilter,
    PaymentFilters,
    PaymentStats,
    SubscriptionPayment,
} from './types';

type CobrosIndexProps = {
    payments: Paginated<SubscriptionPayment>;
    filters: PaymentFilters;
    stats: PaymentStats;
};

/**
 * State machine de modales del módulo Cobros.
 *
 * NO hay create/edit/delete: la fila base es inmutable (proviene del
 * webhook de Orvae). Solo acciones de soporte.
 */
type ModalState =
    | { type: 'idle' }
    | { type: 'detail'; payment: SubscriptionPayment }
    | { type: 'refund'; payment: SubscriptionPayment }
    | { type: 'note'; payment: SubscriptionPayment }
    | { type: 'resend'; payment: SubscriptionPayment };

const DEFAULT_PER_PAGE = 10;
const DEFAULT_ESTADO: PaymentEstadoFilter = 'todos';

const formatPrice = (value: string | number): string => {
    const num = typeof value === 'string' ? Number(value) : value;
    if (Number.isNaN(num)) return '—';
    return `S/. ${num.toFixed(2)}`;
};

const formatDate = (value: string | null): string => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleDateString('es-PE', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const formatDateTime = (value: string | null): string => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString('es-PE', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
};

/**
 * Badge del estado del pago. Centralizado para que toda la página
 * use los mismos colores.
 */
function renderEstadoBadge(
    estado: SubscriptionPayment['estado'],
    t: (key: string) => string,
) {
    const label = t(`cobros:estados.${estado}`);
    const variant: Parameters<typeof StatBadge>[0]['variant'] =
        estado === 'procesado'
            ? 'success'
            : estado === 'pendiente'
              ? 'info'
              : estado === 'fallido'
                ? 'danger'
                : 'muted';
    return <StatBadge label={label} value="" variant={variant} />;
}

/**
 * Página principal del módulo Plataforma → Cobros (superadmin).
 *
 * Read-only sobre `subscription_payments` + acciones humanas:
 *   - Ver detalle completo (incluye JSON crudo del webhook).
 *   - Marcar reembolso manual (con razón).
 *   - Agregar/editar nota interna.
 *   - Reenviar factura electrónica (cuando exista FEL).
 *
 * No tiene create/edit/delete/bulk-delete porque la fila base la
 * escribe Orvae vía webhook y es inmutable.
 */
export default function Index({
    payments: paginated,
    filters,
    stats,
}: CobrosIndexProps) {
    const { t } = useTranslation(['cobros', 'common']);
    const { can } = usePermission();
    const canExport = can('plataforma-cobros.export');
    const canRefund = can('plataforma-cobros.refund');
    const canAddNote = can('plataforma-cobros.add-note');
    const canResend = can('plataforma-cobros.resend-invoice');
    const showRowActions = true;

    const {
        search,
        setSearch,
        isLoading,
        sort,
        setSort,
        setPerPage,
        applyFilter,
    } = useDataTablePage<{ estado: PaymentEstadoFilter }>({
        routeUrl: cobros.index().url,
        initialFilters: filters,
        only: ['payments', 'filters', 'stats'],
        errorMessage: t('toast.load_error'),
        storageKey: 'vetsaas.plataforma.cobros.prefs',
        defaults: {
            per_page: DEFAULT_PER_PAGE,
            sort: null,
            direction: null,
        },
    });

    const estadoOptions: readonly FilterChip<PaymentEstadoFilter>[] = useMemo(
        () => [
            { value: 'todos', label: t('cobros:filters.all') },
            { value: 'procesado', label: t('cobros:filters.procesado') },
            { value: 'pendiente', label: t('cobros:filters.pendiente') },
            { value: 'fallido', label: t('cobros:filters.fallido') },
            { value: 'reembolsado', label: t('cobros:filters.reembolsado') },
        ],
        [t],
    );

    const [modal, setModal] = useState<ModalState>({ type: 'idle' });

    const closeModal = useCallback(() => setModal({ type: 'idle' }), []);
    const openDetail = useCallback(
        (payment: SubscriptionPayment) =>
            setModal({ type: 'detail', payment }),
        [],
    );
    const openRefund = useCallback(
        (payment: SubscriptionPayment) =>
            setModal({ type: 'refund', payment }),
        [],
    );
    const openNote = useCallback(
        (payment: SubscriptionPayment) =>
            setModal({ type: 'note', payment }),
        [],
    );
    const openResend = useCallback(
        (payment: SubscriptionPayment) =>
            setModal({ type: 'resend', payment }),
        [],
    );

    const activeFiltersCount = useMemo(() => {
        let count = 0;
        if (filters.search) count += 1;
        if (filters.sort) count += 1;
        if (filters.estado !== DEFAULT_ESTADO) count += 1;
        if (filters.subscription_id) count += 1;
        if (filters.tenant_id) count += 1;
        if (filters.per_page !== DEFAULT_PER_PAGE) count += 1;
        return count;
    }, [
        filters.search,
        filters.sort,
        filters.estado,
        filters.subscription_id,
        filters.tenant_id,
        filters.per_page,
    ]);

    const exportUrl = useMemo(() => {
        const params = new URLSearchParams();
        if (filters.search) params.set('search', filters.search);
        if (filters.sort) params.set('sort', filters.sort);
        if (filters.direction) params.set('direction', filters.direction);
        if (filters.estado !== DEFAULT_ESTADO)
            params.set('estado', filters.estado);
        if (filters.subscription_id)
            params.set('subscription_id', filters.subscription_id);
        if (filters.tenant_id) params.set('tenant_id', filters.tenant_id);

        const qs = params.toString();
        return qs.length > 0
            ? `${cobros.export().url}?${qs}`
            : cobros.export().url;
    }, [
        filters.search,
        filters.sort,
        filters.direction,
        filters.estado,
        filters.subscription_id,
        filters.tenant_id,
    ]);

    const columns = useMemo<DataTableColumn<SubscriptionPayment>[]>(() => {
        const base: DataTableColumn<SubscriptionPayment>[] = [
            {
                key: 'pagado_at',
                header: t('cobros:columns.fecha'),
                sortable: true,
                cell: (p) => (
                    <div className="flex flex-col leading-tight">
                        <span className="text-xs font-medium">
                            {formatDate(p.pagado_at ?? p.created_at)}
                        </span>
                        <span className="text-[10px] text-muted-foreground">
                            {formatDateTime(p.pagado_at ?? p.created_at)}
                        </span>
                    </div>
                ),
            },
            {
                key: 'tenant',
                header: t('cobros:columns.tenant'),
                cell: (p) => (
                    <div className="flex min-w-0 flex-col leading-tight">
                        <span className="truncate text-sm font-semibold text-foreground">
                            {p.tenant?.razon_social ?? '—'}
                        </span>
                        <span className="truncate font-mono text-xs text-muted-foreground">
                            {p.tenant?.slug ?? '—'}
                        </span>
                    </div>
                ),
            },
            {
                key: 'plan',
                header: t('cobros:columns.plan'),
                cell: (p) => {
                    if (!p.plan) {
                        return (
                            <span className="text-xs text-muted-foreground italic">
                                —
                            </span>
                        );
                    }
                    const hex =
                        p.plan.color_hex &&
                        /^#[0-9a-fA-F]{3,6}$/.test(p.plan.color_hex)
                            ? p.plan.color_hex
                            : '#1F6E4A';
                    return (
                        <div className="flex items-center gap-1.5">
                            <span
                                className="size-2 shrink-0 rounded-full"
                                style={{ backgroundColor: hex }}
                            />
                            <span className="text-sm">{p.plan.nombre}</span>
                        </div>
                    );
                },
            },
            {
                key: 'total',
                header: t('cobros:columns.total'),
                sortable: true,
                align: 'right',
                cell: (p) => (
                    <div className="flex flex-col items-end leading-tight">
                        <span className="font-mono text-sm font-semibold tabular-nums">
                            {formatPrice(p.total)}
                        </span>
                        {Number(p.descuento_monto) > 0 && (
                            <span className="font-mono text-[10px] text-emerald-600 dark:text-emerald-400">
                                −{formatPrice(p.descuento_monto)}
                            </span>
                        )}
                    </div>
                ),
            },
            {
                key: 'estado',
                header: t('cobros:columns.estado'),
                sortable: true,
                cell: (p) => renderEstadoBadge(p.estado, t),
            },
            {
                key: 'pasarela',
                header: t('cobros:columns.pasarela'),
                sortable: true,
                cell: (p) => (
                    <div className="flex flex-col leading-tight">
                        <span className="text-xs font-medium">
                            {p.pasarela ?? '—'}
                        </span>
                        {p.pasarela_transaction_id && (
                            <span className="truncate font-mono text-[10px] text-muted-foreground">
                                {p.pasarela_transaction_id}
                            </span>
                        )}
                    </div>
                ),
            },
            {
                key: 'fel',
                header: t('cobros:columns.factura'),
                cell: (p) =>
                    p.fel_emitido && p.fel_numero ? (
                        <span className="font-mono text-xs">{p.fel_numero}</span>
                    ) : (
                        <span className="text-xs text-muted-foreground italic">
                            {t('cobros:row.no_invoice')}
                        </span>
                    ),
            },
        ];

        if (showRowActions) {
            base.push({
                key: 'acciones',
                header: (
                    <span className="md:sr-only">
                        {t('cobros:columns.acciones')}
                    </span>
                ),
                align: 'right',
                cell: (p: SubscriptionPayment) => (
                    <div className="flex justify-end">
                        <PaymentRowActions
                            payment={p}
                            onViewDetail={openDetail}
                            onAddNote={openNote}
                            onMarkRefunded={openRefund}
                            onResendInvoice={openResend}
                            canAddNote={canAddNote}
                            canRefund={canRefund}
                            canResend={canResend}
                        />
                    </div>
                ),
                className: 'w-12',
            });
        }

        return base;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        t,
        showRowActions,
        canAddNote,
        canRefund,
        canResend,
        openDetail,
        openNote,
        openRefund,
        openResend,
    ]);

    return (
        <>
            <Head title={t('cobros:title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('cobros:title')}
                    description={t('cobros:description')}
                    stats={[
                        {
                            label: t('cobros:stats.total'),
                            value: stats.total,
                            variant: 'info',
                            icon: Receipt,
                        },
                        {
                            label: t('cobros:stats.procesado'),
                            value: stats.procesado,
                            variant: 'success',
                            icon: CheckCircle2,
                        },
                        {
                            label: t('cobros:stats.pendiente'),
                            value: stats.pendiente,
                            variant: 'info',
                            icon: Clock3,
                        },
                        {
                            label: t('cobros:stats.fallido'),
                            value: stats.fallido,
                            variant: 'danger',
                            icon: XCircle,
                        },
                        {
                            label: t('cobros:stats.reembolsado'),
                            value: stats.reembolsado,
                            variant: 'muted',
                            icon: Undo2,
                        },
                        {
                            label: t('cobros:stats.cobrado_total'),
                            value: formatPrice(stats.cobrado_total),
                            variant: 'primary',
                            icon: DollarSign,
                        },
                        {
                            label: t('cobros:stats.filters'),
                            value: activeFiltersCount,
                            variant: 'warning',
                            icon: Filter,
                        },
                        {
                            label: t('cobros:stats.matches'),
                            value: stats.coincidencias,
                            variant: 'primary',
                            icon: ScreenShare,
                        },
                    ]}
                    action={
                        <div className="flex flex-row items-center gap-2">
                            {canExport && (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="cursor-pointer gap-2"
                                >
                                    <a href={exportUrl} download>
                                        <Download
                                            className="size-4"
                                            strokeWidth={2.5}
                                        />
                                        <span className="hidden sm:inline">
                                            {t('common:actions.export_xlsx')}
                                        </span>
                                    </a>
                                </Button>
                            )}
                        </div>
                    }
                />

                <DataTable
                    columns={columns}
                    data={paginated.data}
                    rowKey={(p) => p.id}
                    sort={sort}
                    onSortChange={setSort}
                    isLoading={isLoading}
                    ariaLiveMessage={t('cobros:aria.results_count_other', {
                        count: stats.coincidencias,
                    })}
                    toolbar={
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSearching={isLoading}
                            placeholder={t('cobros:search_placeholder')}
                        >
                            <FilterChips
                                ariaLabel={t('cobros:filter_label')}
                                value={filters.estado}
                                onChange={(estado) => applyFilter({ estado })}
                                options={estadoOptions}
                            />
                        </DataToolbar>
                    }
                    footer={
                        <DataPagination
                            meta={paginated}
                            onPerPageChange={setPerPage}
                            preservedQuery={{
                                search: filters.search || undefined,
                                per_page: filters.per_page,
                                sort: filters.sort ?? undefined,
                                direction: filters.direction ?? undefined,
                                estado:
                                    filters.estado !== DEFAULT_ESTADO
                                        ? filters.estado
                                        : undefined,
                                subscription_id:
                                    filters.subscription_id ?? undefined,
                                tenant_id: filters.tenant_id ?? undefined,
                            }}
                        />
                    }
                    emptyState={
                        <EmptyState
                            icon={
                                activeFiltersCount > 0 ? Activity : Wallet
                            }
                            title={
                                activeFiltersCount > 0
                                    ? t('cobros:empty.no_results_title')
                                    : t('cobros:empty.no_records_title')
                            }
                            description={
                                activeFiltersCount > 0
                                    ? t('cobros:empty.no_results_description')
                                    : t('cobros:empty.no_records_description')
                            }
                        />
                    }
                />
            </div>

            <PaymentDetailModal
                open={modal.type === 'detail'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                payment={modal.type === 'detail' ? modal.payment : null}
            />

            <PaymentRefundDialog
                open={modal.type === 'refund'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                payment={modal.type === 'refund' ? modal.payment : null}
            />

            <PaymentNoteDialog
                open={modal.type === 'note'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                payment={modal.type === 'note' ? modal.payment : null}
            />

            <PaymentResendInvoiceDialog
                open={modal.type === 'resend'}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                payment={modal.type === 'resend' ? modal.payment : null}
            />
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma' },
            { title: 'Cobros', href: '/plataforma/cobros' },
        ]}
    >
        {page}
    </AppLayout>
);
