import { usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { enUS, es as esLocale } from 'date-fns/locale';
import { CalendarIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { DateRange } from 'react-day-picker';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

import { rangeLastMonth, rangeLastWeek, rangeThisWeek } from '../atencion-range-presets';

export type AtencionDateRangeFilterProps = {
    /** Inicio del rango aplicado (YYYY-MM-DD). Null = sin filtro de fechas. */
    desde: string | null;
    /** Fin del rango aplicado (YYYY-MM-DD). Null = sin filtro de fechas. */
    hasta: string | null;
    /** Límites del “mes actual” según el servidor (para el botón de restablecer). */
    defaultDesde: string;
    defaultHasta: string;
    disabled?: boolean;
    /** Al confirmar un rango (desde ≤ hasta, fechas locales). */
    onApply: (desde: string, hasta: string) => void;
    /** Namespace i18n con claves `date_filter.*` (p. ej. `historias-clinicas`, `movimientos-inventario`). */
    translationNs?: string;
    /** Clases extra del botón disparador (p. ej. `h-10` para alinear con otros filtros). */
    triggerClassName?: string;
};

function parseDay(iso: string): Date | undefined {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
        return undefined;
    }

    const [y, m, d] = iso.split('-').map(Number);

    if (!y || !m || !d) {
        return undefined;
    }

    const dt = new Date(y, m - 1, d);

    return Number.isNaN(dt.getTime()) ? undefined : dt;
}

function toIsoDate(d: Date): string {
    return format(d, 'yyyy-MM-dd');
}

export function AtencionDateRangeFilter({
    desde,
    hasta,
    defaultDesde,
    defaultHasta,
    disabled,
    onApply,
    translationNs = 'historias-clinicas',
    triggerClassName,
}: AtencionDateRangeFilterProps) {
    const { t, i18n } = useTranslation(translationNs);
    const { timezone: appTz } = usePage().props;

    const dateFnsLocale = i18n.language.startsWith('en') ? enUS : esLocale;

    const [open, setOpen] = useState(false);
    const [rangeDraft, setRangeDraft] = useState<DateRange | undefined>(undefined);

    const committed = useMemo(
        () => ({
            from: desde ? parseDay(desde) : undefined,
            to: hasta ? parseDay(hasta) : undefined,
        }),
        [desde, hasta],
    );

    const displayRange = open ? (rangeDraft ?? committed) : committed;

    const label =
        displayRange.from != null ? (
            displayRange.to != null ? (
                <>
                    {format(displayRange.from, 'd MMM yyyy', { locale: dateFnsLocale })} —{' '}
                    {format(displayRange.to, 'd MMM yyyy', { locale: dateFnsLocale })}
                </>
            ) : (
                format(displayRange.from, 'd MMM yyyy', { locale: dateFnsLocale })
            )
        ) : (
            t('date_filter.placeholder')
        );

    const defaultMonth = displayRange.from ?? committed.from ?? new Date();

    const applyRange = (from: Date, to: Date) => {
        const d0 = from <= to ? from : to;
        const d1 = from <= to ? to : from;

        onApply(toIsoDate(d0), toIsoDate(d1));
        setOpen(false);
    };

    const handleSelect = (range: DateRange | undefined) => {
        setRangeDraft(range);

        if (range?.from != null && range.to !== undefined) {
            applyRange(range.from, range.to);
        }
    };

    const applySingleDay = () => {
        const from = rangeDraft?.from ?? displayRange.from;

        if (from == null) {
            return;
        }

        applyRange(from, from);
    };

    const resetToCurrentMonth = () => {
        onApply(defaultDesde, defaultHasta);
        setOpen(false);
    };

    const handleOpenChange = (next: boolean) => {
        if (next) {
            setRangeDraft({
                from: desde ? parseDay(desde) : undefined,
                to: hasta ? parseDay(hasta) : undefined,
            });
        }

        setOpen(next);
    };

    const calendarSelected = open ? (rangeDraft ?? committed) : committed;

    const applyPreset = (from: Date, to: Date) => {
        applyRange(from, to);
    };

    return (
        <Popover open={open} onOpenChange={handleOpenChange}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    disabled={disabled}
                    className={cn(
                        'h-9 min-w-48 justify-start gap-2 px-3 font-normal',
                        !displayRange.from && 'text-muted-foreground',
                        triggerClassName,
                    )}
                    aria-label={t('date_filter.aria')}
                >
                    <CalendarIcon className="size-4 shrink-0 opacity-70" aria-hidden />
                    <span className="truncate text-left">{label}</span>
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto max-w-[min(100vw-2rem,22rem)] p-0" align="start" sideOffset={6}>
                <div className="grid grid-cols-2 gap-1.5 border-b border-border p-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-8 cursor-pointer text-xs font-normal"
                        disabled={disabled}
                        onClick={() => {
                            const { from, to } = rangeThisWeek(appTz);

                            applyPreset(from, to);
                        }}
                    >
                        {t('date_filter.preset_this_week')}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-8 cursor-pointer text-xs font-normal"
                        disabled={disabled}
                        onClick={() => {
                            const { from, to } = rangeLastWeek(appTz);

                            applyPreset(from, to);
                        }}
                    >
                        {t('date_filter.preset_last_week')}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-8 cursor-pointer text-xs font-normal"
                        disabled={disabled}
                        onClick={() => {
                            const { from, to } = rangeLastMonth(appTz);

                            applyPreset(from, to);
                        }}
                    >
                        {t('date_filter.preset_last_month')}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-8 cursor-pointer text-xs font-normal"
                        disabled={disabled}
                        onClick={resetToCurrentMonth}
                    >
                        {t('date_filter.reset_month')}
                    </Button>
                </div>
                <Calendar
                    key={`${desde}-${hasta}-${String(open)}`}
                    mode="range"
                    locale={dateFnsLocale}
                    defaultMonth={defaultMonth}
                    selected={calendarSelected}
                    onSelect={handleSelect}
                    numberOfMonths={1}
                />
                <div className="flex flex-col gap-1 border-t border-border p-2">
                    {rangeDraft?.from != null && rangeDraft.to == null && (
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            className="w-full cursor-pointer text-xs font-normal"
                            onClick={applySingleDay}
                        >
                            {t('date_filter.apply_single_day')}
                        </Button>
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}
