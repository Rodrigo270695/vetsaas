import { TZDate } from '@date-fns/tz';
import { format } from 'date-fns';
import { Pencil, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dateKeyInAppTimezone, formatTimeOnlyInAppTimezone } from '../../historias-clinicas/format-atendido';
import type { InternamientoEvolucionRow } from '../types';

const ZONA_PERU = 'America/Lima';

function zonaPeru(timeZone: string | undefined): string {
    const zona = (timeZone ?? '').trim();

    if (zona === '' || zona.toUpperCase() === 'UTC' || zona === 'Etc/UTC') {
        return ZONA_PERU;
    }

    return zona;
}

function fechaCabecera(iso: string, timeZone: string): string {
    try {
        const d = new TZDate(iso, timeZone);

        if (Number.isNaN(d.getTime())) {
            return '—';
        }

        return format(d, 'dd/MM/yyyy');
    } catch {
        return '—';
    }
}

type ParamKey =
    | 'temperatura_c'
    | 'fc_lpm'
    | 'fr_rpm'
    | 'peso_kg'
    | 'deshidratacion_pct'
    | 'tllc_segundos'
    | 'pas'
    | 'pad'
    | 'pam';

const PARAMETROS: readonly { key: ParamKey; labelKey: string }[] = [
    { key: 'temperatura_c', labelKey: 'evolucion.row_temperatura' },
    { key: 'fc_lpm', labelKey: 'evolucion.row_lpm' },
    { key: 'fr_rpm', labelKey: 'evolucion.row_rpm' },
    { key: 'peso_kg', labelKey: 'evolucion.row_kg' },
    { key: 'deshidratacion_pct', labelKey: 'evolucion.row_deshidratacion' },
    { key: 'tllc_segundos', labelKey: 'evolucion.row_tllc' },
    { key: 'pas', labelKey: 'evolucion.row_pas' },
    { key: 'pad', labelKey: 'evolucion.row_pad' },
    { key: 'pam', labelKey: 'evolucion.row_pam' },
];

type DaySheet = {
    dayKey: string;
    label: string;
    columnas: InternamientoEvolucionRow[];
};

function valorCelda(row: InternamientoEvolucionRow, key: ParamKey): string {
    const value = row[key];

    if (value == null || value === '') {
        return '—';
    }

    return String(value);
}

type Props = {
    evoluciones: readonly InternamientoEvolucionRow[];
    timeZone?: string;
    canUpdate: boolean;
    onEdit: (evolucion: InternamientoEvolucionRow) => void;
    onDelete: (evolucion: InternamientoEvolucionRow) => void;
};

export function ConstantesFisiologicas({
    evoluciones,
    timeZone,
    canUpdate,
    onEdit,
    onDelete,
}: Props) {
    const { t } = useTranslation(['hospitalizacion', 'common']);
    const zona = zonaPeru(timeZone);

    const dias = useMemo((): DaySheet[] => {
        const groups = new Map<string, InternamientoEvolucionRow[]>();

        for (const item of evoluciones) {
            const dayKey = dateKeyInAppTimezone(item.registrado_at, zona) || '—';
            const list = groups.get(dayKey) ?? [];
            list.push(item);
            groups.set(dayKey, list);
        }

        return [...groups.entries()]
            .sort(([a], [b]) => b.localeCompare(a))
            .map(([dayKey, columnas]) => {
                const ordenadas = [...columnas].sort((a, b) =>
                    a.registrado_at.localeCompare(b.registrado_at),
                );

                return {
                    dayKey,
                    label: fechaCabecera(ordenadas[0].registrado_at, zona),
                    columnas: ordenadas,
                };
            });
    }, [evoluciones, zona]);

    const esquina = 'sticky left-0 z-10 bg-card';

    return (
        <div className="overflow-x-auto">
            <table className="border-separate border-spacing-0 text-sm">
                <thead>
                    {dias.length > 0 ? (
                        <tr>
                            <th className={cn(esquina, 'border-b border-border/50')} />
                            {dias.map((dia) => (
                                <th
                                    key={dia.dayKey}
                                    colSpan={dia.columnas.length}
                                    className="border-b border-l border-border/50 px-3 pt-1 pb-2 text-center"
                                >
                                    <span className="inline-flex rounded-md bg-muted px-2.5 py-1 text-xs font-semibold tracking-wide text-foreground tabular-nums">
                                        {dia.label}
                                    </span>
                                </th>
                            ))}
                        </tr>
                    ) : null}
                    <tr>
                        <th
                            className={cn(
                                esquina,
                                'min-w-44 border-b border-border/70 px-1 py-2 pr-8 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase',
                            )}
                        >
                            {t('show.constantes_parametro')}
                        </th>
                        {dias.map((dia) =>
                            dia.columnas.map((columna, indice) => (
                                <th
                                    key={columna.id}
                                    className={cn(
                                        'min-w-36 border-b border-border/70 px-3 py-2 whitespace-nowrap',
                                        indice === 0 && 'border-l border-border/50',
                                    )}
                                >
                                    <div className="flex items-center justify-center gap-1.5">
                                        <span className="text-sm font-semibold text-foreground tabular-nums">
                                            {formatTimeOnlyInAppTimezone(columna.registrado_at, zona)}
                                        </span>
                                        {canUpdate ? (
                                            <span className="inline-flex items-center">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-6 cursor-pointer rounded-full text-primary transition-colors duration-150 hover:bg-primary/12"
                                                    aria-label={t('common:actions.edit')}
                                                    onClick={() => onEdit(columna)}
                                                >
                                                    <Pencil className="size-3.5" strokeWidth={2.25} />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-6 cursor-pointer rounded-full text-destructive transition-colors duration-150 hover:bg-destructive/12"
                                                    aria-label={t('common:actions.delete')}
                                                    onClick={() => onDelete(columna)}
                                                >
                                                    <Trash2 className="size-3.5" strokeWidth={2.25} />
                                                </Button>
                                            </span>
                                        ) : null}
                                    </div>
                                </th>
                            )),
                        )}
                    </tr>
                </thead>
                <tbody>
                    {PARAMETROS.map((parametro) => (
                        <tr key={parametro.key} className="group">
                            <th
                                className={cn(
                                    esquina,
                                    'border-b border-border/40 px-1 py-2.5 pr-8 text-left text-[13px] font-medium text-muted-foreground group-last:border-b-0 group-hover:bg-muted/40 group-hover:text-foreground',
                                )}
                            >
                                {t(parametro.labelKey)}
                            </th>
                            {dias.map((dia) =>
                                dia.columnas.map((columna, indice) => {
                                    const valor = valorCelda(columna, parametro.key);
                                    const vacio = valor === '—';

                                    return (
                                        <td
                                            key={`${columna.id}-${parametro.key}`}
                                            className={cn(
                                                'border-b border-border/40 px-3 py-2.5 text-center whitespace-nowrap group-last:border-b-0 group-hover:bg-muted/30',
                                                indice === 0 && 'border-l border-border/50',
                                                vacio
                                                    ? 'text-muted-foreground/35'
                                                    : 'font-semibold text-foreground tabular-nums',
                                            )}
                                        >
                                            {valor}
                                        </td>
                                    );
                                }),
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
