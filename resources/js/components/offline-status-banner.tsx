import { Link } from '@inertiajs/react';
import { CloudOff, CloudUpload, ListTodo, RefreshCw, Wifi } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { OfflineAwareLink } from '@/components/offline-aware-link';
import { Button } from '@/components/ui/button';
import { useOfflineSync } from '@/hooks/use-offline-sync';
import { cn } from '@/lib/utils';

const SYNC_CENTER_URL = '/offline/cola';

/**
 * Banner global de conectividad y cola offline.
 * Visible cuando no hay internet o hay registros pendientes de sync.
 */
export function OfflineStatusBanner() {
    const { t } = useTranslation('offline');
    const { isOnline, pendingCount, isSyncing, syncNow } = useOfflineSync();

    const showBanner = !isOnline || pendingCount > 0;

    if (!showBanner) {
        return null;
    }

    const isOfflineMode = !isOnline;
    const QueueLink = isOfflineMode ? OfflineAwareLink : Link;

    return (
        <div
            className={cn(
                'border-b px-4 py-2.5 text-sm',
                isOfflineMode
                    ? 'border-amber-500/30 bg-amber-500/10 text-amber-950 dark:text-amber-100'
                    : 'border-sky-500/30 bg-sky-500/10 text-sky-950 dark:text-sky-100',
            )}
            role="status"
        >
            <div className="mx-auto flex max-w-7xl flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-start gap-2">
                    {isOfflineMode ? (
                        <CloudOff className="mt-0.5 size-4 shrink-0" />
                    ) : (
                        <CloudUpload className="mt-0.5 size-4 shrink-0" />
                    )}
                    <div>
                        <p className="font-medium">
                            {isOfflineMode
                                ? t('banner.offline_title')
                                : t('banner.sync_title', { count: pendingCount })}
                        </p>
                        <p className="text-xs opacity-80">
                            {isOfflineMode
                                ? t('banner.offline_body')
                                : t('banner.sync_body')}
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="shrink-0"
                        asChild
                    >
                        <QueueLink href={SYNC_CENTER_URL}>
                            <ListTodo className="size-4" />
                            {t('banner.view_queue')}
                        </QueueLink>
                    </Button>

                    {isOnline && pendingCount > 0 && (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="shrink-0"
                            disabled={isSyncing}
                            onClick={() => void syncNow()}
                        >
                            {isSyncing ? (
                                <RefreshCw className="size-4 animate-spin" />
                            ) : (
                                <Wifi className="size-4" />
                            )}
                            {isSyncing ? t('banner.syncing') : t('banner.sync_now')}
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}
