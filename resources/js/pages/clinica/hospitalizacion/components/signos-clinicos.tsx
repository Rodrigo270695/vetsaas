import { TZDate } from '@date-fns/tz';
import { format } from 'date-fns';
import { Pencil, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dateKeyInAppTimezone, formatTimeOnlyInAppTimezone } from '../../historias-clinicas/format-atendido';
import type { InternamientoSignoRow } from '../types';

const ZONA_PERU = 'America/Lima';

const MUCOSAS = [
    'rosadas',
    'rosadas_palidas',
    'palidas',
    'cianoticas',
    'ictericas',
    'congestionadas',
    'hemorragicas',
    'secas',
] as const;

const COLOR_MUCOSA: Record<(typeof MUCOSAS)[number], string> = {
    rosadas: 'bg-rose-400',
    rosadas_palidas: 'bg-rose-200',
    palidas: 'bg-stone-200 ring-1 ring-stone-300',
    cianoticas: 'bg-sky-500',
    ictericas: 'bg-amber-400',
    congestionadas: 'bg-red-500',
    hemorragicas: 'bg-red-700',
    secas: 'bg-amber-200',
};

type Fila =
    | { key: 'mucosas' | 'glucemia' | 'orina' | 'vomito' | 'diarrea' | 'heces' | 'bristol' | 'alimento' | 'agua' | 'notas'; labelKey: string };

const FILAS: readonly Fila[] = [
    { key: 'mucosas', labelKey: 'signos.mucosas' },
    { key: 'glucemia', labelKey: 'signos.glucemia' },
    { key: 'orina', labelKey: 'signos.orina' },
    { key: 'vomito', labelKey: 'signos.vomito' },
    { key: 'diarrea', labelKey: 'signos.diarrea' },
    { key: 'heces', labelKey: 'signos.heces' },
    { key: 'bristol', labelKey: 'signos.bristol' },
    { key: 'alimento', labelKey: 'signos.alimento' },
    { key: 'agua', labelKey: 'signos.agua' },
    { key: 'notas', labelKey: 'signos.notas' },
];

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

function esMucosaConocida(valor: string): valor is (typeof MUCOSAS)[number] {
    return (MUCOSAS as readonly string[]).includes(valor);
}

type Props = {
    signos: readonly InternamientoSignoRow[];
    timeZone?: string;
    canUpdate: boolean;
    onEdit: (signo: InternamientoSignoRow) => void;
    onDelete: (signo: InternamientoSignoRow) => void;
};

export function SignosClinicos({ signos, timeZone, canUpdate, onEdit, onDelete }: Props) {
    const { t } = useTranslation(['hospitalizacion', 'common']);
    const zona = zonaPeru(timeZone);

    const dias = useMemo(() => {
        const groups = new Map<string, InternamientoSignoRow[]>();

        for (const item of signos) {
            const dayKey = dateKeyInAppTimezone(item.registrado_at, zona) || '—';
            const list = groups.get(dayKey) ?? [];
            list.push(item);
            groups.set(dayKey, list);
        }

        return [...groups.entries()]
            .sort(([a], [b]) => a.localeCompare(b))
            .map(([dayKey, columnas]) => {
                const ordenadas = [...columnas].sort((a, b) => a.registrado_at.localeCompare(b.registrado_at));

                return {
                    dayKey,
                    label: fechaCabecera(ordenadas[0].registrado_at, zona),
                    columnas: ordenadas,
                };
            });
    }, [signos, zona]);

    const celda = (fila: Fila['key'], columna: InternamientoSignoRow) => {
        if (fila === 'mucosas') {
            const valor = columna.mucosas;

            if (valor == null || valor === '') {
                return <span className="text-muted-foreground/35">—</span>;
            }

            if (esMucosaConocida(valor)) {
                return (
                    <span className="inline-flex items-center justify-center gap-1.5 font-medium text-foreground">
                        <span className={cn('size-2.5 shrink-0 rounded-full', COLOR_MUCOSA[valor])} />
                        {t(`signos.mucosas_opcion.${valor}`)}
                    </span>
                );
            }

            return <span className="font-medium text-foreground">{valor}</span>;
        }

        if (fila === 'glucemia') {
            return columna.glucemia_mg_dl ? (
                <span className="font-semibold tabular-nums text-foreground">{columna.glucemia_mg_dl}</span>
            ) : (
                <span className="text-muted-foreground/35">—</span>
            );
        }

        if (fila === 'orina') {
            return columna.orina_ml ? (
                <span className="font-semibold tabular-nums text-foreground">{columna.orina_ml}</span>
            ) : (
                <span className="text-muted-foreground/35">—</span>
            );
        }

        if (fila === 'bristol') {
            return columna.bristol != null ? (
                <span className="font-semibold tabular-nums text-foreground" title={t(`signos.bristol_opcion.${columna.bristol}`)}>
                    {columna.bristol}
                </span>
            ) : (
                <span className="text-muted-foreground/35">—</span>
            );
        }

        if (fila === 'notas') {
            return columna.notas ? (
                <span className="block max-w-40 text-left text-xs leading-snug text-foreground">{columna.notas}</span>
            ) : (
                <span className="text-muted-foreground/35">—</span>
            );
        }

        const codigo = columna[fila];

        if (codigo == null || codigo === '') {
            return <span className="text-muted-foreground/35">—</span>;
        }

        if (codigo === 'no') {
            return <span className="font-semibold text-muted-foreground">{t('signos.ausente')}</span>;
        }

        return <span className="font-medium text-foreground">{t(`signos.${fila}_opcion.${codigo}`, { defaultValue: codigo })}</span>;
    };

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
                            {t('show.signos_parametro')}
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
                    {FILAS.map((fila) => (
                        <tr key={fila.key} className="group">
                            <th
                                className={cn(
                                    esquina,
                                    'border-b border-border/40 px-1 py-2.5 pr-8 text-left text-[13px] font-medium text-muted-foreground group-last:border-b-0 group-hover:bg-muted/40 group-hover:text-foreground',
                                )}
                            >
                                {t(fila.labelKey)}
                            </th>
                            {dias.map((dia) =>
                                dia.columnas.map((columna, indice) => (
                                    <td
                                        key={`${columna.id}-${fila.key}`}
                                        className={cn(
                                            'border-b border-border/40 px-3 py-2.5 text-center align-middle whitespace-nowrap group-last:border-b-0 group-hover:bg-muted/30',
                                            indice === 0 && 'border-l border-border/50',
                                            fila.key === 'notas' && 'whitespace-normal',
                                        )}
                                    >
                                        {celda(fila.key, columna)}
                                    </td>
                                )),
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
