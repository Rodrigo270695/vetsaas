import { Head } from '@inertiajs/react';
import {
    AlertCircle,
    Bot,
    CalendarClock,
    CheckCircle2,
    CreditCard,
    ExternalLink,
    Package,
    Sparkles,
} from 'lucide-react';
import { Link } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader, StatBadge } from '@/components/data-page';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { SectionCard } from '../general/components/section-card';
import { ComprobantesQuotaCard, type ComprobantesQuota } from './components/comprobantes-quota-card';

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
    urgency: 'ok' | 'yellow' | 'amber' | 'red' | 'danger' | 'muted';
    renewal_url: string | null;
    bot_ia: {
        activo: boolean;
        precio_mensual: string;
        activado_at: string | null;
    };
    renewal_billing: {
        applies: boolean;
        currency: string;
        plan_amount: number;
        bot_ia_amount: number;
        comprobantes_overage_amount: number;
        total_amount: number;
        addons: Array<{ key: string; label: string; amount: number }>;
    };
};

type SuscripcionIndexProps = {
    subscription: SubscriptionSummary | null;
    comprobantes: ComprobantesQuota | null;
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
    switch (urgency) {
        case 'red':
            return 'border-red-500/40 bg-red-500/5 text-red-800 dark:text-red-300';
        case 'amber':
            return 'border-amber-500/40 bg-amber-500/5 text-amber-800 dark:text-amber-300';
        case 'yellow':
            return 'border-yellow-500/40 bg-yellow-500/5 text-yellow-800 dark:text-yellow-300';
        case 'danger':
            return 'border-destructive/40 bg-destructive/5 text-destructive';
        case 'ok':
            return 'border-emerald-500/30 bg-emerald-500/5 text-emerald-800 dark:text-emerald-300';
        default:
            return 'border-border/60 bg-muted/30 text-muted-foreground';
    }
}

function urgencyHeroClass(urgency: SubscriptionSummary['urgency']): string {
    switch (urgency) {
        case 'red':
            return 'border-red-500/25 bg-gradient-to-br from-red-700 via-red-600 to-red-800 shadow-red-900/20';
        case 'amber':
            return 'border-amber-500/25 bg-gradient-to-br from-amber-700 via-amber-600 to-orange-700 shadow-amber-900/20';
        case 'yellow':
            return 'border-yellow-500/25 bg-gradient-to-br from-yellow-700 via-yellow-600 to-amber-700 shadow-yellow-900/20';
        case 'danger':
            return 'border-destructive/30 bg-gradient-to-br from-red-800 via-red-700 to-red-900 shadow-red-950/25';
        default:
            return 'border-brand-600/20 bg-gradient-to-br from-brand-700 via-brand-600 to-brand-800 shadow-brand-900/15';
    }
}

function dateValueClass(urgency: SubscriptionSummary['urgency']): string | undefined {
    switch (urgency) {
        case 'red':
            return 'text-red-700 dark:text-red-400';
        case 'amber':
            return 'text-amber-700 dark:text-amber-400';
        case 'yellow':
            return 'text-yellow-700 dark:text-yellow-400';
        case 'ok':
            return 'text-emerald-700 dark:text-emerald-400';
        default:
            return undefined;
    }
}

function DetailRow({
    label,
    value,
    valueClassName,
    highlight,
}: {
    label: string;
    value: string;
    valueClassName?: string;
    highlight?: boolean;
}) {
    return (
        <div
            className={cn(
                'flex flex-col gap-1 rounded-lg border border-transparent px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between',
                highlight && 'border-border/50 bg-muted/30',
            )}
        >
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd
                className={cn(
                    'text-sm font-medium text-foreground sm:text-right',
                    valueClassName,
                )}
            >
                {value}
            </dd>
        </div>
    );
}

function MetricTile({
    label,
    value,
    subvalue,
    className,
}: {
    label: string;
    value: string;
    subvalue?: string;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'rounded-xl border border-border/60 bg-card/70 p-4 ring-1 ring-border/20 backdrop-blur-sm',
                className,
            )}
        >
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {label}
            </p>
            <p className="mt-2 text-lg font-semibold tracking-tight text-foreground">
                {value}
            </p>
            {subvalue && (
                <p className="mt-1 text-xs text-muted-foreground">{subvalue}</p>
            )}
        </div>
    );
}

export default function Index({ subscription, comprobantes }: SuscripcionIndexProps) {
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

    const urgency = summary?.urgency ?? 'muted';
    const highlightDates = dateValueClass(urgency);
    const renewalDate = formatDate(summary?.renewal_anchor_at ?? null, locale);
    const proximoCobro = formatDate(summary?.proximo_cobro_at ?? null, locale);
    const daysCount = summary?.days_until_renewal;

    const billing = summary?.renewal_billing;
    const paymentTotal = useMemo(() => {
        if (billing?.applies && (billing.total_amount ?? 0) > 0) {
            return billing.total_amount;
        }

        const pactado = summary?.precio_pactado;
        if (pactado === null || pactado === undefined) {
            return null;
        }

        const num = Number(pactado);

        return Number.isNaN(num) ? null : num;
    }, [billing, summary?.precio_pactado]);

    const paymentTotalLabel = useMemo(() => {
        const hasAddons =
            (billing?.bot_ia_amount ?? 0) > 0 ||
            (billing?.comprobantes_overage_amount ?? 0) > 0 ||
            (billing?.applies &&
                billing.total_amount > (billing.plan_amount ?? 0));

        return hasAddons ? t('stats.total') : t('stats.precio');
    }, [billing, t]);

    const heroBreakdown = useMemo(() => {
        if (!billing?.applies || paymentTotal === null) {
            return null;
        }

        const planAmount = billing.plan_amount ?? 0;
        const addonsAmount = Math.max(0, (billing.total_amount ?? 0) - planAmount);

        if (addonsAmount <= 0) {
            return null;
        }

        return t('hero.total_breakdown', {
            plan: formatPrice(String(planAmount)),
            addons: formatPrice(String(addonsAmount)),
        });
    }, [billing, paymentTotal, t]);

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description')}
                    stats={
                        summary
                            ? [
                                  {
                                      label: t('stats.plan'),
                                      value: planNombre,
                                      variant: 'default' as const,
                                      icon: Package,
                                  },
                                  {
                                      label: t('stats.estado'),
                                      value: estadoLabel,
                                      variant: estadoVariant(summary.estado),
                                      icon: CheckCircle2,
                                  },
                                  {
                                      label: paymentTotalLabel,
                                      value:
                                          paymentTotal !== null
                                              ? formatPrice(String(paymentTotal))
                                              : formatPrice(summary.precio_pactado),
                                      variant: 'muted' as const,
                                      icon: CreditCard,
                                  },
                                  {
                                      label: t('stats.renewal'),
                                      value: proximoCobro,
                                      variant:
                                          urgency === 'ok'
                                              ? ('success' as const)
                                              : urgency === 'red' ||
                                                  urgency === 'danger'
                                                ? ('warning' as const)
                                                : ('info' as const),
                                      icon: CalendarClock,
                                  },
                              ]
                            : []
                    }
                />

                {summary && (
                    <section
                        className={cn(
                            'relative overflow-hidden rounded-2xl border px-6 py-7 text-white shadow-lg md:px-8 md:py-8',
                            urgencyHeroClass(summary.urgency),
                        )}
                    >
                        <div
                            className="pointer-events-none absolute -right-16 -top-20 size-56 rounded-full bg-white/10 blur-2xl"
                            aria-hidden
                        />
                        <div
                            className="pointer-events-none absolute -bottom-24 left-1/3 size-72 rounded-full bg-white/10 blur-3xl"
                            aria-hidden
                        />

                        <div className="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                            <div className="space-y-4">
                                <div className="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-medium backdrop-blur-sm ring-1 ring-white/20">
                                    <Sparkles className="size-3.5" aria-hidden />
                                    {t('hero.badge')}
                                </div>
                                <div>
                                    <h2 className="text-2xl font-semibold tracking-tight md:text-3xl">
                                        {planNombre}
                                    </h2>
                                    <p className="mt-2 text-sm text-white/85">
                                        {cicloLabel} · {estadoLabel}
                                    </p>
                                    <p className="mt-1 text-sm text-white/75">
                                        {paymentTotal !== null
                                            ? formatPrice(String(paymentTotal))
                                            : formatPrice(summary.precio_pactado)}
                                    </p>
                                    {heroBreakdown ? (
                                        <p className="mt-1 text-xs text-white/60">
                                            {heroBreakdown}
                                        </p>
                                    ) : null}
                                </div>
                            </div>

                            <div className="flex flex-col items-start gap-2 rounded-2xl bg-white/10 px-5 py-4 ring-1 ring-white/15 backdrop-blur-sm lg:min-w-52 lg:items-end lg:text-right">
                                {daysCount !== null && daysCount >= 0 ? (
                                    <>
                                        <span className="text-4xl font-bold tabular-nums leading-none">
                                            {daysCount}
                                        </span>
                                        <span className="text-sm text-white/85">
                                            {daysCount === 0
                                                ? t('hero.due_today')
                                                : t('hero.days_label')}
                                        </span>
                                    </>
                                ) : (
                                    <span className="text-lg font-semibold">
                                        {daysLabel ?? renewalDate}
                                    </span>
                                )}
                                <span className="text-xs text-white/70">
                                    {renewalDate}
                                </span>
                            </div>
                        </div>
                    </section>
                )}

                {summary && summary.urgency !== 'ok' && (
                    <Alert className={urgencyAlertClass(summary.urgency)}>
                        <AlertCircle className="size-4" />
                        <AlertDescription className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <span>{alertMessage}</span>
                            {daysLabel && (
                                <span className="text-sm font-semibold">
                                    {daysLabel}
                                </span>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-5 xl:grid-cols-12">
                    <SectionCard
                        title={t('sections.plan')}
                        icon={Package}
                        className="xl:col-span-5"
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
                        <div className="grid gap-3 sm:grid-cols-2">
                            <MetricTile
                                label={t('fields.plan')}
                                value={planNombre}
                            />
                            <MetricTile
                                label={t('fields.estado')}
                                value={estadoLabel}
                            />
                            <MetricTile
                                label={t('fields.ciclo')}
                                value={cicloLabel}
                            />
                            <MetricTile
                                label={t('fields.precio')}
                                value={formatPrice(summary?.precio_pactado ?? null)}
                            />
                            {billing?.applies &&
                                (billing.total_amount ?? 0) >
                                    (billing.plan_amount ?? 0) && (
                                    <MetricTile
                                        label={t('stats.total')}
                                        value={formatPrice(
                                            String(billing.total_amount),
                                        )}
                                        subvalue={heroBreakdown ?? undefined}
                                    />
                                )}
                        </div>
                    </SectionCard>

                    <SectionCard
                        title={t('sections.dates')}
                        icon={CalendarClock}
                        className="xl:col-span-7"
                    >
                        <dl className="grid gap-2 sm:grid-cols-2">
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
                                value={proximoCobro}
                                valueClassName={highlightDates}
                                highlight
                            />
                            <DetailRow
                                label={t('fields.renewal_anchor')}
                                value={renewalDate}
                                valueClassName={highlightDates}
                                highlight
                            />
                        </dl>
                    </SectionCard>

                    <ComprobantesQuotaCard
                        quota={comprobantes}
                        locale={locale}
                        className="xl:col-span-6"
                    />

                    {summary && (
                        <SectionCard
                            title={t('sections.bot_ia')}
                            icon={Bot}
                            className="xl:col-span-6"
                            badge={
                                <StatBadge
                                    label={
                                        summary.bot_ia?.activo
                                            ? t('bot_ia.active')
                                            : t('bot_ia.inactive')
                                    }
                                    value=""
                                    variant={
                                        summary.bot_ia?.activo ? 'success' : 'muted'
                                    }
                                />
                            }
                        >
                            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div className="space-y-2">
                                    <p className="text-sm font-medium text-foreground">
                                        {summary.bot_ia?.activo
                                            ? t('bot_ia.price', {
                                                  price: formatPrice(
                                                      summary.bot_ia.precio_mensual,
                                                  ).replace('S/. ', ''),
                                              })
                                            : t('bot_ia.price', { price: '15.00' })}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {summary.bot_ia?.activo
                                            ? t('bot_ia.description_active')
                                            : t('bot_ia.description_inactive')}
                                    </p>
                                    {summary.bot_ia?.activo &&
                                        summary.bot_ia.activado_at && (
                                            <p className="text-xs text-muted-foreground">
                                                {t('bot_ia.activated_at', {
                                                    date: formatDate(
                                                        summary.bot_ia.activado_at,
                                                        locale,
                                                    ),
                                                })}
                                            </p>
                                        )}
                                </div>
                                {summary.bot_ia?.activo && (
                                    <Button asChild variant="outline">
                                        <Link href="/comunicaciones/bot-ia">
                                            {t('bot_ia.manage_cta')}
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </SectionCard>
                    )}

                    <SectionCard
                        title={t('sections.renew')}
                        description={t('renew_hint')}
                        icon={CreditCard}
                        className="xl:col-span-6 flex h-full flex-col"
                        badge={
                            summary?.renewal_url ? (
                                <CheckCircle2 className="size-5 text-primary" />
                            ) : undefined
                        }
                    >
                        {summary.renewal_billing?.applies &&
                            summary.renewal_billing.total_amount > 0 && (
                                <div className="mb-4 rounded-lg border border-border/60 bg-muted/20 px-4 py-3">
                                    <p className="text-sm font-medium text-foreground">
                                        {t('renewal_billing.total')}:{' '}
                                        {formatPrice(
                                            String(summary.renewal_billing.total_amount),
                                        )}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {t('renewal_billing.total_hint')}
                                    </p>
                                    <div className="mt-2 flex flex-wrap gap-3 text-xs text-muted-foreground">
                                        <span>
                                            {t('renewal_billing.breakdown_plan')}:{' '}
                                            {formatPrice(
                                                String(summary.renewal_billing.plan_amount),
                                            )}
                                        </span>
                                        {summary.renewal_billing.bot_ia_amount > 0 && (
                                            <span>
                                                {t('renewal_billing.breakdown_bot_ia')}:{' '}
                                                {formatPrice(
                                                    String(
                                                        summary.renewal_billing.bot_ia_amount,
                                                    ),
                                                )}
                                            </span>
                                        )}
                                        {(summary.renewal_billing
                                            .comprobantes_overage_amount ?? 0) > 0 && (
                                            <span>
                                                {t(
                                                    'renewal_billing.breakdown_comprobantes',
                                                )}
                                                :{' '}
                                                {formatPrice(
                                                    String(
                                                        summary.renewal_billing
                                                            .comprobantes_overage_amount,
                                                    ),
                                                )}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            )}
                        <div className="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                            <div className="max-w-2xl space-y-2">
                                <p className="text-sm text-muted-foreground">
                                    {t('renew_hint')}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {t('support')}
                                </p>
                            </div>

                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                {summary?.renewal_url ? (
                                    <Button asChild size="lg" className="min-w-48">
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
                            </div>
                        </div>
                    </SectionCard>
                </div>
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
