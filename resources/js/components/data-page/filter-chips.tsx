import * as SelectPrimitive from '@radix-ui/react-select';
import {
    CheckCircle2,
    CheckIcon,
    ChevronDownIcon,
    CircleDashed,
    Clock3,
    FilePenLine,
    LayoutGrid,
    LoaderCircle,
    XCircle,
    type LucideIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type FilterChipTone = 'default' | 'success' | 'muted' | 'warning' | 'danger' | 'info';

export type FilterChip<TValue extends string> = {
    value: TValue;
    label: string;
    /** Conteo opcional al lado de la etiqueta (ej. "Activas (13)"). */
    count?: number;
    icon?: ReactNode;
    /** Texto secundario bajo el título en el menú (típico en "Todos…"). */
    description?: string;
    /** Tinte visual (badge en menú + icono del trigger). */
    tone?: FilterChipTone;
};

export type FilterChipsProps<TValue extends string> = {
    /** Etiqueta semántica para accesibilidad. */
    ariaLabel: string;
    value: TValue;
    onChange: (value: TValue) => void;
    options: readonly FilterChip<TValue>[];
    className?: string;
    disabled?: boolean;
    /** Clases extra del botón disparador. */
    triggerClassName?: string;
};

const toneIconShell: Record<FilterChipTone, string> = {
    default: 'bg-brand-50 text-brand-700 dark:bg-brand-950/50 dark:text-brand-200',
    success: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300',
    muted: 'bg-stone-100 text-stone-500 dark:bg-muted dark:text-muted-foreground',
    warning: 'bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-300',
    danger: 'bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-300',
    info: 'bg-sky-50 text-sky-600 dark:bg-sky-950/40 dark:text-sky-300',
};

const toneBadge: Record<FilterChipTone, string> = {
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

const toneTriggerIcon: Record<FilterChipTone, string> = {
    default: 'text-brand-700 dark:text-brand-200',
    success: 'text-emerald-600 dark:text-emerald-300',
    muted: 'text-stone-500 dark:text-muted-foreground',
    warning: 'text-amber-600 dark:text-amber-300',
    danger: 'text-rose-600 dark:text-rose-300',
    info: 'text-sky-600 dark:text-sky-300',
};

function inferTone(value: string, explicit?: FilterChipTone): FilterChipTone {
    if (explicit) {
        return explicit;
    }

    const v = value.toLowerCase();

    if (v === 'todas' || v === 'todos' || v === 'all' || v === '' || v === '__all__') {
        return 'default';
    }

    if (
        [
            'activa',
            'activo',
            'activas',
            'activos',
            'completada',
            'completado',
            'confirmada',
            'abierta',
            'pagada',
            'alta',
            'emitida',
        ].includes(v)
    ) {
        return 'success';
    }

    if (
        [
            'inactiva',
            'inactivo',
            'inactivas',
            'inactivos',
            'cancelada',
            'cancelado',
            'anulada',
            'cerrada',
        ].includes(v)
    ) {
        return 'muted';
    }

    if (['borrador', 'programada', 'pendiente', 'solicitado'].includes(v)) {
        return 'warning';
    }

    if (['en_proceso', 'en_curso'].includes(v)) {
        return 'info';
    }

    return 'default';
}

function isAllValue(value: string): boolean {
    return ['todas', 'todos', 'all', '', '__all__'].includes(value.toLowerCase());
}

function DefaultIcon({ value, className }: { value: string; className?: string }): ReactNode {
    const v = value.toLowerCase();
    const cls = cn('size-3.5 shrink-0', className);

    if (isAllValue(v)) {
        return <LayoutGrid className={cls} strokeWidth={2.25} />;
    }

    if (
        [
            'activa',
            'activo',
            'activas',
            'activos',
            'completada',
            'completado',
            'confirmada',
            'abierta',
            'pagada',
            'alta',
            'emitida',
        ].includes(v)
    ) {
        return <CheckCircle2 className={cls} strokeWidth={2.25} />;
    }

    if (
        [
            'inactiva',
            'inactivo',
            'inactivas',
            'inactivos',
            'cancelada',
            'cancelado',
            'anulada',
            'cerrada',
        ].includes(v)
    ) {
        return <XCircle className={cls} strokeWidth={2.25} />;
    }

    if (v === 'borrador') {
        return <FilePenLine className={cls} strokeWidth={2.25} />;
    }

    if (v === 'programada' || v === 'pendiente' || v === 'solicitado') {
        return <Clock3 className={cls} strokeWidth={2.25} />;
    }

    if (v === 'en_proceso' || v === 'en_curso') {
        return <LoaderCircle className={cls} strokeWidth={2.25} />;
    }

    return <CircleDashed className={cls} strokeWidth={2.25} />;
}

function OptionIcon({
    option,
    className,
}: {
    option: FilterChip<string>;
    className?: string;
}): ReactNode {
    if (option.icon) {
        return option.icon;
    }

    return <DefaultIcon value={option.value} className={className} />;
}

function labelWithCount(label: string, count?: number): string {
    if (typeof count !== 'number') {
        return label;
    }

    return `${label} (${count})`;
}

/**
 * Filtro de estado tipo select enriquecido (icono + etiqueta / badge + descripción).
 *
 * API compatible con el antiguo segmented control: mismos props
 * (`ariaLabel`, `value`, `onChange`, `options`).
 */
export function FilterChips<TValue extends string>({
    ariaLabel,
    value,
    onChange,
    options,
    className,
    disabled = false,
    triggerClassName,
}: FilterChipsProps<TValue>) {
    const selected = options.find((o) => o.value === value) ?? options[0];
    const selectedTone = selected ? inferTone(selected.value, selected.tone) : 'default';

    return (
        <SelectPrimitive.Root
            value={value}
            onValueChange={(v) => onChange(v as TValue)}
            disabled={disabled}
        >
            <SelectPrimitive.Trigger
                aria-label={ariaLabel}
                className={cn(
                    'border-input flex h-9 w-full min-w-[11rem] cursor-pointer items-center justify-between gap-2 rounded-lg border bg-white px-2.5 text-sm shadow-xs outline-none transition-[color,border-color]',
                    'hover:border-brand-300 hover:bg-white focus-visible:border-brand-400 focus-visible:ring-0',
                    'data-[state=open]:border-brand-400 data-[state=open]:ring-0',
                    'disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto dark:bg-card',
                    className,
                    triggerClassName,
                )}
            >
                {selected ? (
                    <span className="flex min-w-0 items-center gap-1.5">
                        <span
                            className={cn(
                                'flex size-4 shrink-0 items-center justify-center',
                                toneTriggerIcon[selectedTone],
                            )}
                            aria-hidden
                        >
                            <OptionIcon option={selected} className="size-3.5" />
                        </span>
                        <span className="truncate font-normal text-foreground">
                            {labelWithCount(selected.label, selected.count)}
                        </span>
                    </span>
                ) : (
                    <SelectPrimitive.Value placeholder="…" />
                )}
                <SelectPrimitive.Icon asChild>
                    <ChevronDownIcon className="size-3.5 shrink-0 text-muted-foreground/70" />
                </SelectPrimitive.Icon>
            </SelectPrimitive.Trigger>

            <SelectPrimitive.Portal>
                <SelectPrimitive.Content
                    position="popper"
                    sideOffset={6}
                    align="start"
                    className={cn(
                        'bg-white text-popover-foreground relative z-50 max-h-(--radix-select-content-available-height) min-w-[max(var(--radix-select-trigger-width),14.5rem)] overflow-hidden rounded-xl border border-border/60 shadow-lg dark:bg-popover',
                        'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2',
                    )}
                >
                    <SelectPrimitive.Viewport className="flex flex-col gap-0.5 p-1">
                        {options.map((opt) => {
                            const tone = inferTone(opt.value, opt.tone);
                            const asAll = isAllValue(opt.value);
                            const asBadge = !asAll && tone !== 'default';

                            return (
                                <SelectPrimitive.Item
                                    key={opt.value}
                                    value={opt.value}
                                    className={cn(
                                        'relative flex w-full cursor-pointer items-center gap-2 rounded-lg px-1.5 py-1.5 pr-7 text-sm outline-hidden select-none',
                                        'data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
                                        'focus:bg-brand-50/70 dark:focus:bg-brand-950/30',
                                        'data-[state=checked]:bg-brand-50 data-[state=checked]:dark:bg-brand-950/40',
                                    )}
                                >
                                    <span className="absolute right-1.5 top-1/2 flex size-3.5 -translate-y-1/2 items-center justify-center">
                                        <SelectPrimitive.ItemIndicator>
                                            <CheckIcon className="size-3 text-foreground/80" strokeWidth={2.5} />
                                        </SelectPrimitive.ItemIndicator>
                                    </span>

                                    <span
                                        className={cn(
                                            'flex size-6 shrink-0 items-center justify-center rounded-md',
                                            toneIconShell[tone],
                                        )}
                                        aria-hidden
                                    >
                                        <OptionIcon option={opt} className="size-3" />
                                    </span>

                                    {asBadge ? (
                                        <span
                                            className={cn(
                                                'inline-flex max-w-full items-center gap-1 rounded-full border px-2 py-0.5 text-[0.7rem] font-medium',
                                                toneBadge[tone],
                                            )}
                                        >
                                            <OptionIcon option={opt} className="size-3" />
                                            <SelectPrimitive.ItemText>
                                                {labelWithCount(opt.label, opt.count)}
                                            </SelectPrimitive.ItemText>
                                        </span>
                                    ) : (
                                        <SelectPrimitive.ItemText asChild>
                                            <span className="whitespace-nowrap text-sm font-normal text-foreground">
                                                {labelWithCount(opt.label, opt.count)}
                                            </span>
                                        </SelectPrimitive.ItemText>
                                    )}
                                </SelectPrimitive.Item>
                            );
                        })}
                    </SelectPrimitive.Viewport>
                </SelectPrimitive.Content>
            </SelectPrimitive.Portal>
        </SelectPrimitive.Root>
    );
}

/** Iconos sugeridos para reutilizar en opciones custom. */
export const filterChipIcons = {
    all: LayoutGrid,
    active: CheckCircle2,
    inactive: XCircle,
    draft: FilePenLine,
    scheduled: Clock3,
    progress: LoaderCircle,
} as const satisfies Record<string, LucideIcon>;
