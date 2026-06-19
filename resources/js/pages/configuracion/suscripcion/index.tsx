import { Head } from '@inertiajs/react';
import {
    AlertCircle,
    CalendarClock,
    CheckCircle2,
    CreditCard,
    ExternalLink,
    Package,
} from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader, StatBadge } from '@/components/data-page';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { SectionCard } from '../general/components/section-card';

type SubscriptionPlan = {
    nombre: string;
    codigo: string;
    badge: string | null;
    color_hex: string | null;
};

type SubscriptionEstado =
    | 'trial'
    | 'active'
    | 'grace'
    | 'suspended'
    | 'cancelled'
    | 'unknown';

type SubscriptionSummary = {
    has_subscription: boolean;
    plan: SubscriptionPlan | null;
    estado: SubscriptionEstado;
    ciclo: 'mensual' | 'anual' | null;
    precio_pactado: string | null;
    trial_ends_at: string | null;
    current_period_start: string | null;
    current_period_end: string | null;
    proximo_cobro_at: string | null;
    renewal_anchor_at: string | null;
    renewal_anchor_source: string;
    days_until_renewal: number | null;
    urgency: 'ok' | 'warning' | 'danger' | 'muted';
    renewal_url: string | null;
};

type SuscripcionIndexProps = {
    subscription: SubscriptionSummary | null;
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

const formatPrice = (value: string | null): string => {
    if (value === null) return '—';
    const num = Number(value);
    if (Number.isNaN(num)) return '—';
    return `S/. ${num.toFixed(2)}`;
};

function estadoVariant(
    estado: SubscriptionEstado,
): Parameters<typeof StatBadge>[0]['variant'] {
    if (estado === 'active') return 'success';
    if (estado === 'trial') return 'info';
    if (estado === 'grace' || estado === 'suspended') return 'warning';
    if (estado === 'cancelled') return 'muted';
    return 'muted';
}

function urgencyAlertClass(urgency: SubscriptionSummary['urgency']): string {
    if (urgency === 'danger') {
        return 'border-destructive/40 bg-destructive/5 text-destructive';
    }
    if (urgency === 'warning') {
        return 'border-amber-500/40 bg-amber-500/5 text-amber-800 dark:text-amber-300';
    }
    if (urgency === 'ok') {
        return 'border-primary/30 bg-primary/5 text-foreground';
    }
    return 'border-border/60 bg-muted/30 text-muted-foreground';
}

function DetailRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium text-foreground">{value}</dd>
        </div>
    );
}

export default function Index({ subscription }: SuscripcionIndexProps) {
    const { t, i18n } = useTranslation(['config-suscripcion', 'common']);

    const summary = subscription;
    const locale = i18n.language;

    const daysLabel = useMemo(() => {
        const days = summary?.days_until_renewal;
        if (days === null || days === undefined) return null;
        if (days === 0) return t('days_until.today');
        if (days < 0) {
            return t('days_until.past', { count: Math.abs(days) });
        }
        return t('days_until.future', { count: days });
    }, [summary?.days_until_renewal, t]);

    const alertMessage = summary
        ? t(`alerts.${summary.urgency}`)
        : t('alerts.muted');

    const planNombre = summary?.plan?.nombre ?? t('empty_plan');
    const estadoLabel = summary
        ? t(`estados.${summary.estado}`)
        : t('estados.unknown');
    const cicloLabel =
        summary?.ciclo !== null && summary?.ciclo !== undefined
            ? t(`ciclos.${summary.ciclo}`)
            : '—';

    return (
        <>
            <Head title={t('title')} />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                />

                {summary && (
                    <Alert className={urgencyAlertClass(summary.urgency)}>
                        <AlertCircle className="size-4" />
                        <AlertDescription className="flex flex-col gap-1">
                            <span>{alertMessage}</span>
                            {daysLabel && (
                                <span className="text-sm font-medium">
                                    {daysLabel}
                                </span>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <SectionCard
                    title={t('sections.plan')}
                    icon={Package}
                    badge={
                        summary ? (
                            <StatBadge
                                label={estadoLabel}
                                value=""
                                variant={estadoVariant(summary.estado)}
                            />
                        ) : undefined
                    }
                >
                    <dl className="flex flex-col gap-4">
                        <DetailRow label={t('fields.plan')} value={planNombre} />
                        <DetailRow
                            label={t('fields.estado')}
                            value={estadoLabel}
                        />
                        <DetailRow
                            label={t('fields.ciclo')}
                            value={cicloLabel}
                        />
                        <DetailRow
                            label={t('fields.precio')}
                            value={formatPrice(summary?.precio_pactado ?? null)}
                        />
                    </dl>
                </SectionCard>

                <SectionCard
                    title={t('sections.dates')}
                    icon={CalendarClock}
                >
                    <dl className="flex flex-col gap-4">
                        {summary?.estado === 'trial' && (
                            <DetailRow
                                label={t('fields.trial_end')}
                                value={formatDate(
                                    summary.trial_ends_at,
                                    locale,
                                )}
                            />
                        )}
                        <DetailRow
                            label={t('fields.period_start')}
                            value={formatDate(
                                summary?.current_period_start ?? null,
                                locale,
                            )}
                        />
                        <DetailRow
                            label={t('fields.period_end')}
                            value={formatDate(
                                summary?.current_period_end ?? null,
                                locale,
                            )}
                        />
                        <DetailRow
                            label={t('fields.proximo_cobro')}
                            value={formatDate(
                                summary?.proximo_cobro_at ?? null,
                                locale,
                            )}
                        />
                        <DetailRow
                            label={t('fields.renewal_anchor')}
                            value={formatDate(
                                summary?.renewal_anchor_at ?? null,
                                locale,
                            )}
                        />
                    </dl>
                </SectionCard>

                <SectionCard
                    title={t('sections.renew')}
                    description={t('renew_hint')}
                    icon={CreditCard}
                    badge={
                        summary?.renewal_url ? (
                            <CheckCircle2 className="size-5 text-primary" />
                        ) : undefined
                    }
                >
                    <div className="flex flex-col gap-4">
                        {summary?.renewal_url ? (
                            <Button asChild className="w-fit">
                                <a
                                    href={summary.renewal_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    {t('renew_cta')}
                                    <ExternalLink className="size-4" />
                                </a>
                            </Button>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {t('no_renewal_url')}
                            </p>
                        )}
                        <p className="text-xs text-muted-foreground">
                            {t('support')}
                        </p>
                    </div>
                </SectionCard>
            </div>
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Configuración' },
            {
                title: 'Mi suscripción',
                href: '/configuracion/suscripcion',
            },
        ]}
    >
        {page}
    </AppLayout>
);
