import { Link } from '@inertiajs/react';
import {
    Bot,
    CalendarCheck,
    MessageCircle,
    Sparkles,
    UserPlus,
    Zap,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { AnnouncementTypeBadge } from '@/pages/plataforma/bot-ia-announcements/components/announcement-type-badge';
import type { TenantAnnouncement } from '@/pages/plataforma/bot-ia-announcements/types';

type ActivationContact = {
    whatsapp_url: string;
    whatsapp_display: string;
};

type Props = {
    announcement: TenantAnnouncement;
    precioMensual: string;
    activationContact: ActivationContact;
};

const FEATURE_ICONS = [MessageCircle, UserPlus, CalendarCheck] as const;

function formatPrice(value: string): string {
    const num = Number(value);
    if (Number.isNaN(num)) {
        return value;
    }
    return num.toFixed(2);
}

function formatExpiry(value: string | null | undefined, locale: string): string | null {
    if (!value) {
        return null;
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return null;
    }
    return date.toLocaleDateString(locale === 'en' ? 'en-US' : 'es-PE', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}

export function BotIaPromoPanel({ announcement, precioMensual, activationContact }: Props) {
    const { t, i18n } = useTranslation('bot-ia');
    const price = formatPrice(precioMensual);
    const expiryLabel = formatExpiry(announcement.expires_at, i18n.language);

    return (
        <div className="overflow-hidden rounded-2xl border border-violet-500/20 bg-gradient-to-br from-violet-500/10 via-background to-emerald-500/5 shadow-sm">
            <div className="border-b border-violet-500/15 bg-violet-500/5 px-5 py-6 sm:px-8 sm:py-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex min-w-0 flex-1 gap-4">
                        <div className="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-violet-600 text-white shadow-lg shadow-violet-500/25">
                            <Bot className="size-7" strokeWidth={2} />
                        </div>
                        <div className="min-w-0 space-y-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <AnnouncementTypeBadge badge={announcement.badge} />
                                <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/15 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:text-amber-300">
                                    <Sparkles className="size-3" />
                                    {t('promo.badge_nuevo')}
                                </span>
                            </div>
                            <h2 className="text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                                {announcement.title}
                            </h2>
                            <p className="max-w-2xl text-sm leading-relaxed text-muted-foreground sm:text-base">
                                {t('promo.subtitle')}
                            </p>
                        </div>
                    </div>

                    <div className="shrink-0 rounded-xl border border-violet-500/25 bg-background/90 px-4 py-3 text-center shadow-sm">
                        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            {t('promo.price_label')}
                        </p>
                        <p className="mt-1 text-3xl font-bold text-violet-700 dark:text-violet-300">
                            S/. {price}
                        </p>
                        <p className="text-xs text-muted-foreground">{t('promo.price_hint')}</p>
                    </div>
                </div>
            </div>

            <div className="grid gap-4 px-5 py-6 sm:grid-cols-3 sm:px-8">
                {announcement.bullets.map((bullet, index) => {
                    const Icon = FEATURE_ICONS[index] ?? Zap;
                    return (
                        <div
                            key={bullet}
                            className="rounded-xl border bg-background/80 p-4 shadow-sm"
                        >
                            <div className="mb-3 flex size-9 items-center justify-center rounded-lg bg-violet-500/10 text-violet-600">
                                <Icon className="size-4.5" strokeWidth={2.25} />
                            </div>
                            <p className="text-sm leading-relaxed text-foreground">{bullet}</p>
                        </div>
                    );
                })}
            </div>

            {(announcement.guide_title || announcement.guide_body || announcement.guide_tips.length > 0) ? (
                <div className="border-t border-violet-500/10 bg-muted/20 px-5 py-5 sm:px-8">
                    {announcement.guide_title ? (
                        <h3 className="text-sm font-semibold text-foreground">{announcement.guide_title}</h3>
                    ) : null}
                    {announcement.guide_body ? (
                        <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                            {announcement.guide_body}
                        </p>
                    ) : null}
                    {announcement.guide_tips.length > 0 ? (
                        <ol className="mt-4 space-y-2">
                            {announcement.guide_tips.map((tip, index) => (
                                <li
                                    key={tip}
                                    className="flex gap-3 text-sm text-muted-foreground"
                                >
                                    <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-violet-500/15 text-xs font-bold text-violet-700 dark:text-violet-300">
                                        {index + 1}
                                    </span>
                                    <span className="pt-0.5 leading-relaxed">{tip}</span>
                                </li>
                            ))}
                        </ol>
                    ) : null}
                </div>
            ) : null}

            <div className="flex flex-col gap-4 border-t border-violet-500/10 bg-background/60 px-5 py-5 sm:px-8">
                <div className="space-y-1">
                    <p className="text-sm font-medium text-foreground">{t('promo.cta_title')}</p>
                    <p className="text-xs text-muted-foreground">
                        {expiryLabel
                            ? t('promo.expires_hint', { date: expiryLabel })
                            : t('promo.persistent_hint')}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {t('promo.whatsapp_hint', { phone: activationContact.whatsapp_display })}
                    </p>
                </div>

                <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <Button asChild size="lg" className="gap-2 bg-emerald-600 shadow-md hover:bg-emerald-700">
                        <a
                            href={activationContact.whatsapp_url}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <MessageCircle className="size-4" />
                            {t('promo.cta_whatsapp')}
                        </a>
                    </Button>
                    <Button asChild variant="outline" size="lg">
                        <Link href="/configuracion/suscripcion">{t('promo.cta_plan_secondary')}</Link>
                    </Button>
                </div>
            </div>
        </div>
    );
}
