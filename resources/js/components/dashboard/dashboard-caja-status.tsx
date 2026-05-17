import { DoorClosed, DoorOpen } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

type Props = {
    abierta: boolean;
};

export function DashboardCajaStatus({ abierta }: Props) {
    const { t } = useTranslation('dashboard');
    const Icon = abierta ? DoorOpen : DoorClosed;

    return (
        <div
            className={cn(
                'flex items-center gap-3 rounded-xl border px-4 py-3 text-sm shadow-sm',
                abierta
                    ? 'border-emerald-300/50 bg-gradient-to-r from-emerald-50 to-emerald-50/40 text-emerald-900 dark:border-emerald-700/40 dark:from-emerald-950/40 dark:to-emerald-950/20 dark:text-emerald-100'
                    : 'border-border/70 bg-muted/30 text-muted-foreground',
            )}
            role="status"
        >
            <div
                className={cn(
                    'flex size-9 items-center justify-center rounded-lg',
                    abierta
                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50'
                        : 'bg-muted text-muted-foreground',
                )}
            >
                <Icon className="size-4" aria-hidden />
            </div>
            <div>
                <p className="font-medium">
                    {abierta ? t('kpis.caja_abierta') : t('kpis.caja_cerrada')}
                </p>
                <p className="text-xs opacity-80">
                    {abierta ? t('hero.caja_abierta_hint') : t('hero.caja_cerrada_hint')}
                </p>
            </div>
        </div>
    );
}
