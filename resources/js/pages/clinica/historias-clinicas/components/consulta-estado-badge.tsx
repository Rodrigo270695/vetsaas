import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { isConsultaAbiertaAntigua } from '../consulta-estado-utils';

type Props = {
    cerradaAt: string | null;
    atendidoAt?: string;
    /** Muestra la leyenda «+24 h abierta» debajo del badge. */
    showAntiguaHint?: boolean;
    className?: string;
};

function StatusDot({ className }: { className?: string }) {
    return (
        <span
            className={cn('size-1.5 shrink-0 rounded-full', className)}
            aria-hidden
        />
    );
}

export function ConsultaEstadoBadge({
    cerradaAt,
    atendidoAt,
    showAntiguaHint = false,
    className,
}: Props) {
    const { t } = useTranslation('historias-clinicas');
    const isCerrada = Boolean(cerradaAt);
    const antigua =
        !isCerrada && atendidoAt != null && isConsultaAbiertaAntigua(atendidoAt, cerradaAt);

    const badge = isCerrada ? (
        <Badge
            variant="outline"
            className={cn(
                'gap-1.5 border-emerald-300/80 bg-emerald-50 px-2 py-0.5 text-[0.65rem] font-semibold text-emerald-800 shadow-xs ring-1 ring-emerald-200/60',
                'dark:border-emerald-700/50 dark:bg-emerald-950/50 dark:text-emerald-200 dark:ring-emerald-800/40',
                className,
            )}
        >
            <StatusDot className="bg-emerald-500 dark:bg-emerald-400" />
            {t('row.estado_cerrada')}
        </Badge>
    ) : antigua ? (
        <Badge
            variant="outline"
            className={cn(
                'gap-1.5 border-rose-300/80 bg-rose-50 px-2 py-0.5 text-[0.65rem] font-semibold text-rose-800 shadow-xs ring-1 ring-rose-200/60',
                'dark:border-rose-700/50 dark:bg-rose-950/45 dark:text-rose-200 dark:ring-rose-800/40',
                className,
            )}
        >
            <StatusDot className="animate-pulse bg-rose-500 dark:bg-rose-400" />
            {t('row.estado_abierta')}
        </Badge>
    ) : (
        <Badge
            variant="outline"
            className={cn(
                'gap-1.5 border-amber-300/80 bg-amber-50 px-2 py-0.5 text-[0.65rem] font-semibold text-amber-900 shadow-xs ring-1 ring-amber-200/70',
                'dark:border-amber-700/50 dark:bg-amber-950/45 dark:text-amber-100 dark:ring-amber-800/40',
                className,
            )}
        >
            <StatusDot className="animate-pulse bg-amber-500 dark:bg-amber-400" />
            {t('row.estado_abierta')}
        </Badge>
    );

    if (!showAntiguaHint || !antigua) {
        return badge;
    }

    return (
        <div className="flex flex-col gap-1">
            {badge}
            <span className="text-[0.65rem] font-medium text-rose-700 dark:text-rose-300">
                {t('row.antigua_hint')}
            </span>
        </div>
    );
}
