import type { LucideIcon } from 'lucide-react';
import {
    Banknote,
    CheckIcon,
    ChevronDownIcon,
    CircleDashed,
    CreditCard,
    FileText,
    Landmark,
    LayoutGrid,
    Receipt,
    Smartphone,
    Ticket,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export type CheckboxSelectTone = 'default' | 'success' | 'muted' | 'warning' | 'danger' | 'info';

export type CheckboxSelectOption = {
    value: string;
    label: string;
    icon?: LucideIcon;
    tone?: CheckboxSelectTone;
};

type Props = {
    label: string;
    options: CheckboxSelectOption[];
    selected: string[];
    onChange: (next: string[]) => void;
    allLabel?: string;
    className?: string;
};

const toneIconShell: Record<CheckboxSelectTone, string> = {
    default: 'bg-brand-50 text-brand-700 dark:bg-brand-950/50 dark:text-brand-200',
    success: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300',
    muted: 'bg-stone-100 text-stone-500 dark:bg-muted dark:text-muted-foreground',
    warning: 'bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-300',
    danger: 'bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-300',
    info: 'bg-sky-50 text-sky-600 dark:bg-sky-950/40 dark:text-sky-300',
};

const toneBadge: Record<CheckboxSelectTone, string> = {
    default:
        'border-brand-200/90 bg-brand-50 text-brand-800 dark:border-brand-800 dark:bg-brand-950/40 dark:text-brand-100',
    success:
        'border-emerald-300/90 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100',
    muted: 'border-stone-200 bg-stone-50 text-stone-600 dark:border-border dark:bg-muted dark:text-muted-foreground',
    warning:
        'border-amber-300/90 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100',
    danger:
        'border-rose-300/90 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-100',
    info: 'border-sky-300/90 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100',
};

const toneTriggerIcon: Record<CheckboxSelectTone, string> = {
    default: 'text-brand-700 dark:text-brand-200',
    success: 'text-emerald-600 dark:text-emerald-300',
    muted: 'text-stone-500 dark:text-muted-foreground',
    warning: 'text-amber-600 dark:text-amber-300',
    danger: 'text-rose-600 dark:text-rose-300',
    info: 'text-sky-600 dark:text-sky-300',
};

export const reportSelectIcons = {
    all: LayoutGrid,
    ticket: Ticket,
    boleta: Receipt,
    factura: FileText,
    efectivo: Banknote,
    yape: Smartphone,
    plin: Smartphone,
    tarjeta: CreditCard,
    transferencia: Landmark,
} as const;

function OptionGlyph({
    option,
    className,
}: {
    option: CheckboxSelectOption | { icon?: LucideIcon };
    className?: string;
}) {
    const Icon = option.icon ?? CircleDashed;

    return <Icon className={cn('size-3.5 shrink-0', className)} strokeWidth={2.25} />;
}

export function ReportCheckboxSelect({
    label,
    options,
    selected,
    onChange,
    allLabel = 'Todos',
    className,
}: Props) {
    const allValues = options.map((o) => o.value);
    const allSelected = allValues.length > 0 && allValues.every((v) => selected.includes(v));
    const count = selected.length;
    const single = !allSelected && count === 1
        ? options.find((o) => o.value === selected[0])
        : undefined;

    const summary = allSelected || count === 0
        ? allLabel
        : single
          ? single.label
          : `${count} seleccionados`;

    const triggerTone: CheckboxSelectTone = allSelected || count === 0
        ? 'default'
        : (single?.tone ?? 'default');
    const TriggerIcon = allSelected || count === 0
        ? LayoutGrid
        : (single?.icon ?? LayoutGrid);

    const toggle = (value: string, nextChecked: boolean) => {
        if (nextChecked) {
            onChange(Array.from(new Set([...selected, value])));
            return;
        }

        const next = selected.filter((v) => v !== value);
        onChange(next.length === 0 ? allValues : next);
    };

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    aria-label={label}
                    className={cn(
                        'border-input flex h-10 min-w-44 cursor-pointer items-center justify-between gap-2 rounded-lg border bg-white px-2.5 font-normal shadow-xs',
                        'hover:border-brand-300 hover:bg-white focus-visible:border-brand-400 focus-visible:ring-0',
                        'data-[state=open]:border-brand-400 data-[state=open]:ring-0 dark:bg-card',
                        className,
                    )}
                >
                    <span className="flex min-w-0 items-center gap-1.5">
                        <span
                            className={cn(
                                'flex size-4 shrink-0 items-center justify-center',
                                toneTriggerIcon[triggerTone],
                            )}
                            aria-hidden
                        >
                            <TriggerIcon className="size-3.5" strokeWidth={2.25} />
                        </span>
                        <span className="truncate text-sm text-foreground">{summary}</span>
                    </span>
                    <ChevronDownIcon className="size-3.5 shrink-0 text-muted-foreground/70" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                sideOffset={6}
                className="min-w-60 overflow-hidden rounded-xl border border-border/60 p-1 shadow-lg"
            >
                <button
                    type="button"
                    className={cn(
                        'relative flex w-full cursor-pointer items-center gap-2 rounded-lg px-1.5 py-1.5 pr-7 text-left text-sm outline-hidden',
                        'hover:bg-brand-50/70 dark:hover:bg-brand-950/30',
                        allSelected && 'bg-brand-50 dark:bg-brand-950/40',
                    )}
                    onClick={() => onChange(allValues)}
                >
                    {allSelected ? (
                        <span className="absolute right-1.5 top-1/2 flex size-3.5 -translate-y-1/2 items-center justify-center">
                            <CheckIcon className="size-3 text-foreground/80" strokeWidth={2.5} />
                        </span>
                    ) : null}
                    <span
                        className={cn(
                            'flex size-6 shrink-0 items-center justify-center rounded-md',
                            toneIconShell.default,
                        )}
                        aria-hidden
                    >
                        <LayoutGrid className="size-3" strokeWidth={2.25} />
                    </span>
                    <span className="whitespace-nowrap text-sm font-normal text-foreground">
                        {allLabel}
                    </span>
                </button>

                <div className="my-0.5 h-px bg-border/70" />

                {options.map((opt) => {
                    const checked = selected.includes(opt.value);
                    const tone = opt.tone ?? 'default';
                    const asBadge = tone !== 'default';

                    return (
                        <button
                            key={opt.value}
                            type="button"
                            className={cn(
                                'relative flex w-full cursor-pointer items-center gap-2 rounded-lg px-1.5 py-1.5 pr-7 text-left text-sm outline-hidden',
                                'hover:bg-brand-50/70 dark:hover:bg-brand-950/30',
                                checked && 'bg-brand-50 dark:bg-brand-950/40',
                            )}
                            onClick={() => toggle(opt.value, !checked)}
                        >
                            {checked ? (
                                <span className="absolute right-1.5 top-1/2 flex size-3.5 -translate-y-1/2 items-center justify-center">
                                    <CheckIcon className="size-3 text-foreground/80" strokeWidth={2.5} />
                                </span>
                            ) : null}
                            <span
                                className={cn(
                                    'flex size-6 shrink-0 items-center justify-center rounded-md',
                                    toneIconShell[tone],
                                )}
                                aria-hidden
                            >
                                <OptionGlyph option={opt} className="size-3" />
                            </span>
                            {asBadge ? (
                                <span
                                    className={cn(
                                        'inline-flex max-w-full items-center gap-1 rounded-full border px-2 py-0.5 text-[0.7rem] font-medium',
                                        toneBadge[tone],
                                    )}
                                >
                                    <OptionGlyph option={opt} className="size-3" />
                                    {opt.label}
                                </span>
                            ) : (
                                <span className="whitespace-nowrap text-sm font-normal text-foreground">
                                    {opt.label}
                                </span>
                            )}
                        </button>
                    );
                })}
            </PopoverContent>
        </Popover>
    );
}
