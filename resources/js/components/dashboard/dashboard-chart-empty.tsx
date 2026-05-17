import { BarChart3 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

type Props = {
    message?: string;
};

export function DashboardChartEmpty({ message }: Props) {
    const { t } = useTranslation('dashboard');

    return (
        <div className="flex h-[280px] flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-border/80 bg-background/60 px-6 text-center">
            <div className="flex size-12 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-950/50 dark:text-brand-300">
                <BarChart3 className="size-6" aria-hidden />
            </div>
            <p className="max-w-xs text-sm text-muted-foreground">
                {message ?? t('charts.empty')}
            </p>
        </div>
    );
}
