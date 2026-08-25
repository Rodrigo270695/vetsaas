import { Head } from '@inertiajs/react';
import { Check, Copy, Gift, Share2 } from 'lucide-react';
import { useState } from 'react';
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

type ReferralPayload = {
    referral_code: string;
    share_url: string;
    reward_days: number;
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
            days: referral.reward_days,
        }),
    );

    return (
        <>
            <Head title={t('title')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('title')}
                    description={t('description', { days: referral.reward_days })}
                />

                <div className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-xl border bg-card p-5 md:col-span-2">
                        <div className="mb-3 flex items-center gap-2 text-sm font-medium">
                            <Share2 className="size-4 text-primary" />
                            {t('your_code')}
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <code className="rounded-lg bg-muted px-3 py-2 text-lg font-semibold tracking-wide">
                                {referral.referral_code}
                            </code>
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
                                {t('copy_code')}
                            </Button>
                        </div>
                        <p className="mt-4 break-all text-sm text-muted-foreground">
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
                                {t('copy_link')}
                            </Button>
                            <Button type="button" size="sm" asChild>
                                <a
                                    href={`https://wa.me/?text=${waText}`}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    {t('share_whatsapp')}
                                </a>
                            </Button>
                        </div>
                    </div>

                    <div className="rounded-xl border bg-card p-5">
                        <div className="mb-2 flex items-center gap-2 text-sm font-medium">
                            <Gift className="size-4 text-primary" />
                            {t('balance_title')}
                        </div>
                        <p className="text-3xl font-semibold tabular-nums">
                            {referral.days_balance}
                            <span className="ml-1 text-base font-normal text-muted-foreground">
                                {t('days')}
                            </span>
                        </p>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {t('balance_hint')}
                        </p>
                    </div>
                </div>

                <div className="rounded-xl border bg-card">
                    <div className="border-b px-5 py-3 text-sm font-medium">
                        {t('referred_title')}
                    </div>
                    {referral.referred.length === 0 ? (
                        <p className="px-5 py-8 text-sm text-muted-foreground">
                            {t('referred_empty')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/40 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-5 py-2 font-medium">
                                            {t('col_clinic')}
                                        </th>
                                        <th className="px-5 py-2 font-medium">
                                            {t('col_date')}
                                        </th>
                                        <th className="px-5 py-2 font-medium">
                                            {t('col_status')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {referral.referred.map((row) => (
                                        <tr key={row.id} className="border-t">
                                            <td className="px-5 py-3">
                                                <div className="font-medium">
                                                    {row.name}
                                                </div>
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
                                                            ? 'border-emerald-500/40 text-emerald-700 dark:text-emerald-300'
                                                            : 'border-amber-500/40 text-amber-700 dark:text-amber-300',
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
                </div>

                {referral.ledger.length > 0 && (
                    <div className="rounded-xl border bg-card">
                        <div className="border-b px-5 py-3 text-sm font-medium">
                            {t('ledger_title')}
                        </div>
                        <ul className="divide-y">
                            {referral.ledger.map((entry) => (
                                <li
                                    key={entry.id}
                                    className="flex items-start justify-between gap-3 px-5 py-3 text-sm"
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
                                            'tabular-nums font-semibold',
                                            entry.days >= 0
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {entry.days > 0 ? `+${entry.days}` : entry.days}{' '}
                                        {t('days')}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
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
