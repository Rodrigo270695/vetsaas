import { TZDate } from '@date-fns/tz';
import { format } from 'date-fns';
import { Pencil, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
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

    const hojas = dias.length > 0 ? dias : [{ dayKey: 'vacio', label: '', columnas: [] }];

    return (
        <div className="flex flex-col gap-6">
            {hojas.map((dia) => (
                <section key={dia.dayKey} className="overflow-hidden rounded-xl border border-border/70">
                    {dia.label ? (
                        <header className="border-b border-border/60 bg-muted/40 px-4 py-2.5">
                            <h3 className="text-sm font-semibold text-foreground">{dia.label}</h3>
                        </header>
                    ) : null}
                    <div className="overflow-x-auto">
                        <table
                            className={`w-full border-collapse text-sm ${dia.columnas.length > 0 ? 'min-w-xl' : ''}`}
                        >
                            <thead>
                                <tr className="border-b border-border/60 bg-muted/20">
                                    <th className="sticky left-0 z-10 bg-muted/20 px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                                        {t('show.constantes_parametro')}
                                    </th>
                                    {dia.columnas.map((columna) => (
                                        <th
                                            key={columna.id}
                                            className="px-2 py-2 text-center text-xs font-semibold text-foreground"
                                        >
                                            <div className="flex items-center justify-center gap-1">
                                                <span>
                                                    {formatTimeOnlyInAppTimezone(
                                                        columna.registrado_at,
                                                        zona,
                                                    )}
                                                </span>
                                                {canUpdate ? (
                                                    <>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-6 cursor-pointer"
                                                            aria-label={t('common:actions.edit')}
                                                            onClick={() => onEdit(columna)}
                                                        >
                                                            <Pencil className="size-3" />
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-6 cursor-pointer text-destructive"
                                                            aria-label={t('common:actions.delete')}
                                                            onClick={() => onDelete(columna)}
                                                        >
                                                            <Trash2 className="size-3" />
                                                        </Button>
                                                    </>
                                                ) : null}
                                            </div>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {PARAMETROS.map((parametro) => (
                                    <tr key={parametro.key} className="border-b border-border/40 last:border-b-0">
                                        <th className="sticky left-0 z-10 bg-card px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                                            {t(parametro.labelKey)}
                                        </th>
                                        {dia.columnas.map((columna) => (
                                            <td
                                                key={`${columna.id}-${parametro.key}`}
                                                className="px-3 py-2 text-center tabular-nums text-foreground"
                                            >
                                                {valorCelda(columna, parametro.key)}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            ))}
        </div>
    );
}
