import { Head } from '@inertiajs/react';
import {
    Check,
    Copy,
    Gift,
    Link2,
    PartyPopper,
    Share2,
    Sparkles,
    Users,
    Wallet,
} from 'lucide-react';
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

const PLAN_ACCENTS: Record<string, string> = {
    starter:
        'from-sky-500/15 via-sky-500/5 to-transparent border-sky-500/25 dark:from-sky-400/20',
    pro: 'from-amber-500/15 via-amber-500/5 to-transparent border-amber-500/25 dark:from-amber-400/20',
    clinica:
        'from-emerald-500/15 via-emerald-500/5 to-transparent border-emerald-500/25 dark:from-emerald-400/20',
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

    const steps = [
        {
            icon: Share2,
            title: t('how_step_1_title'),
            body: t('how_step_1_body'),
        },
        {
            icon: Users,
            title: t('how_step_2_title'),
            body: t('how_step_2_body'),
        },
        {
            icon: Gift,
            title: t('how_step_3_title'),
            body: t('how_step_3_body'),
        },
    ] as const;

    return (
        <>
            <Head title={t('title')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title={t('title')} description={t('description')} />

                <section className="relative overflow-hidden rounded-2xl border border-primary/20 bg-gradient-to-br from-primary/12 via-background to-emerald-500/5 p-5 shadow-sm md:p-6">
                    <div className="pointer-events-none absolute -right-10 -top-10 size-40 rounded-full bg-primary/10 blur-2xl" />
                    <div className="pointer-events-none absolute -bottom-12 left-1/3 size-36 rounded-full bg-emerald-400/10 blur-2xl" />
                    <div className="relative mb-4 flex items-center gap-2">
                        <span className="inline-flex size-9 items-center justify-center rounded-xl bg-primary/15 text-primary">
                            <Sparkles className="size-4" />
                        </span>
                        <h2 className="text-sm font-semibold tracking-tight">
                            {t('how_title')}
                        </h2>
                    </div>
                    <ol className="relative grid gap-3 md:grid-cols-3">
                        {steps.map((step, index) => {
                            const Icon = step.icon;
                            return (
                                <li
                                    key={step.title}
                                    className="rounded-xl border bg-background/80 p-4 backdrop-blur-sm"
                                >
                                    <div className="mb-3 flex items-center gap-2">
                                        <span className="inline-flex size-8 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
                                            {index + 1}
                                        </span>
                                        <span className="inline-flex size-8 items-center justify-center rounded-lg bg-muted text-primary">
                                            <Icon className="size-4" />
                                        </span>
                                    </div>
                                    <p className="text-sm font-semibold">{step.title}</p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {step.body}
                                    </p>
                                </li>
                            );
                        })}
                    </ol>
                </section>

                {referral.rewards_by_plan.length > 0 && (
                    <section className="space-y-3">
                        <div>
                            <h2 className="text-sm font-semibold">{t('rewards_title')}</h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {t('rewards_hint')}
                            </p>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-3">
                            {referral.rewards_by_plan.map((plan) => (
                                <div
                                    key={plan.codigo}
                                    className={cn(
                                        'relative overflow-hidden rounded-xl border bg-gradient-to-br p-4',
                                        PLAN_ACCENTS[plan.codigo] ??
                                            'from-primary/10 via-primary/5 to-transparent border-primary/20',
                                    )}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <p className="text-sm font-medium text-muted-foreground">
                                                {plan.nombre}
                                            </p>
                                            <p className="mt-1 text-2xl font-semibold tabular-nums tracking-tight">
                                                {plan.label}
                                            </p>
                                        </div>
                                        <span className="inline-flex size-9 items-center justify-center rounded-full bg-background/70 text-primary shadow-sm">
                                            <Gift className="size-4" />
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                <div className="grid gap-4 lg:grid-cols-5">
                    <section className="rounded-2xl border bg-card p-5 shadow-sm lg:col-span-3">
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                            <div className="flex items-center gap-2">
                                <span className="inline-flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <Link2 className="size-4" />
                                </span>
                                <div>
                                    <h2 className="text-sm font-semibold">{t('your_code')}</h2>
                                    <p className="text-xs text-muted-foreground">
                                        {t('code_ready')}
                                    </p>
                                </div>
                            </div>
                            <Badge variant="outline" className="border-primary/30 text-primary">
                                {referral.referral_code}
                            </Badge>
                        </div>

                        <div className="rounded-xl border border-dashed bg-muted/40 px-4 py-5 text-center">
                            <code className="text-2xl font-bold tracking-[0.12em] md:text-3xl">
                                {referral.referral_code}
                            </code>
                            <div className="mt-4 flex flex-wrap items-center justify-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => copy('code', referral.referral_code)}
                                >
                                    {copied === 'code' ? (
                                        <Check className="size-4" />
                                    ) : (
                                        <Copy className="size-4" />
                                    )}
                                    {copied === 'code' ? t('copied') : t('copy_code')}
                                </Button>
                            </div>
                        </div>

                        <div className="mt-4 rounded-xl bg-muted/30 px-4 py-3">
                            <p className="break-all text-sm text-muted-foreground">
                                {referral.share_url}
                            </p>
                            <div className="mt-3 flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
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
                                    size="sm"
                                    className="bg-[#25D366] text-white hover:bg-[#1ebe57]"
                                    asChild
                                >
                                    <a
                                        href={`https://wa.me/?text=${waText}`}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <Share2 className="size-4" />
                                        {t('share_whatsapp')}
                                    </a>
                                </Button>
                            </div>
                        </div>
                    </section>

                    <section
                        className={cn(
                            'relative overflow-hidden rounded-2xl border p-5 shadow-sm lg:col-span-2',
                            referral.days_balance > 0
                                ? 'border-emerald-500/30 bg-gradient-to-br from-emerald-500/15 via-background to-primary/5'
                                : 'bg-card',
                        )}
                    >
                        <div className="mb-3 flex items-center gap-2">
                            <span
                                className={cn(
                                    'inline-flex size-9 items-center justify-center rounded-xl',
                                    referral.days_balance > 0
                                        ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                                        : 'bg-muted text-muted-foreground',
                                )}
                            >
                                {referral.days_balance > 0 ? (
                                    <PartyPopper className="size-4" />
                                ) : (
                                    <Wallet className="size-4" />
                                )}
                            </span>
                            <h2 className="text-sm font-semibold">{t('balance_title')}</h2>
                        </div>
                        <p className="text-4xl font-bold tabular-nums tracking-tight">
                            {referral.days_balance}
                            <span className="ml-1.5 text-base font-medium text-muted-foreground">
                                {t('days')}
                            </span>
                        </p>
                        <p className="mt-2 text-sm font-medium">
                            {referral.days_balance > 0
                                ? t('balance_ready')
                                : t('balance_empty')}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('balance_hint')}
                        </p>
                        <div className="mt-4 flex flex-wrap gap-2 text-xs text-muted-foreground">
                            <Badge variant="secondary">
                                {t('referred_count', {
                                    count: referral.referred.length,
                                })}
                            </Badge>
                            {creditedCount > 0 && (
                                <Badge
                                    variant="outline"
                                    className="border-emerald-500/40 text-emerald-700 dark:text-emerald-300"
                                >
                                    {creditedCount} ✓
                                </Badge>
                            )}
                        </div>
                    </section>
                </div>

                <section className="rounded-2xl border bg-card shadow-sm">
                    <div className="flex items-center justify-between gap-3 border-b px-5 py-3.5">
                        <div className="flex items-center gap-2">
                            <Users className="size-4 text-primary" />
                            <h2 className="text-sm font-semibold">{t('referred_title')}</h2>
                        </div>
                        {referral.referred.length > 0 && (
                            <Badge variant="secondary">
                                {t('referred_count', {
                                    count: referral.referred.length,
                                })}
                            </Badge>
                        )}
                    </div>
                    {referral.referred.length === 0 ? (
                        <div className="flex flex-col items-center px-5 py-10 text-center">
                            <span className="mb-3 inline-flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                <Users className="size-7" />
                            </span>
                            <p className="max-w-sm text-sm text-muted-foreground">
                                {t('referred_empty')}
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="mt-4"
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
                                            className="border-t transition-colors hover:bg-muted/30"
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
                    <section className="rounded-2xl border bg-card shadow-sm">
                        <div className="border-b px-5 py-3.5 text-sm font-semibold">
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
                                            'rounded-lg px-2.5 py-1 tabular-nums font-semibold',
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
