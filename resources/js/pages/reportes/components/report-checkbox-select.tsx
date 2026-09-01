import { ChevronDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

export type CheckboxSelectOption = {
    value: string;
    label: string;
};

type Props = {
    label: string;
    options: CheckboxSelectOption[];
    selected: string[];
    onChange: (next: string[]) => void;
    allLabel?: string;
    className?: string;
};

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

    const summary =
        allSelected || count === 0
            ? allLabel
            : count === 1
              ? (options.find((o) => o.value === selected[0])?.label ?? `${count}`)
              : `${count} seleccionados`;

    const toggle = (value: string, checked: boolean) => {
        if (checked) {
            onChange(Array.from(new Set([...selected, value])));
            return;
        }

        const next = selected.filter((v) => v !== value);
        onChange(next.length === 0 ? allValues : next);
    };

    const toggleAll = (checked: boolean) => {
        onChange(checked ? allValues : allValues);
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    className={cn(
                        'h-10 min-w-48 justify-between gap-2 font-normal',
                        className,
                    )}
                >
                    <span className="truncate text-left">
                        <span className="text-muted-foreground">{label}: </span>
                        {summary}
                    </span>
                    <ChevronDown className="size-4 shrink-0 opacity-60" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="min-w-56">
                <DropdownMenuLabel>{label}</DropdownMenuLabel>
                <DropdownMenuCheckboxItem
                    checked={allSelected}
                    onCheckedChange={(c) => toggleAll(c === true)}
                    onSelect={(e) => e.preventDefault()}
                >
                    {allLabel}
                </DropdownMenuCheckboxItem>
                <DropdownMenuSeparator />
                {options.map((opt) => (
                    <DropdownMenuCheckboxItem
                        key={opt.value}
                        checked={selected.includes(opt.value)}
                        onCheckedChange={(c) => toggle(opt.value, c === true)}
                        onSelect={(e) => e.preventDefault()}
                    >
                        {opt.label}
                    </DropdownMenuCheckboxItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
