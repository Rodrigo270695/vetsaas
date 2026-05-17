import type { ChangeEvent } from 'react';
import { UI, type DropdownProps } from 'react-day-picker';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

/**
 * Sustituye el `<select>` nativo de react-day-picker por el Select de Radix
 * (lista con foco marca VetSaaS, sin el resaltado azul del SO).
 */
export function VetsaasDayPickerDropdown({
    options,
    className,
    classNames,
    components: _c,
    value,
    onChange,
    disabled,
    style,
    ...rest
}: DropdownProps) {
    const stringValue = value === undefined || value === null ? '' : String(value);
    const selected = options?.find((o) => o.value === Number(stringValue));

    const triggerLabel =
        (rest['aria-label'] as string | undefined) ??
        (selected?.label ? String(selected.label) : undefined);

    const emitChange = (raw: string) => {
        if (!onChange) {
            return;
        }

        const synthetic = {
            target: { value: raw },
        } as ChangeEvent<HTMLSelectElement>;

        onChange(synthetic);
    };

    return (
        <div
            data-disabled={disabled ? 'true' : undefined}
            className={cn(classNames[UI.DropdownRoot], 'relative min-w-0')}
            style={style}
        >
            <Select
                value={stringValue}
                onValueChange={(v) => emitChange(v)}
                disabled={Boolean(disabled)}
            >
                <SelectTrigger
                    size="sm"
                    id={rest.id}
                    aria-label={triggerLabel}
                    className={cn(
                        'h-8 min-h-8 border-border bg-background px-2.5 text-xs font-medium text-foreground shadow-xs',
                        'data-placeholder:text-muted-foreground',
                        className,
                    )}
                >
                    <SelectValue placeholder={selected?.label} />
                </SelectTrigger>
                <SelectContent
                    position="popper"
                    sideOffset={4}
                    className={cn(
                        'z-100 max-h-64 min-w-(--radix-select-trigger-width) border-border p-1 shadow-lg',
                    )}
                >
                    {options?.map((opt) => (
                        <SelectItem
                            key={opt.value}
                            value={String(opt.value)}
                            disabled={opt.disabled}
                            className={cn(
                                'cursor-pointer rounded-sm py-1.5 pr-8 pl-2 text-xs font-normal',
                                'focus:bg-brand-50 focus:text-foreground',
                                'data-highlighted:bg-brand-50 data-highlighted:text-foreground',
                                'data-[state=checked]:bg-brand-100 data-[state=checked]:text-brand-900',
                            )}
                        >
                            {opt.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
