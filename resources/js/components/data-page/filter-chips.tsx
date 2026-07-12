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
    /** Texto secundario bajo el título en el menú. */
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

const toneIconBox: Record<FilterChipTone, string> = {
    default: 'bg-brand-50 text-brand-700 dark:bg-brand-950/50 dark:text-brand-200',
    success: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
    muted: 'bg-muted text-muted-foreground',
    warning: 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
    danger: 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300',
    info: 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300',
};

const toneBadge: Record<FilterChipTone, string> = {
    default: 'border-brand-200/80 bg-brand-50 text-brand-800 dark:border-brand-800 dark:bg-brand-950/40 dark:text-brand-100',
    success:
        'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100',
    muted: 'border-border bg-muted text-muted-foreground',
    warning:
        'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100',
    danger: 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-100',
    info: 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100',
};

const toneDot: Record<FilterChipTone, string> = {
    default: 'bg-brand-50 dark:bg-brand-950/40',
    success: 'bg-emerald-100 dark:bg-emerald-950/50',
    muted: 'bg-muted',
    warning: 'bg-amber-100 dark:bg-amber-950/50',
    danger: 'bg-rose-100 dark:bg-rose-950/50',
    info: 'bg-sky-100 dark:bg-sky-950/50',
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

function DefaultIcon({ value, className }: { value: string; className?: string }): ReactNode {
    const v = value.toLowerCase();
    const cls = cn('size-3.5', className);

    if (['todas', 'todos', 'all', '', '__all__'].includes(v)) {
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

/**
 * Filtro de estado tipo select enriquecido (icono + etiqueta + descripción).
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
                    'border-input flex h-10 w-full min-w-[11.5rem] cursor-pointer items-center justify-between gap-2 rounded-lg border bg-card px-3 text-sm shadow-xs outline-none transition-[color,box-shadow,background-color]',
                    'hover:bg-muted/30 focus-visible:border-ring focus-visible:ring-ring/40 focus-visible:ring-[3px]',
                    'data-[state=open]:border-brand-200 data-[state=open]:ring-2 data-[state=open]:ring-brand-100/70',
                    'disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto',
                    '[&>span]:line-clamp-1',
                    className,
                    triggerClassName,
                )}
            >
                {selected ? (
                    <span className="flex min-w-0 items-center gap-2">
                        <span
                            className={cn(
                                'flex size-5 shrink-0 items-center justify-center rounded-full',
                                toneDot[selectedTone],
                            )}
                        >
                            <OptionIcon option={selected} />
                        </span>
                        <span className="truncate font-medium text-foreground">{selected.label}</span>
                        {typeof selected.count === 'number' ? (
                            <span className="tabular-nums text-muted-foreground">({selected.count})</span>
                        ) : null}
                    </span>
                ) : (
                    <SelectPrimitive.Value placeholder="…" />
                )}
                <SelectPrimitive.Icon asChild>
                    <ChevronDownIcon className="size-4 shrink-0 opacity-50" />
                </SelectPrimitive.Icon>
            </SelectPrimitive.Trigger>

            <SelectPrimitive.Portal>
                <SelectPrimitive.Content
                    position="popper"
                    sideOffset={6}
                    align="start"
                    className={cn(
                        'bg-popover text-popover-foreground relative z-50 max-h-(--radix-select-content-available-height) min-w-[var(--radix-select-trigger-width)] overflow-hidden rounded-xl border border-border/70 shadow-lg sm:min-w-[18rem]',
                        'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2',
                    )}
                >
                    <SelectPrimitive.Viewport className="p-1.5">
                        {options.map((opt) => {
                            const tone = inferTone(opt.value, opt.tone);
                            const useBadge =
                                !opt.description &&
                                (tone === 'success' ||
                                    tone === 'muted' ||
                                    tone === 'danger' ||
                                    tone === 'warning' ||
                                    tone === 'info');

                            return (
                                <SelectPrimitive.Item
                                    key={opt.value}
                                    value={opt.value}
                                    className={cn(
                                        'relative flex w-full cursor-pointer items-start gap-2.5 rounded-lg py-2.5 pr-9 pl-2 text-sm outline-hidden select-none',
                                        'data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
                                        'focus:bg-brand-50/75 dark:focus:bg-brand-950/30',
                                        'data-[state=checked]:bg-brand-50/90 dark:data-[state=checked]:bg-brand-950/40',
                                    )}
                                >
                                    <span className="absolute right-2 top-1/2 flex size-4 -translate-y-1/2 items-center justify-center">
                                        <SelectPrimitive.ItemIndicator>
                                            <CheckIcon className="size-4 text-foreground" />
                                        </SelectPrimitive.ItemIndicator>
                                    </span>

                                    <span
                                        className={cn(
                                            'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg',
                                            toneIconBox[tone],
                                        )}
                                        aria-hidden
                                    >
                                        <OptionIcon option={opt} />
                                    </span>

                                    <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                                        <SelectPrimitive.ItemText asChild>
                                            {useBadge ? (
                                                <span
                                                    className={cn(
                                                        'inline-flex w-fit items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-semibold',
                                                        toneBadge[tone],
                                                    )}
                                                >
                                                    <OptionIcon option={opt} className="size-3" />
                                                    {opt.label}
                                                    {typeof opt.count === 'number' ? (
                                                        <span className="tabular-nums opacity-80">
                                                            ({opt.count})
                                                        </span>
                                                    ) : null}
                                                </span>
                                            ) : (
                                                <span className="truncate font-semibold text-foreground">
                                                    {opt.label}
                                                    {typeof opt.count === 'number' ? (
                                                        <span className="ml-1.5 font-medium tabular-nums text-muted-foreground">
                                                            ({opt.count})
                                                        </span>
                                                    ) : null}
                                                </span>
                                            )}
                                        </SelectPrimitive.ItemText>
                                        {opt.description ? (
                                            <span className="text-xs leading-snug text-muted-foreground">
                                                {opt.description}
                                            </span>
                                        ) : null}
                                    </span>
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
