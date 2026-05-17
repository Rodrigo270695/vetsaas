import { usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { es, enUS } from 'date-fns/locale';
import { LayoutDashboard, Sparkles } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import type { Auth } from '@/types';

type Props = {
    clinicLabel: string;
};

function greetingKey(hour: number): 'hero.greeting_morning' | 'hero.greeting_afternoon' | 'hero.greeting_evening' {
    if (hour < 12) {
        return 'hero.greeting_morning';
    }

    if (hour < 19) {
        return 'hero.greeting_afternoon';
    }

    return 'hero.greeting_evening';
}

export function DashboardHero({ clinicLabel }: Props) {
    const { t, i18n } = useTranslation('dashboard');
    const { auth } = usePage<{ auth: Auth }>().props;
    const dateFnsLocale = i18n.language?.startsWith('en') ? enUS : es;

    const { greeting, dateLabel, userName } = useMemo(() => {
        const now = new Date();
        const firstName = auth.user?.name?.trim().split(/\s+/)[0] ?? '';

        return {
            greeting: t(greetingKey(now.getHours())),
            dateLabel: format(now, "EEEE d 'de' MMMM, yyyy", { locale: dateFnsLocale }),
            userName: firstName,
        };
    }, [auth.user?.name, dateFnsLocale, t]);

    const clinic = clinicLabel.trim() !== '' ? clinicLabel : t('hero.clinic_fallback');

    return (
        <section
            className="relative overflow-hidden rounded-2xl border border-brand-600/20 bg-gradient-to-br from-brand-700 via-brand-600 to-brand-800 px-6 py-7 text-white shadow-lg shadow-brand-900/15 md:px-8 md:py-8"
            aria-label={t('title')}
        >
            <div
                className="pointer-events-none absolute -right-16 -top-20 size-56 rounded-full bg-white/10 blur-2xl"
                aria-hidden
            />
            <div
                className="pointer-events-none absolute -bottom-24 left-1/3 size-72 rounded-full bg-brand-400/25 blur-3xl"
                aria-hidden
            />
            <div
                className="pointer-events-none absolute inset-0 opacity-[0.07]"
                style={{
                    backgroundImage:
                        'radial-gradient(circle at 1px 1px, white 1px, transparent 0)',
                    backgroundSize: '24px 24px',
                }}
                aria-hidden
            />

            <div className="relative flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                <div className="space-y-3">
                    <div className="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-medium backdrop-blur-sm ring-1 ring-white/20">
                        <LayoutDashboard className="size-3.5" aria-hidden />
                        {t('title')}
                    </div>
                    <div>
                        <p className="text-sm font-medium text-brand-100/90">
                            {greeting}
                            {userName !== '' ? `, ${userName}` : ''}
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight md:text-3xl">{clinic}</h1>
                        <p className="mt-2 max-w-xl text-sm leading-relaxed text-brand-50/85">
                            {t('hero.subtitle')}
                        </p>
                    </div>
                </div>

                <div className="flex shrink-0 flex-col items-start gap-2 md:items-end">
                    <div className="inline-flex items-center gap-2 rounded-xl bg-white/12 px-4 py-2.5 text-sm backdrop-blur-sm ring-1 ring-white/15">
                        <Sparkles className="size-4 text-brand-100" aria-hidden />
                        <span className="capitalize text-brand-50">{dateLabel}</span>
                    </div>
                </div>
            </div>
        </section>
    );
}
