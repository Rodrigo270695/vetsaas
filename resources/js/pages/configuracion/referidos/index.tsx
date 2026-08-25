import { Head } from '@inertiajs/react';
import { ArrowRight, Check, Copy, MessageCircle } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/data-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';

type ReferredRow = {
    id: string;
    slug: string;
    name: string;
    estado: string;
    created_at: string | null;
    reward_status: 'credited' | 'pending_payment';
};

type LedgerRow = {
    id: string;
    days: number;
    type: string;
    notes: string | null;
    created_at: string | null;
};

type RewardByPlan = {
    codigo: string;
    nombre: string;
    days: number;
    label: string;
};

type ReferralPayload = {
    referral_code: string;
    share_url: string;
    reward_days: number | null;
    rewards_by_plan: RewardByPlan[];
    days_balance: number;
    referred: ReferredRow[];
    ledger: LedgerRow[];
};

type Props = {
    referral: ReferralPayload;
};

function formatDate(value: string | null, locale: string): string {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleDateString(locale === 'en' ? 'en-US' : 'es-PE', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

export default function Index({ referral }: Props) {
    const { t, i18n } = useTranslation('config-referidos');
    const locale = i18n.language?.startsWith('en') ? 'en' : 'es';
    const [copied, setCopied] = useState<'code' | 'url' | null>(null);

    const creditedCount = useMemo(
        () => referral.referred.filter((r) => r.reward_status === 'credited').length,
        [referral.referred],
    );

    const maxRewardDays = useMemo(() => {
        const days = referral.rewards_by_plan.map((p) => p.days);
        return days.length > 0 ? Math.max(...days, 1) : 1;
    }, [referral.rewards_by_plan]);

    const copy = async (kind: 'code' | 'url', value: string) => {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(kind);
            window.setTimeout(() => setCopied(null), 1800);
        } catch {
            // ignore
        }
    };

    const waText = encodeURIComponent(
        t('whatsapp_message', {
            code: referral.referral_code,
            url: referral.share_url,
        }),
    );

    return (
        <>
            <Head title={t('title')} />
            <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
                <PageHeader title={t('title')} description={t('description')} />

                <div className="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(280px,0.9fr)]">
                    {/* Invite stage — primary composition */}
                    <section className="relative overflow-hidden rounded-3xl bg-primary text-primary-foreground shadow-lg">
                        <div className="pointer-events-none absolute inset-0 opacity-25 [background-image:radial-gradient(circle_at_20%_20%,white,transparent_35%),radial-gradient(circle_at_90%_10%,white,transparent_28%),radial-gradient(circle_at_80%_80%,white,transparent_40%)]" />
                        <div className="relative flex h-full flex-col justify-between gap-8 p-6 sm:p-8">
                            <div className="space-y-3">
                                <p className="text-xs font-semibold uppercase tracking-[0.22em] text-primary-foreground/75">
                                    {t('invite_kicker')}
                                </p>
                                <h2 className="max-w-md text-3xl font-semibold tracking-tight sm:text-4xl">
                                    {t('invite_title')}
                                </h2>
                                <p className="max-w-lg text-sm leading-relaxed text-primary-foreground/85 sm:text-base">
                                    {t('invite_body')}
                                </p>
                            </div>

                            <div className="space-y-4">
                                <div>
                                    <p className="mb-2 text-xs font-medium uppercase tracking-wide text-primary-foreground/70">
                                        {t('your_code')}
                                    </p>
                                    <div className="flex flex-col gap-3 rounded-2xl bg-black/15 p-4 backdrop-blur-sm sm:flex-row sm:items-center sm:justify-between">
                                        <code className="break-all text-xl font-bold tracking-[0.14em] sm:text-2xl">
                                            {referral.referral_code}
                                        </code>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="secondary"
                                            className="shrink-0 bg-white text-foreground hover:bg-white/90"
                                            onClick={() =>
                                                copy('code', referral.referral_code)
                                            }
                                        >
                                            {copied === 'code' ? (
                                                <Check className="size-4" />
                                            ) : (
                                                <Copy className="size-4" />
                                            )}
                                            {copied === 'code'
                                                ? t('copied')
                                                : t('copy_code')}
                                        </Button>
                                    </div>
                                </div>

                                <div className="rounded-2xl border border-white/20 bg-black/10 px-4 py-3">
                                    <p className="break-all font-mono text-xs text-primary-foreground/80 sm:text-sm">
                                        {referral.share_url}
                                    </p>
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        className="bg-white/15 text-primary-foreground hover:bg-white/25"
                                        onClick={() => copy('url', referral.share_url)}
                                    >
                                        {copied === 'url' ? (
                                            <Check className="size-4" />
                                        ) : (
                                            <Copy className="size-4" />
                                        )}
                                        {copied === 'url' ? t('copied') : t('copy_link')}
                                    </Button>
                                    <Button
                                        type="button"
                                        className="bg-[#25D366] text-white hover:bg-[#1ebe57]"
                                        asChild
                                    >
                                        <a
                                            href={`https://wa.me/?text=${waText}`}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <MessageCircle className="size-4" />
                                            {t('share_whatsapp')}
                                        </a>
                                    </Button>
                                </div>
                            </div>

                            <p className="flex flex-wrap items-center gap-2 text-xs text-primary-foreground/70">
                                <span className="font-semibold uppercase tracking-wide">
                                    {t('flow_label')}
                                </span>
                                <ArrowRight className="size-3 opacity-60" />
                                <span>{t('flow')}</span>
                            </p>
                        </div>
                    </section>

                    {/* Side rail: balance + reward ladder */}
                    <aside className="flex flex-col gap-4">
                        <div className="rounded-3xl border bg-card p-5 shadow-sm">
                            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                                {t('balance_title')}
                            </p>
                            <p className="mt-3 text-5xl font-semibold tabular-nums tracking-tight">
                                {referral.days_balance}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {t('days')} ·{' '}
                                {referral.days_balance > 0
                                    ? t('balance_ready')
                                    : t('balance_empty')}
                            </p>
                            <div className="mt-5 grid grid-cols-2 gap-2">
                                <div className="rounded-2xl bg-muted/60 px-3 py-3">
                                    <p className="text-[11px] uppercase tracking-wide text-muted-foreground">
                                        {t('stats_invited')}
                                    </p>
                                    <p className="mt-1 text-2xl font-semibold tabular-nums">
                                        {referral.referred.length}
                                    </p>
                                </div>
                                <div className="rounded-2xl bg-muted/60 px-3 py-3">
                                    <p className="text-[11px] uppercase tracking-wide text-muted-foreground">
                                        {t('stats_credited')}
                                    </p>
                                    <p className="mt-1 text-2xl font-semibold tabular-nums">
                                        {creditedCount}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {referral.rewards_by_plan.length > 0 && (
                            <div className="rounded-3xl border bg-card p-5 shadow-sm">
                                <div className="mb-4">
                                    <p className="text-sm font-semibold">
                                        {t('rewards_title')}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {t('rewards_hint')}
                                    </p>
                                </div>
                                <ul className="space-y-4">
                                    {referral.rewards_by_plan.map((plan) => (
                                        <li key={plan.codigo} className="space-y-1.5">
                                            <div className="flex items-baseline justify-between gap-3">
                                                <span className="text-sm font-medium">
                                                    {plan.nombre}
                                                </span>
                                                <span className="text-sm font-semibold tabular-nums text-primary">
                                                    {plan.label}
                                                </span>
                                            </div>
                                            <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full rounded-full bg-primary/80"
                                                    style={{
                                                        width: `${Math.max(
                                                            12,
                                                            Math.round(
                                                                (plan.days / maxRewardDays) *
                                                                    100,
                                                            ),
                                                        )}%`,
                                                    }}
                                                />
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </aside>
                </div>

                <section className="overflow-hidden rounded-3xl border bg-card shadow-sm">
                    <div className="flex items-center justify-between gap-3 border-b px-5 py-4">
                        <h2 className="text-sm font-semibold">{t('referred_title')}</h2>
                        <Badge variant="secondary" className="tabular-nums">
                            {t('referred_count', { count: referral.referred.length })}
                        </Badge>
                    </div>

                    {referral.referred.length === 0 ? (
                        <div className="flex flex-col items-start gap-3 px-5 py-8 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-sm text-muted-foreground">
                                {t('referred_empty')}
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => copy('url', referral.share_url)}
                            >
                                {copied === 'url' ? (
                                    <Check className="size-4" />
                                ) : (
                                    <Copy className="size-4" />
                                )}
                                {copied === 'url' ? t('copied') : t('referred_empty_cta')}
                            </Button>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/40 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-5 py-2.5 font-medium">
                                            {t('col_clinic')}
                                        </th>
                                        <th className="px-5 py-2.5 font-medium">
                                            {t('col_date')}
                                        </th>
                                        <th className="px-5 py-2.5 font-medium">
                                            {t('col_status')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {referral.referred.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-t transition-colors hover:bg-muted/25"
                                        >
                                            <td className="px-5 py-3">
                                                <div className="font-medium">{row.name}</div>
                                                <div className="text-xs text-muted-foreground">
                                                    {row.slug}
                                                </div>
                                            </td>
                                            <td className="px-5 py-3 tabular-nums">
                                                {formatDate(row.created_at, locale)}
                                            </td>
                                            <td className="px-5 py-3">
                                                <Badge
                                                    variant="outline"
                                                    className={cn(
                                                        row.reward_status === 'credited'
                                                            ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                                            : 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300',
                                                    )}
                                                >
                                                    {row.reward_status === 'credited'
                                                        ? t('status_credited')
                                                        : t('status_pending')}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                {referral.ledger.length > 0 && (
                    <section className="overflow-hidden rounded-3xl border bg-card shadow-sm">
                        <div className="border-b px-5 py-4 text-sm font-semibold">
                            {t('ledger_title')}
                        </div>
                        <ul className="divide-y">
                            {referral.ledger.map((entry) => (
                                <li
                                    key={entry.id}
                                    className="flex items-start justify-between gap-3 px-5 py-3.5 text-sm"
                                >
                                    <div>
                                        <div className="font-medium">
                                            {t(`ledger_type.${entry.type}`, {
                                                defaultValue: entry.type,
                                            })}
                                        </div>
                                        {entry.notes && (
                                            <div className="text-muted-foreground">
                                                {entry.notes}
                                            </div>
                                        )}
                                        <div className="text-xs text-muted-foreground">
                                            {formatDate(entry.created_at, locale)}
                                        </div>
                                    </div>
                                    <div
                                        className={cn(
                                            'rounded-full px-2.5 py-1 text-xs font-semibold tabular-nums',
                                            entry.days >= 0
                                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                                : 'bg-muted text-muted-foreground',
                                        )}
                                    >
                                        {entry.days > 0 ? `+${entry.days}` : entry.days}{' '}
                                        {t('days')}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Configuración' },
            {
                title: 'Referidos',
                href: '/configuracion/referidos',
            },
        ]}
    >
        {page}
    </AppLayout>
);
