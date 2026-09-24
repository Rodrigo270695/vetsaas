import { Pencil, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    dateKeyInAppTimezone,
    formatFullDateLabelInAppTimezone,
    formatTimeOnlyInAppTimezone,
} from '../../historias-clinicas/format-atendido';
import type { InternamientoEvolucionRow } from '../types';

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

function capitalizar(texto: string): string {
    if (texto.length === 0) {
        return texto;
    }

    return texto.charAt(0).toUpperCase() + texto.slice(1);
}

type Props = {
    evoluciones: readonly InternamientoEvolucionRow[];
    locale: string;
    timeZone: string;
    canUpdate: boolean;
    onEdit: (evolucion: InternamientoEvolucionRow) => void;
    onDelete: (evolucion: InternamientoEvolucionRow) => void;
};

export function ConstantesFisiologicas({
    evoluciones,
    locale,
    timeZone,
    canUpdate,
    onEdit,
    onDelete,
}: Props) {
    const { t } = useTranslation(['hospitalizacion', 'common']);

    const dias = useMemo((): DaySheet[] => {
        const groups = new Map<string, InternamientoEvolucionRow[]>();

        for (const item of evoluciones) {
            const dayKey = dateKeyInAppTimezone(item.registrado_at, timeZone) || '—';
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
                const label = capitalizar(
                    formatFullDateLabelInAppTimezone(ordenadas[0].registrado_at, locale, timeZone),
                );

                return { dayKey, label, columnas: ordenadas };
            });
    }, [evoluciones, locale, timeZone]);

    if (dias.length === 0) {
        return (
            <p className="py-8 text-center text-sm text-muted-foreground">
                {t('show.constantes_empty')}
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            {dias.map((dia) => (
                <section key={dia.dayKey} className="overflow-hidden rounded-xl border border-border/70">
                    <header className="border-b border-border/60 bg-muted/40 px-4 py-2.5">
                        <h3 className="text-sm font-semibold capitalize text-foreground">{dia.label}</h3>
                    </header>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[36rem] border-collapse text-sm">
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
                                                        timeZone,
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
