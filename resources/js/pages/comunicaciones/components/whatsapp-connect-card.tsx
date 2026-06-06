import { router } from '@inertiajs/react';
import { Loader2, LogOut, MessageCircle, RefreshCw, Send } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { StatBadge } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { WhatsAppDisconnectDialog } from './whatsapp-disconnect-dialog';
import { WhatsAppTestMessageDialog } from './whatsapp-test-message-dialog';

export type WhatsAppSessionProps = {
    id: string;
    openwa_session_id: string;
    openwa_session_name: string;
    status: string;
    phone: string | null;
    push_name: string | null;
    connected_at: string | null;
    last_synced_at: string | null;
    last_error: string | null;
    is_ready: boolean;
};

export type WhatsAppProps = {
    enabled: boolean;
    configured: boolean;
    session: WhatsAppSessionProps | null;
};

export type WhatsAppApiRoutes = {
    sync: string;
    qr: string;
    logout: string;
    test: string;
};

const DEFAULT_API_ROUTES: WhatsAppApiRoutes = {
    sync: '/comunicaciones/whatsapp/sync',
    qr: '/comunicaciones/whatsapp/qr',
    logout: '/comunicaciones/whatsapp/logout',
    test: '/comunicaciones/whatsapp/test',
};

type Props = {
    whatsapp: WhatsAppProps;
    canManage: boolean;
    apiRoutes?: WhatsAppApiRoutes;
    translationNs?: 'comunicaciones' | 'avisos-renovacion';
};

export function WhatsAppConnectCard({
    whatsapp,
    canManage,
    apiRoutes = DEFAULT_API_ROUTES,
    translationNs = 'comunicaciones',
}: Props) {
    const { t, i18n } = useTranslation(translationNs);
    const [qrCode, setQrCode] = useState<string | null>(null);
    const [loadingQr, setLoadingQr] = useState(false);
    const [syncing, setSyncing] = useState(false);
    const [disconnectOpen, setDisconnectOpen] = useState(false);
    const [testOpen, setTestOpen] = useState(false);
    const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const stopPoll = useCallback(() => {
        if (pollRef.current !== null) {
            clearInterval(pollRef.current);
            pollRef.current = null;
        }
    }, []);

    useEffect(() => () => stopPoll(), [stopPoll]);

    const fetchQr = useCallback(async () => {
        setLoadingQr(true);
        try {
            const res = await fetch(apiRoutes.qr, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = (await res.json()) as { ready?: boolean; qr_code?: string };
            if (data.ready) {
                setQrCode(null);
                router.reload({ only: ['whatsapp'] });
                stopPoll();
            } else if (data.qr_code) {
                setQrCode(data.qr_code);
            }
        } finally {
            setLoadingQr(false);
        }
    }, [apiRoutes.qr, stopPoll]);

    const handleConnect = useCallback(() => {
        if (!canManage) {
            return;
        }

        setSyncing(true);
        router.post(
            apiRoutes.sync,
            {},
            {
                preserveScroll: true,
                onFinish: () => setSyncing(false),
                onSuccess: () => {
                    void fetchQr();
                    stopPoll();
                    pollRef.current = setInterval(() => {
                        void fetchQr();
                    }, 4000);
                },
            },
        );
    }, [apiRoutes.sync, canManage, fetchQr, stopPoll]);

    if (!whatsapp.configured) {
        return (
            <Card className="border-dashed">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2 text-base">
                        <MessageCircle className="size-5" />
                        {t('whatsapp.title')}
                    </CardTitle>
                    <CardDescription>{t('whatsapp.disabled')}</CardDescription>
                </CardHeader>
            </Card>
        );
    }

    const session = whatsapp.session;
    const isReady = session?.is_ready === true;

    return (
        <Card>
            <CardHeader className="pb-3">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <MessageCircle className="size-5 text-emerald-600" />
                            {t('whatsapp.title')}
                        </CardTitle>
                        <CardDescription className="mt-1">{t('whatsapp.description')}</CardDescription>
                    </div>
                    <StatBadge
                        label={
                            isReady
                                ? t('whatsapp.status_ready')
                                : session?.last_error
                                  ? t('whatsapp.status_error')
                                  : t('whatsapp.status_pending')
                        }
                        value=""
                        variant={isReady ? 'success' : session?.last_error ? 'danger' : 'warning'}
                    />
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                {isReady && session?.phone ? (
                    <p className="text-sm text-muted-foreground">
                        {t('whatsapp.phone')}:{' '}
                        <span className="font-medium text-foreground">{session.phone}</span>
                    </p>
                ) : null}

                {session?.last_error ? (
                    <p className="text-sm text-destructive">{session.last_error}</p>
                ) : null}

                {session?.last_synced_at ? (
                    <p className="text-xs text-muted-foreground">
                        {t('whatsapp.last_sync')}:{' '}
                        {new Date(session.last_synced_at).toLocaleString(i18n.language)}
                    </p>
                ) : null}

                {!isReady && qrCode ? (
                    <div className="flex flex-col items-center gap-2 rounded-lg border bg-muted/30 p-4">
                        <p className="text-sm font-medium">{t('whatsapp.scan_qr')}</p>
                        <img src={qrCode} alt="QR WhatsApp" className="max-w-[220px] rounded-md" />
                    </div>
                ) : null}

                {canManage ? (
                    <div className="flex flex-wrap gap-2">
                        {!isReady ? (
                            <Button type="button" size="sm" onClick={handleConnect} disabled={syncing || loadingQr}>
                                {syncing || loadingQr ? (
                                    <Loader2 className="mr-2 size-4 animate-spin" />
                                ) : (
                                    <MessageCircle className="mr-2 size-4" />
                                )}
                                {t('whatsapp.connect')}
                            </Button>
                        ) : (
                            <>
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={() => setTestOpen(true)}
                                    disabled={syncing}
                                >
                                    <Send className="mr-2 size-4" />
                                    {t('whatsapp.test_send')}
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={handleConnect}
                                    disabled={syncing || disconnectOpen}
                                >
                                    {syncing ? (
                                        <Loader2 className="mr-2 size-4 animate-spin" />
                                    ) : (
                                        <RefreshCw className="mr-2 size-4" />
                                    )}
                                    {t('whatsapp.sync')}
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    className="text-destructive hover:text-destructive"
                                    onClick={() => setDisconnectOpen(true)}
                                    disabled={syncing}
                                >
                                    <LogOut className="mr-2 size-4" />
                                    {t('whatsapp.disconnect')}
                                </Button>
                            </>
                        )}
                    </div>
                ) : null}
            </CardContent>

            <WhatsAppDisconnectDialog
                open={disconnectOpen}
                onOpenChange={setDisconnectOpen}
                phone={session?.phone ?? null}
                logoutUrl={apiRoutes.logout}
                translationNs={translationNs}
                onSuccess={() => {
                    setQrCode(null);
                    stopPoll();
                }}
            />

            <WhatsAppTestMessageDialog
                open={testOpen}
                onOpenChange={setTestOpen}
                defaultPhone={session?.phone ?? null}
                testUrl={apiRoutes.test}
                translationNs={translationNs}
            />
        </Card>
    );
}
