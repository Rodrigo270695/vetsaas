import { usePage } from '@inertiajs/react';
import { format, isSameDay, isSameMonth, isSameYear } from 'date-fns';
import { enUS, es as esLocale } from 'date-fns/locale';
import { CalendarIcon, Check, X } from 'lucide-react';
import { useEffect, useMemo, useState, type MouseEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

import {
    rangeLastMonth,
    rangeLastWeek,
    rangeThisMonth,
    rangeThisWeek,
    rangeThisYear,
    rangeToday,
    rangeYesterday,
} from '../atencion-range-presets';

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
    /** Si se define, el botón «×» quita el filtro en lugar de volver al mes actual. */
    onClear?: () => void;
    /** Namespace i18n con claves `date_filter.*` (p. ej. `historias-clinicas`, `movimientos-inventario`). */
    translationNs?: string;
    /** Clases extra del botón disparador (p. ej. `h-10` para alinear con otros filtros). */
    triggerClassName?: string;
};

type PresetId =
    | 'today'
    | 'yesterday'
    | 'this_week'
    | 'last_week'
    | 'this_month'
    | 'last_month'
    | 'this_year'
    | 'custom';

type PresetOption = {
    id: PresetId;
    label: string;
    desde: string;
    hasta: string;
    from: Date;
    to: Date;
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

function formatPresetSpan(from: Date, to: Date, locale: typeof esLocale): string {
    return `${format(from, 'dd/MM/yyyy', { locale })} — ${format(to, 'dd/MM/yyyy', { locale })}`;
}

function formatTriggerLabel(
    from: Date | undefined,
    to: Date | undefined,
    locale: typeof esLocale,
    allDatesLabel: string,
): string {
    if (from == null || to == null) {
        return allDatesLabel;
    }

    if (isSameDay(from, to)) {
        return format(from, 'dd/MM/yyyy', { locale });
    }

    if (isSameMonth(from, to) && isSameYear(from, to)) {
        const raw = format(from, 'MMM yyyy', { locale });

        return raw.charAt(0).toUpperCase() + raw.slice(1);
    }

    return formatPresetSpan(from, to, locale);
}

function detectPresetId(
    desde: string | null,
    hasta: string | null,
    presets: PresetOption[],
): PresetId {
    if (!desde || !hasta) {
        return 'custom';
    }

    const match = presets.find((p) => p.desde === desde && p.hasta === hasta);

    return match?.id ?? 'custom';
}

export function AtencionDateRangeFilter({
    desde,
    hasta,
    defaultDesde,
    defaultHasta,
    disabled,
    onApply,
    onClear,
    translationNs = 'historias-clinicas',
    triggerClassName,
}: AtencionDateRangeFilterProps) {
    const { t, i18n } = useTranslation([translationNs, 'common']);
    const { timezone: appTz } = usePage().props;

    const dateFnsLocale = i18n.language.startsWith('en') ? enUS : esLocale;

    const [open, setOpen] = useState(false);
    const [customOpen, setCustomOpen] = useState(false);
    const [customDesde, setCustomDesde] = useState('');
    const [customHasta, setCustomHasta] = useState('');

    const presets = useMemo((): PresetOption[] => {
        const build = (
            id: PresetId,
            labelKey: string,
            range: { from: Date; to: Date },
        ): PresetOption => ({
            id,
            label: t(`common:date_range.${labelKey}`),
            from: range.from,
            to: range.to,
            desde: toIsoDate(range.from),
            hasta: toIsoDate(range.to),
        });

        const thisMonth = rangeThisMonth(appTz);
        const serverThisMonth =
            defaultDesde && defaultHasta
                ? { from: parseDay(defaultDesde)!, to: parseDay(defaultHasta)! }
                : thisMonth;

        return [
            build('today', 'preset_today', rangeToday(appTz)),
            build('yesterday', 'preset_yesterday', rangeYesterday(appTz)),
            build('this_week', 'preset_this_week', rangeThisWeek(appTz)),
            build('last_week', 'preset_last_week', rangeLastWeek(appTz)),
            build('this_month', 'preset_this_month', serverThisMonth),
            build('last_month', 'preset_last_month', rangeLastMonth(appTz)),
            build('this_year', 'preset_this_year', rangeThisYear(appTz)),
        ];
    }, [appTz, defaultDesde, defaultHasta, t]);

    const committedFrom = desde ? parseDay(desde) : undefined;
    const committedTo = hasta ? parseDay(hasta) : undefined;
    const hasRange = Boolean(desde && hasta);

    const activePresetId = useMemo(
        () => detectPresetId(desde, hasta, presets),
        [desde, hasta, presets],
    );

    const triggerLabel = hasRange
        ? formatTriggerLabel(committedFrom, committedTo, dateFnsLocale, t('date_filter.placeholder'))
        : t('common:date_range.all_dates');

    const applyRange = (fromIso: string, toIso: string) => {
        onApply(fromIso, toIso);
        setOpen(false);
    };

    const applyPreset = (preset: PresetOption) => {
        applyRange(preset.desde, preset.hasta);
    };

    const handleClear = (event: MouseEvent) => {
        event.preventDefault();
        event.stopPropagation();

        if (onClear) {
            onClear();
        } else {
            onApply(defaultDesde, defaultHasta);
        }

        setOpen(false);
    };

    const handleOpenChange = (next: boolean) => {
        if (next) {
            setCustomDesde(desde ?? defaultDesde);
            setCustomHasta(hasta ?? defaultHasta);
            setCustomOpen(activePresetId === 'custom');
        }

        setOpen(next);
    };

    useEffect(() => {
        if (open && activePresetId === 'custom') {
            setCustomOpen(true);
        }
    }, [open, activePresetId]);

    const applyCustom = () => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(customDesde) || !/^\d{4}-\d{2}-\d{2}$/.test(customHasta)) {
            return;
        }

        const from = parseDay(customDesde);
        const to = parseDay(customHasta);

        if (!from || !to) {
            return;
        }

        applyRange(
            toIsoDate(from <= to ? from : to),
            toIsoDate(from <= to ? to : from),
        );
    };

    const canApplyCustom =
        /^\d{4}-\d{2}-\d{2}$/.test(customDesde) && /^\d{4}-\d{2}-\d{2}$/.test(customHasta);

    return (
        <Popover open={open} onOpenChange={handleOpenChange}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    disabled={disabled}
                    className={cn(
                        'h-9 min-w-36 justify-start gap-2 rounded-lg px-2.5 font-normal shadow-xs transition-colors',
                        hasRange
                            ? 'border-brand-400 bg-white text-foreground hover:border-brand-500 hover:bg-white dark:border-brand-500/70 dark:bg-card'
                            : 'text-muted-foreground',
                        triggerClassName,
                    )}
                    aria-label={t('date_filter.aria')}
                >
                    <CalendarIcon
                        className={cn(
                            'size-3.5 shrink-0',
                            hasRange ? 'text-brand-700 dark:text-brand-300' : 'opacity-60',
                        )}
                        aria-hidden
                    />
                    <span className="min-w-0 flex-1 truncate text-left text-sm">{triggerLabel}</span>
                    {hasRange ? (
                        <span
                            role="button"
                            tabIndex={0}
                            className="inline-flex size-5 shrink-0 cursor-pointer items-center justify-center rounded-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            aria-label={t('common:date_range.clear')}
                            onClick={handleClear}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' || e.key === ' ') {
                                    e.preventDefault();
                                    handleClear(e as unknown as MouseEvent);
                                }
                            }}
                        >
                            <X className="size-3.5" aria-hidden />
                        </span>
                    ) : null}
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className="w-[min(100vw-2rem,14.5rem)] overflow-hidden rounded-xl border border-border/70 p-0 shadow-lg"
                align="start"
                sideOffset={6}
            >
                <div className="flex flex-col p-1">
                    {presets.map((preset) => {
                        const isActive = activePresetId === preset.id;

                        return (
                            <button
                                key={preset.id}
                                type="button"
                                disabled={disabled}
                                onClick={() => applyPreset(preset)}
                                className={cn(
                                    'flex w-full cursor-pointer flex-col gap-0.5 rounded-lg px-2.5 py-1.5 text-left transition-colors',
                                    isActive
                                        ? 'border border-brand-300 bg-brand-50 text-brand-950 dark:border-brand-700/60 dark:bg-brand-950/40 dark:text-brand-50'
                                        : 'border border-transparent hover:bg-muted/60',
                                )}
                            >
                                <span
                                    className={cn(
                                        'min-w-0 text-sm',
                                        isActive ? 'font-semibold' : 'font-medium text-foreground',
                                    )}
                                >
                                    {preset.label}
                                </span>
                                <span
                                    className={cn(
                                        'text-[0.65rem] leading-tight tabular-nums',
                                        isActive
                                            ? 'text-brand-700/80 dark:text-brand-200/80'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {formatPresetSpan(preset.from, preset.to, dateFnsLocale)}
                                </span>
                            </button>
                        );
                    })}
                </div>

                <div className="border-t border-border/60">
                    <button
                        type="button"
                        disabled={disabled}
                        onClick={() => setCustomOpen((v) => !v)}
                        className={cn(
                            'flex w-full cursor-pointer items-center justify-between gap-2 px-3 py-2 text-left transition-colors',
                            customOpen || activePresetId === 'custom'
                                ? 'bg-brand-50/80 dark:bg-brand-950/30'
                                : 'hover:bg-muted/50',
                        )}
                    >
                        <span className="text-sm font-semibold text-foreground">
                            {t('common:date_range.custom')}
                        </span>
                        {customOpen || activePresetId === 'custom' ? (
                            <Check className="size-3.5 text-brand-600 dark:text-brand-300" aria-hidden />
                        ) : null}
                    </button>

                    {customOpen ? (
                        <div className="space-y-3 border-t border-border/40 bg-muted/15 px-3 py-3">
                            <div className="grid grid-cols-2 gap-2.5">
                                <label className="flex flex-col gap-1">
                                    <span className="text-[0.65rem] font-semibold tracking-wide text-muted-foreground uppercase">
                                        {t('common:date_range.from')}
                                    </span>
                                    <Input
                                        type="date"
                                        value={customDesde}
                                        disabled={disabled}
                                        onChange={(e) => setCustomDesde(e.target.value)}
                                        className="h-9 bg-card text-sm"
                                    />
                                </label>
                                <label className="flex flex-col gap-1">
                                    <span className="text-[0.65rem] font-semibold tracking-wide text-muted-foreground uppercase">
                                        {t('common:date_range.to')}
                                    </span>
                                    <Input
                                        type="date"
                                        value={customHasta}
                                        disabled={disabled}
                                        onChange={(e) => setCustomHasta(e.target.value)}
                                        className="h-9 bg-card text-sm"
                                    />
                                </label>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                disabled={disabled || !canApplyCustom}
                                className="h-9 w-full cursor-pointer rounded-md font-medium"
                                onClick={applyCustom}
                            >
                                {t('common:date_range.apply')}
                            </Button>
                        </div>
                    ) : null}
                </div>
            </PopoverContent>
        </Popover>
    );
}
