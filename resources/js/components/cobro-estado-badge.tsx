import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type EstadoCobro =
    | 'sin_precuenta'
    | 'precuenta_borrador'
    | 'precuenta_lista'
    | 'cobrado';

const STYLES: Record<EstadoCobro, string> = {
    sin_precuenta:
        'border-slate-300/70 bg-slate-50 text-slate-700 dark:border-slate-600/50 dark:bg-slate-900/40 dark:text-slate-300',
    precuenta_borrador:
        'border-amber-500/40 bg-amber-500/10 text-amber-900 dark:text-amber-200',
    precuenta_lista:
        'border-sky-500/40 bg-sky-500/10 text-sky-900 dark:text-sky-200',
    cobrado:
        'border-emerald-500/45 bg-emerald-500/10 text-emerald-900 dark:text-emerald-200',
};

type Props = {
    estado: EstadoCobro | string | null | undefined;
    className?: string;
    /** Si es true, no muestra badge cuando aún no hay precuenta (reduce ruido). */
    hideSinPrecuenta?: boolean;
};

export function CobroEstadoBadge({ estado, className, hideSinPrecuenta = false }: Props) {
    const { t } = useTranslation('consulta-cargos');

    if (estado == null || estado === '') {
        return null;
    }

    if (hideSinPrecuenta && estado === 'sin_precuenta') {
        return null;
    }

    const key = (['sin_precuenta', 'precuenta_borrador', 'precuenta_lista', 'cobrado'].includes(
        estado,
    )
        ? estado
        : 'sin_precuenta') as EstadoCobro;

    return (
        <Badge
            variant="outline"
            className={cn(
                'whitespace-nowrap text-[0.65rem] font-normal',
                STYLES[key],
                className,
            )}
            title={t(`estado_cobro.${key}_hint`)}
        >
            {t(`estado_cobro.${key}`)}
        </Badge>
    );
}
