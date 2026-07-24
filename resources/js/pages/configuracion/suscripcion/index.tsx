import { Head, Link } from '@inertiajs/react';
import {
    AlertCircle,
    Bot,
    CalendarClock,
    ChevronRight,
    CreditCard,
    ExternalLink,
    Package,
    Sparkles,
} from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/data-page';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { ComprobantesQuotaCard, type ComprobantesQuota } from './components/comprobantes-quota-card';
import { PlanUsageSection } from './components/plan-usage-section';

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
    ciclo: 'mensual' | 'trimestral' | 'semestral' | 'anual' | null;
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
        acoplado_al_plan: boolean;
        proximo_cobro_at: string | null;
    };
    renewal_billing: {
        applies: boolean;
        currency: string;
        plan_amount: number;
        bot_ia_amount: number;
        comprobantes_overage_amount: number;
        limit_extras_amount?: number;
        total_amount: number;
        addons: Array<{ key: string; label: string; amount: number }>;
    };
};

type SuscripcionIndexProps = {
    subscription: SubscriptionSummary | null;
    comprobantes: ComprobantesQuota | null;
};

const formatDate = (value: string | null, locale: string, short = false): string => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleDateString(locale === 'en' ? 'en-US' : 'es-PE', {
        day: '2-digit',
        month: short ? 'short' : 'long',
        year: 'numeric',
    });
};

const formatPrice = (value: string | number | null): string => {
    if (value === null) return '—';
    const num = typeof value === 'number' ? value : Number(value);
    if (Number.isNaN(num)) return '—';
    return `S/. ${num.toFixed(2)}`;
};

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
            return 'from-red-700 via-red-600 to-red-800';
        case 'amber':
            return 'from-amber-700 via-amber-600 to-orange-700';
        case 'yellow':
            return 'from-yellow-700 via-yellow-600 to-amber-700';
        case 'danger':
            return 'from-red-800 via-red-700 to-red-900';
        default:
            return 'from-brand-700 via-brand-600 to-brand-800';
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

function InfoRow({
    label,
    value,
    valueClassName,
    mono,
}: {
    label: string;
    value: string;
    valueClassName?: string;
    mono?: boolean;
}) {
    return (
        <div className="flex items-baseline justify-between gap-3 py-1.5">
            <span className="shrink-0 text-xs text-muted-foreground">{label}</span>
            <span
                className={cn(
                    'text-right text-sm font-medium text-foreground',
                    mono && 'tabular-nums',
                    valueClassName,
                )}
            >
                {value}
            </span>
        </div>
    );
}

function PanelSection({
    title,
    icon: Icon,
    children,
    action,
}: {
    title: string;
    icon?: React.ComponentType<{ className?: string }>;
    children: React.ReactNode;
    action?: React.ReactNode;
}) {
    return (
        <section className="min-w-0">
            <div className="mb-2 flex items-center justify-between gap-2">
                <h3 className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    {Icon ? <Icon className="size-3.5" /> : null}
                    {title}
                </h3>
                {action}
            </div>
            {children}
        </section>
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
    const renewalDate = formatDate(summary?.renewal_anchor_at ?? null, locale, true);
    const proximoCobro = formatDate(summary?.proximo_cobro_at ?? null, locale, true);
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

    const billingLines = useMemo(() => {
        if (!billing?.applies) {
            return [];
        }

        const lines: { label: string; amount: number }[] = [];

        if ((billing.plan_amount ?? 0) > 0) {
            lines.push({
                label: t('renewal_billing.breakdown_plan'),
                amount: billing.plan_amount,
            });
        }
        if ((billing.bot_ia_amount ?? 0) > 0) {
            lines.push({
                label: t('renewal_billing.breakdown_bot_ia'),
                amount: billing.bot_ia_amount,
            });
        }
        for (const addon of billing.addons ?? []) {
            if (
                addon.key === 'bot_ia' ||
                addon.key === 'comprobantes_overage'
            ) {
                continue;
            }
            if ((addon.amount ?? 0) > 0) {
                lines.push({
                    label: addon.label,
                    amount: addon.amount,
                });
            }
        }
        if ((billing.comprobantes_overage_amount ?? 0) > 0) {
            lines.push({
                label: t('renewal_billing.breakdown_comprobantes'),
                amount: billing.comprobantes_overage_amount,
            });
        }

        return lines;
    }, [billing, t]);

    const hasBillingExtras = billingLines.length > 1;

    return (
        <>
            <Head title={t('title')} />

            <div className="flex flex-1 flex-col gap-5 p-4 sm:p-6">
                <PageHeader title={t('title')} description={t('description')} />

                {summary && summary.urgency !== 'ok' && (
                    <Alert className={cn('py-2.5', urgencyAlertClass(summary.urgency))}>
                        <AlertCircle className="size-4" />
                        <AlertDescription className="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span>{alertMessage}</span>
                            {daysLabel ? (
                                <span className="font-semibold">{daysLabel}</span>
                            ) : null}
                        </AlertDescription>
                    </Alert>
                )}

                {summary && (
                    <div
                        className={cn(
                            'overflow-hidden rounded-xl border border-white/10 bg-linear-to-r text-white shadow-md',
                            urgencyHeroClass(summary.urgency),
                        )}
                    >
                        <div className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                            <div className="min-w-0 space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="inline-flex items-center gap-1 rounded-full bg-white/15 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide ring-1 ring-white/20">
                                        <Sparkles className="size-3" />
                                        {t('hero.badge')}
                                    </span>
                                    <span className="rounded-full bg-white/15 px-2 py-0.5 text-[10px] font-medium ring-1 ring-white/20">
                                        {estadoLabel}
                                    </span>
                                </div>
                                <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                    <h2 className="text-xl font-semibold tracking-tight">
                                        {planNombre}
                                    </h2>
                                    <span className="text-sm text-white/75">
                                        {cicloLabel}
                                    </span>
                                </div>
                                <p className="text-2xl font-bold tabular-nums leading-tight">
                                    {paymentTotal !== null
                                        ? formatPrice(paymentTotal)
                                        : formatPrice(summary.precio_pactado)}
                                </p>
                                {hasBillingExtras ? (
                                    <p className="text-xs text-white/65">
                                        {billingLines
                                            .map(
                                                (line) =>
                                                    `${line.label} ${formatPrice(line.amount)}`,
                                            )
                                            .join(' · ')}
                                    </p>
                                ) : null}
                            </div>

                            <div className="flex shrink-0 flex-wrap items-center gap-3 sm:justify-end">
                                <div className="rounded-lg bg-black/15 px-3 py-2 text-center ring-1 ring-white/15 sm:text-right">
                                    {daysCount !== null && daysCount >= 0 ? (
                                        <>
                                            <p className="text-2xl font-bold tabular-nums leading-none">
                                                {daysCount}
                                            </p>
                                            <p className="mt-0.5 text-[11px] text-white/80">
                                                {daysCount === 0
                                                    ? t('hero.due_today')
                                                    : t('hero.days_label')}
                                            </p>
                                        </>
                                    ) : (
                                        <p className="text-sm font-semibold">
                                            {daysLabel ?? renewalDate}
                                        </p>
                                    )}
                                    <p className="text-[11px] text-white/65">{proximoCobro}</p>
                                </div>

                                {summary.renewal_url ? (
                                    <Button
                                        asChild
                                        size="sm"
                                        className="bg-white text-brand-800 hover:bg-white/90"
                                    >
                                        <a
                                            href={summary.renewal_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            {t('renew_cta')}
                                            <ExternalLink className="size-3.5" />
                                        </a>
                                    </Button>
                                ) : null}
                            </div>
                        </div>
                    </div>
                )}

                <div className="grid gap-5 xl:grid-cols-12">
                    <div
                        className={cn(
                            'overflow-hidden rounded-xl border border-border/60 bg-card/80 shadow-sm ring-1 ring-border/20',
                            comprobantes?.enabled ? 'xl:col-span-8' : 'xl:col-span-12',
                        )}
                    >
                        <div className="grid divide-y lg:grid-cols-3 lg:divide-x lg:divide-y-0">
                        <div className="p-4">
                            <PanelSection title={t('sections.plan')} icon={Package}>
                                <InfoRow label={t('fields.plan')} value={planNombre} />
                                <InfoRow label={t('fields.ciclo')} value={cicloLabel} />
                                <InfoRow
                                    label={t('fields.precio')}
                                    value={formatPrice(summary?.precio_pactado ?? null)}
                                    mono
                                />
                                {summary?.bot_ia?.activo ? (
                                    <InfoRow
                                        label={t('fields.bot_ia_addon')}
                                        value={formatPrice(summary.bot_ia.precio_mensual)}
                                        mono
                                    />
                                ) : null}
                                {hasBillingExtras && paymentTotal !== null ? (
                                    <div className="mt-2 border-t border-border/50 pt-2">
                                        {billingLines.map((line) => (
                                            <InfoRow
                                                key={line.label}
                                                label={line.label}
                                                value={formatPrice(line.amount)}
                                                mono
                                            />
                                        ))}
                                        <InfoRow
                                            label={t('stats.total')}
                                            value={formatPrice(paymentTotal)}
                                            valueClassName="text-primary"
                                            mono
                                        />
                                    </div>
                                ) : null}
                                {summary?.bot_ia?.activo ? (
                                    <Link
                                        href="/comunicaciones/bot-ia"
                                        className="mt-2 inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                                    >
                                        <Bot className="size-3.5" />
                                        {t('bot_ia.manage_cta')}
                                        <ChevronRight className="size-3.5" />
                                    </Link>
                                ) : null}
                            </PanelSection>
                        </div>

                        <div className="p-4">
                            <PanelSection
                                title={t('sections.dates')}
                                icon={CalendarClock}
                            >
                                {summary?.estado === 'trial' ? (
                                    <InfoRow
                                        label={t('fields.trial_end')}
                                        value={formatDate(
                                            summary.trial_ends_at,
                                            locale,
                                            true,
                                        )}
                                    />
                                ) : null}
                                <InfoRow
                                    label={t('fields.period_start')}
                                    value={formatDate(
                                        summary?.current_period_start ?? null,
                                        locale,
                                        true,
                                    )}
                                />
                                <InfoRow
                                    label={t('fields.period_end')}
                                    value={formatDate(
                                        summary?.current_period_end ?? null,
                                        locale,
                                        true,
                                    )}
                                />
                                <InfoRow
                                    label={t('fields.proximo_cobro')}
                                    value={proximoCobro}
                                    valueClassName={highlightDates}
                                />
                                <InfoRow
                                    label={t('fields.renewal_anchor')}
                                    value={renewalDate}
                                    valueClassName={highlightDates}
                                />
                            </PanelSection>
                        </div>

                        <div className="p-4">
                            <PanelSection title={t('sections.renew')} icon={CreditCard}>
                                {paymentTotal !== null && hasBillingExtras ? (
                                    <p className="mb-2 text-lg font-semibold tabular-nums text-foreground">
                                        {formatPrice(paymentTotal)}
                                    </p>
                                ) : null}
                                <p className="text-xs leading-relaxed text-muted-foreground">
                                    {t('renew_hint')}
                                </p>
                                {summary?.bot_ia?.activo && summary.bot_ia.activado_at ? (
                                    <p className="mt-2 text-[11px] leading-relaxed text-muted-foreground">
                                        {t('bot_ia.activated_mid_cycle', {
                                            activated: formatDate(
                                                summary.bot_ia.activado_at,
                                                locale,
                                                true,
                                            ),
                                            renewal: formatDate(
                                                summary.bot_ia.proximo_cobro_at ??
                                                    summary.proximo_cobro_at ??
                                                    null,
                                                locale,
                                                true,
                                            ),
                                            price: formatPrice(
                                                summary.bot_ia.precio_mensual,
                                            ),
                                        })}
                                    </p>
                                ) : null}
                                <div className="mt-3">
                                    {summary?.renewal_url ? (
                                        <Button asChild size="sm" className="w-full sm:w-auto">
                                            <a
                                                href={summary.renewal_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                {t('renew_cta')}
                                                <ExternalLink className="size-3.5" />
                                            </a>
                                        </Button>
                                    ) : (
                                        <p className="text-xs text-muted-foreground">
                                            {t('no_renewal_url')}
                                        </p>
                                    )}
                                </div>
                            </PanelSection>
                        </div>
                    </div>
                    </div>

                    {comprobantes?.enabled ? (
                        <ComprobantesQuotaCard
                            quota={comprobantes}
                            locale={locale}
                            compact
                            className="xl:col-span-4"
                        />
                    ) : null}
                </div>

                <PlanUsageSection />
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
