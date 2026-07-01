import { Head, Link } from '@inertiajs/react';
import { BookOpen, Bot, Lock, MessageCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageHeader, StatBadge } from '@/components/data-page';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type WhatsAppProps } from '../components/whatsapp-connect-card';

type BotIaPayload = {
    activo: boolean;
    precio_mensual: string;
    activado_at: string | null;
};

type BotIaPageProps = {
    bot_ia: BotIaPayload;
    whatsapp: WhatsAppProps;
    can_manage: boolean;
};

const formatDate = (value: string | null, locale: string): string => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleDateString(locale === 'en' ? 'en-US' : 'es-PE', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
};

const formatPrice = (value: string): string => {
    const num = Number(value);
    if (Number.isNaN(num)) return value;
    return num.toFixed(2);
};

export default function Index({ bot_ia, whatsapp }: BotIaPageProps) {
    const { t, i18n } = useTranslation(['bot-ia', 'nav', 'comunicaciones']);
    const locale = i18n.language;
    const isActive = bot_ia.activo === true;
    const isWhatsappReady = whatsapp.session?.is_ready === true;

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    stats={
                        isActive
                            ? [
                                  {
                                      label: t('active.title'),
                                      value: `S/. ${formatPrice(bot_ia.precio_mensual)}`,
                                      variant: 'success' as const,
                                      icon: Bot,
                                  },
                              ]
                            : []
                    }
                />

                {!isActive ? (
                    <Alert className="border-amber-500/30 bg-amber-500/5">
                        <Lock className="size-4 text-amber-600" />
                        <AlertDescription className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p className="font-medium text-foreground">
                                    {t('locked.title')}
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {t('locked.description')}
                                </p>
                            </div>
                            <Button asChild variant="outline" className="shrink-0">
                                <Link href="/configuracion/suscripcion">
                                    {t('locked.cta')}
                                </Link>
                            </Button>
                        </AlertDescription>
                    </Alert>
                ) : (
                    <>
                        <Card className="border-violet-500/20 bg-violet-500/5">
                            <CardHeader className="pb-2">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Bot className="size-5 text-violet-600" />
                                        {t('active.title')}
                                    </CardTitle>
                                    <StatBadge
                                        label={t('active.title')}
                                        value=""
                                        variant="success"
                                    />
                                </div>
                                <CardDescription>
                                    {t('active.price_hint', {
                                        price: formatPrice(bot_ia.precio_mensual),
                                    })}
                                </CardDescription>
                            </CardHeader>
                            {bot_ia.activado_at && (
                                <CardContent className="pt-0">
                                    <p className="text-xs text-muted-foreground">
                                        {t('active.activated_at', {
                                            date: formatDate(bot_ia.activado_at, locale),
                                        })}
                                    </p>
                                </CardContent>
                            )}
                        </Card>

                        <Card>
                            <CardHeader className="pb-3">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <CardTitle className="flex items-center gap-2 text-base">
                                            <MessageCircle className="size-5 text-emerald-600" />
                                            {t('comunicaciones:whatsapp.title')}
                                        </CardTitle>
                                        <CardDescription className="mt-1">
                                            {t('whatsapp_hint')}
                                        </CardDescription>
                                    </div>
                                    <StatBadge
                                        label={
                                            isWhatsappReady
                                                ? t('comunicaciones:whatsapp.status_ready')
                                                : whatsapp.session?.last_error
                                                  ? t('comunicaciones:whatsapp.status_error')
                                                  : t('comunicaciones:whatsapp.status_pending')
                                        }
                                        value=""
                                        variant={
                                            isWhatsappReady
                                                ? 'success'
                                                : whatsapp.session?.last_error
                                                  ? 'danger'
                                                  : 'warning'
                                        }
                                    />
                                </div>
                            </CardHeader>
                            <CardContent className="flex flex-wrap items-center justify-between gap-3 pt-0">
                                {isWhatsappReady && whatsapp.session?.phone ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('comunicaciones:whatsapp.phone')}:{' '}
                                        <span className="font-medium text-foreground">
                                            {whatsapp.session.phone}
                                        </span>
                                    </p>
                                ) : (
                                    <span />
                                )}
                                <Button asChild variant="outline" size="sm" className="shrink-0">
                                    <Link href="/comunicaciones/cola">{t('whatsapp_manage')}</Link>
                                </Button>
                            </CardContent>
                        </Card>

                        <Card className="border-dashed">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <BookOpen className="size-5" />
                                    {t('active.knowledge_title')}
                                </CardTitle>
                                <CardDescription>
                                    {t('active.knowledge_description')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <StatBadge
                                    label={t('active.knowledge_coming')}
                                    value=""
                                    variant="muted"
                                />
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Comunicaciones' },
            { title: 'Asistente IA', href: '/comunicaciones/bot-ia' },
        ]}
    >
        {page}
    </AppLayout>
);
