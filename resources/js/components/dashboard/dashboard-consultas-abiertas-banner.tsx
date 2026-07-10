import { Link } from '@inertiajs/react';
import { AlertTriangle, ChevronRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import clinica from '@/routes/clinica';

type Props = {
    abiertas: number;
    antiguas: number;
};

export function DashboardConsultasAbiertasBanner({ abiertas, antiguas }: Props) {
    const { t } = useTranslation('dashboard');

    if (abiertas <= 0) {
        return null;
    }

    const historiasUrl = clinica.historiasClinicas.url({
        query: { solo_abiertas: '1' },
    });

    return (
        <section className="overflow-hidden rounded-xl border border-amber-200/70 bg-linear-to-r from-amber-50/90 via-card to-card shadow-sm dark:border-amber-800/35 dark:from-amber-950/25">
            <div className="flex flex-wrap items-center gap-x-4 gap-y-3 px-4 py-3">
                <div className="flex min-w-0 flex-1 items-start gap-3">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200">
                        <AlertTriangle className="size-4" aria-hidden />
                    </div>
                    <div className="min-w-0">
                        <h2 className="text-sm font-semibold text-foreground">
                            {t('consultas_abiertas_banner.title', { count: abiertas })}
                        </h2>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            {antiguas > 0
                                ? t('consultas_abiertas_banner.subtitle_antiguas', { count: antiguas })
                                : t('consultas_abiertas_banner.subtitle')}
                        </p>
                    </div>
                </div>
                <Button asChild size="sm" variant="outline" className="h-8 shrink-0 cursor-pointer gap-1">
                    <Link href={historiasUrl}>
                        {t('consultas_abiertas_banner.cta')}
                        <ChevronRight className="size-3.5" aria-hidden />
                    </Link>
                </Button>
            </div>
        </section>
    );
}
