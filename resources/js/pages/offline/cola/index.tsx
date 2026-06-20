import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    CloudOff,
    Eye,
    Inbox,
    RefreshCw,
    RotateCcw,
    Trash2,
    Wifi,
} from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState, PageHeader, StatBadge, DataTable } from '@/components/data-page';
import type { DataTableColumn } from '@/components/data-page';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useOfflineSync } from '@/hooks/use-offline-sync';
import { useOutboxQueue } from '@/hooks/use-outbox-queue';
import AppLayout from '@/layouts/app-layout';
import { isIndexedDbSupported } from '@/lib/offline/idb';
import {
    formatOutboxPayload,
    outboxPayloadSummary,
    outboxTypeI18nKey,
    outboxTypeOptions,
} from '@/lib/offline/outbox-presenter';
import { discardOutboxItem, retryOutboxItem } from '@/lib/offline/outbox';
import { syncSingleOutboxItem } from '@/lib/offline/sync-engine';
import { toastManager } from '@/lib/toast';
import type { OutboxItem, OutboxStatus, OutboxType } from '@/lib/offline/types';
import type { BreadcrumbItem } from '@/types';

const ROUTE_URL = '/offline/cola';

type StatusFilter = 'all' | OutboxStatus;

export default function OfflineColaIndex() {
    const { t, i18n } = useTranslation(['offline', 'common']);
    const { isOnline, isSyncing, syncNow, refreshPending } = useOfflineSync();
    const { items, summary, supported, loading, refresh } = useOutboxQueue();
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');
    const [typeFilter, setTypeFilter] = useState<'all' | OutboxType>('all');
    const [detailItem, setDetailItem] = useState<OutboxItem | null>(null);
    const [actingUuid, setActingUuid] = useState<string | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('sync_center.breadcrumb_root'), href: ROUTE_URL },
        { title: t('sync_center.title'), href: ROUTE_URL },
    ];

    const filteredItems = useMemo(() => {
        return items.filter((item) => {
            if (statusFilter !== 'all' && item.status !== statusFilter) {
                return false;
            }

            if (typeFilter !== 'all' && item.type !== typeFilter) {
                return false;
            }

            return true;
        });
    }, [items, statusFilter, typeFilter]);

    const formatDate = useCallback(
        (iso: string) =>
            new Date(iso).toLocaleString(i18n.language, {
                dateStyle: 'short',
                timeStyle: 'short',
            }),
        [i18n.language],
    );

    const handleSyncAll = async () => {
        await syncNow();
        await refresh();
    };

    const handleRetry = useCallback(
        async (item: OutboxItem) => {
            if (!isOnline) {
                toastManager.warning({
                    title: t('sync_center.toast.offline_title'),
                    description: t('sync_center.toast.offline_body'),
                });

                return;
            }

            setActingUuid(item.uuid);

            try {
                if (item.status === 'failed') {
                    await retryOutboxItem(item.uuid);
                }

                const result = await syncSingleOutboxItem(item.uuid);
                await refreshPending();
                await refresh();

                if (result === 'synced') {
                    toastManager.success({
                        title: t('sync_center.toast.synced_title'),
                        description: t('sync_center.toast.synced_body', {
                            label: item.local_label ?? item.uuid.slice(0, 8),
                        }),
                    });
                } else if (result === 'offline') {
                    toastManager.warning({
                        title: t('sync_center.toast.offline_title'),
                        description: t('sync_center.toast.offline_body'),
                    });
                } else if (result === 'busy') {
                    toastManager.warning({
                        title: t('sync_center.toast.busy_title'),
                        description: t('sync_center.toast.busy_body'),
                    });
                }
            } finally {
                setActingUuid(null);
            }
        },
        [isOnline, refresh, refreshPending, t],
    );

    const handleDiscard = useCallback(
        async (item: OutboxItem) => {
            const confirmed = window.confirm(
                t('sync_center.discard_confirm', {
                    label: item.local_label ?? item.uuid.slice(0, 8),
                }),
            );

            if (!confirmed) {
                return;
            }

            setActingUuid(item.uuid);

            try {
                await discardOutboxItem(item.uuid);
                await refreshPending();
                await refresh();
                toastManager.success({
                    title: t('sync_center.toast.discarded_title'),
                    description: t('sync_center.toast.discarded_body'),
                });

                setDetailItem((current) => (current?.uuid === item.uuid ? null : current));
            } finally {
                setActingUuid(null);
            }
        },
        [refresh, refreshPending, t],
    );

    const columns = useMemo((): DataTableColumn<OutboxItem>[] => {
        return [
            {
                key: 'reference',
                header: t('sync_center.table.reference'),
                cell: (item) => (
                    <span className="font-mono text-xs">
                        {item.local_label ?? item.uuid.slice(0, 8)}
                    </span>
                ),
            },
            {
                key: 'type',
                header: t('sync_center.table.type'),
                cell: (item) => t(outboxTypeI18nKey(item.type)),
            },
            {
                key: 'summary',
                header: t('sync_center.table.summary'),
                cell: (item) => (
                    <span className="max-w-[220px] truncate text-muted-foreground">
                        {outboxPayloadSummary(item)}
                    </span>
                ),
            },
            {
                key: 'status',
                header: t('sync_center.table.status'),
                cell: (item) => (
                    <div>
                        <Badge variant={item.status === 'failed' ? 'destructive' : 'secondary'}>
                            {t(`sync_center.status.${item.status}`)}
                        </Badge>
                        {item.error && (
                            <p className="mt-1 max-w-[220px] truncate text-xs text-destructive">
                                {item.error}
                            </p>
                        )}
                    </div>
                ),
            },
            {
                key: 'created',
                header: t('sync_center.table.created'),
                cell: (item) => (
                    <span className="whitespace-nowrap text-sm text-muted-foreground">
                        {formatDate(item.created_at)}
                    </span>
                ),
            },
            {
                key: 'actions',
                header: t('sync_center.table.actions'),
                align: 'right',
                cell: (item) => {
                    const busy = actingUuid === item.uuid || item.status === 'syncing';

                    return (
                        <div className="flex justify-end gap-1">
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                title={t('sync_center.actions.detail')}
                                onClick={() => setDetailItem(item)}
                            >
                                <Eye className="size-4" />
                            </Button>
                            {(item.status === 'pending' || item.status === 'failed') && (
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    disabled={busy || !isOnline}
                                    title={t('sync_center.actions.retry')}
                                    onClick={() => void handleRetry(item)}
                                >
                                    <RotateCcw className={`size-4 ${busy ? 'animate-spin' : ''}`} />
                                </Button>
                            )}
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                disabled={busy || item.status === 'syncing'}
                                title={t('sync_center.actions.discard')}
                                onClick={() => void handleDiscard(item)}
                            >
                                <Trash2 className="size-4 text-destructive" />
                            </Button>
                        </div>
                    );
                },
            },
        ];
    }, [actingUuid, formatDate, handleDiscard, handleRetry, isOnline, t]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('sync_center.title')} />

            <div className="space-y-6">
                <PageHeader
                    title={t('sync_center.title')}
                    description={t('sync_center.description')}
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={loading}
                                onClick={() => void refresh()}
                            >
                                <RefreshCw className="size-4" />
                                {t('sync_center.actions.refresh')}
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                disabled={!isOnline || isSyncing || summary.pending + summary.failed === 0}
                                onClick={() => void handleSyncAll()}
                            >
                                {isSyncing ? (
                                    <RefreshCw className="size-4 animate-spin" />
                                ) : (
                                    <Wifi className="size-4" />
                                )}
                                {isSyncing ? t('banner.syncing') : t('banner.sync_now')}
                            </Button>
                        </div>
                    }
                />

                {!isOnline && (
                    <Alert className="border-amber-500/30 bg-amber-500/10">
                        <CloudOff className="size-4" />
                        <AlertTitle>{t('banner.offline_title')}</AlertTitle>
                        <AlertDescription>{t('sync_center.offline_hint')}</AlertDescription>
                    </Alert>
                )}

                {!supported || !isIndexedDbSupported() ? (
                    <Alert variant="destructive">
                        <AlertTriangle className="size-4" />
                        <AlertTitle>{t('sync_center.unsupported_title')}</AlertTitle>
                        <AlertDescription>{t('sync_center.unsupported_body')}</AlertDescription>
                    </Alert>
                ) : (
                    <>
                        <div className="flex flex-wrap gap-2">
                            <StatBadge label={t('sync_center.stats.pending')} value={summary.pending} variant="warning" />
                            <StatBadge label={t('sync_center.stats.syncing')} value={summary.syncing} variant="info" />
                            <StatBadge label={t('sync_center.stats.failed')} value={summary.failed} variant="danger" />
                            <StatBadge label={t('sync_center.stats.total')} value={summary.total} variant="muted" />
                        </div>

                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <Select
                                value={statusFilter}
                                onValueChange={(value) => setStatusFilter(value as StatusFilter)}
                            >
                                <SelectTrigger className="w-full sm:w-52">
                                    <SelectValue placeholder={t('sync_center.filters.status')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{t('sync_center.filters.status_all')}</SelectItem>
                                    <SelectItem value="pending">{t('sync_center.status.pending')}</SelectItem>
                                    <SelectItem value="syncing">{t('sync_center.status.syncing')}</SelectItem>
                                    <SelectItem value="failed">{t('sync_center.status.failed')}</SelectItem>
                                </SelectContent>
                            </Select>

                            <Select
                                value={typeFilter}
                                onValueChange={(value) => setTypeFilter(value as 'all' | OutboxType)}
                            >
                                <SelectTrigger className="w-full sm:w-64">
                                    <SelectValue placeholder={t('sync_center.filters.type')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{t('sync_center.filters.type_all')}</SelectItem>
                                    {outboxTypeOptions().map((type) => (
                                        <SelectItem key={type} value={type}>
                                            {t(outboxTypeI18nKey(type))}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {filteredItems.length === 0 ? (
                            <EmptyState
                                icon={Inbox}
                                title={t('sync_center.empty_title')}
                                description={t('sync_center.empty_body')}
                            />
                        ) : (
                            <DataTable
                                columns={columns}
                                data={filteredItems}
                                rowKey={(item) => item.uuid}
                                isLoading={loading}
                            />
                        )}

                        <p className="text-xs text-muted-foreground">{t('sync_center.synced_note')}</p>
                    </>
                )}
            </div>

            <Sheet open={detailItem !== null} onOpenChange={(open) => !open && setDetailItem(null)}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                    {detailItem && (
                        <>
                            <SheetHeader>
                                <SheetTitle>
                                    {detailItem.local_label ?? detailItem.uuid.slice(0, 8)}
                                </SheetTitle>
                                <SheetDescription>
                                    {t(outboxTypeI18nKey(detailItem.type))} ·{' '}
                                    {t(`sync_center.status.${detailItem.status}`)}
                                </SheetDescription>
                            </SheetHeader>

                            <div className="mt-6 space-y-4 text-sm">
                                <div>
                                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        {t('sync_center.detail.uuid')}
                                    </p>
                                    <p className="mt-1 break-all font-mono text-xs">{detailItem.uuid}</p>
                                </div>

                                <div>
                                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        {t('sync_center.detail.created')}
                                    </p>
                                    <p className="mt-1">{formatDate(detailItem.created_at)}</p>
                                </div>

                                {detailItem.error && (
                                    <Alert variant="destructive">
                                        <AlertTriangle className="size-4" />
                                        <AlertTitle>{t('sync_center.detail.error')}</AlertTitle>
                                        <AlertDescription>{detailItem.error}</AlertDescription>
                                    </Alert>
                                )}

                                <div>
                                    <p className="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        {t('sync_center.detail.payload')}
                                    </p>
                                    <pre className="max-h-80 overflow-auto rounded-lg border bg-muted/40 p-3 text-xs">
                                        {formatOutboxPayload(detailItem)}
                                    </pre>
                                </div>

                                <div className="flex flex-wrap gap-2 pt-2">
                                    {(detailItem.status === 'pending' || detailItem.status === 'failed') && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            disabled={!isOnline || actingUuid === detailItem.uuid}
                                            onClick={() => void handleRetry(detailItem)}
                                        >
                                            <RotateCcw className="size-4" />
                                            {t('sync_center.actions.retry')}
                                        </Button>
                                    )}
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        disabled={actingUuid === detailItem.uuid || detailItem.status === 'syncing'}
                                        onClick={() => void handleDiscard(detailItem)}
                                    >
                                        <Trash2 className="size-4" />
                                        {t('sync_center.actions.discard')}
                                    </Button>
                                </div>
                            </div>
                        </>
                    )}
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}

OfflineColaIndex.layout = {
    breadcrumbs: [
        { title: 'Offline', href: ROUTE_URL },
        { title: 'Cola de sync', href: ROUTE_URL },
    ],
};
