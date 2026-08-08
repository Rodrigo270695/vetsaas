import { Head } from '@inertiajs/react';
import { ExternalLink, Landmark } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PageHeader } from '@/components/data-page';
import AppLayout from '@/layouts/app-layout';
import { ApiPeruDetailModal } from './components/apiperu-detail-modal';
import { ApiPeruHubCard } from './components/apiperu-hub-card';
import type { ApiPeruIndexProps, ApiPeruPerfilPayload } from './types';

export default function PlataformaApiPeruIndex({ profiles, meta }: ApiPeruIndexProps) {
    const { t } = useTranslation(['plataforma-apiperu', 'common']);
    const [detail, setDetail] = useState<ApiPeruPerfilPayload | null>(null);
    const [modalOpen, setModalOpen] = useState(false);
    const consultarUrl = '/plataforma/apiperu/consultar-perfil';
    const disabled = !meta.token_configured;

    const sourceCount = useMemo(
        () => profiles.reduce((acc, p) => acc + p.endpoint_keys.length, 0),
        [profiles],
    );

    const openResult = (payload: ApiPeruPerfilPayload) => {
        setDetail(payload);
        setModalOpen(true);
    };

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    stats={[
                        {
                            label: t('stats.hubs'),
                            value: profiles.length,
                            variant: 'primary',
                        },
                        {
                            label: t('stats.sources'),
                            value: sourceCount,
                            variant: 'muted',
                        },
                    ]}
                    action={
                        <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                            <Badge
                                variant={meta.token_configured ? 'default' : 'secondary'}
                                className="h-8 justify-center px-3 font-normal"
                            >
                                {meta.token_configured
                                    ? t('meta.token_ok')
                                    : t('meta.token_missing')}
                            </Badge>
                            <Button asChild variant="outline" className="h-10 gap-2">
                                <a href={meta.docs_url} target="_blank" rel="noreferrer">
                                    <ExternalLink className="size-4 opacity-70" aria-hidden />
                                    {t('meta.docs')}
                                </a>
                            </Button>
                        </div>
                    }
                />

                {!meta.token_configured ? (
                    <Alert className="border-amber-500/30 bg-amber-500/5">
                        <Landmark className="size-4" />
                        <AlertTitle>{t('alert.token_title')}</AlertTitle>
                        <AlertDescription>
                            {t('alert.token_body', { env: 'APIPERU_TOKEN' })}
                            <span className="mt-1 block font-mono text-xs text-muted-foreground">
                                {meta.base_url}
                            </span>
                        </AlertDescription>
                    </Alert>
                ) : (
                    <Alert className="border-primary/20 bg-primary/5">
                        <Landmark className="size-4 text-primary" />
                        <AlertTitle>{t('alert.ready_title')}</AlertTitle>
                        <AlertDescription className="text-muted-foreground">
                            {t('alert.ready_body')}
                            <span className="mt-1 block font-mono text-xs">{meta.base_url}</span>
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    {profiles.map((profile) => (
                        <ApiPeruHubCard
                            key={profile.id}
                            profile={profile}
                            consultarUrl={consultarUrl}
                            disabled={disabled}
                            onResult={openResult}
                        />
                    ))}
                </div>
            </div>

            <ApiPeruDetailModal
                open={modalOpen}
                onOpenChange={(open) => {
                    setModalOpen(open);
                    if (!open) {
                        setDetail(null);
                    }
                }}
                payload={detail}
            />
        </>
    );
}

PlataformaApiPeruIndex.layout = (page: ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Plataforma' },
            { title: 'ApiPerú', href: '/plataforma/apiperu' },
        ]}
    >
        {page}
    </AppLayout>
);
