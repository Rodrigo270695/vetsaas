import { Check, ChevronsUpDown, Loader2, X } from 'lucide-react';
import * as React from 'react';

import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export type ComboboxOption = {
    value: string;
    label: string;
};

export type ComboboxProps = {
    options: readonly ComboboxOption[];
    value: string | null;
    onChange: (value: string | null) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyMessage?: string;
    disabled?: boolean;
    loading?: boolean;
    clearable?: boolean;
    id?: string;
    name?: string;
    className?: string;
    'aria-invalid'?: boolean;
    'aria-describedby'?: string;
};

/**
 * Combobox accesible con búsqueda interna (cmdk).
 *
 * Patrón estándar shadcn: `Popover` para el flotante, `Command` para
 * la lista + filtrado por texto, `CommandItem` para cada opción.
 *
 * Características:
 *   - `clearable`: muestra una "X" para borrar la selección sin abrir.
 *   - `loading`: spinner reemplaza el chevron mientras se cargan
 *     opciones en cascada (útil en formularios dependientes).
 *   - `disabled`: bloquea cuando el padre aún no fue seleccionado.
 *   - Soporte completo de teclado (arrow keys, enter, escape).
 */
export function Combobox({
    options,
    value,
    onChange,
    placeholder = 'Selecciona...',
    searchPlaceholder = 'Buscar...',
    emptyMessage = 'Sin resultados.',
    disabled = false,
    loading = false,
    clearable = true,
    id,
    name,
    className,
    'aria-invalid': ariaInvalid,
    'aria-describedby': ariaDescribedBy,
}: ComboboxProps) {
    const [open, setOpen] = React.useState(false);

    const selected = React.useMemo(
        () => options.find((opt) => opt.value === value) ?? null,
        [options, value],
    );

    const handleClear = (e: React.MouseEvent) => {
        e.stopPropagation();
        onChange(null);
    };

    return (
        // `modal` evita que el Dialog padre (FormModal de la sede) intercepte
        // los eventos pointer/scroll y rompa la rueda del mouse dentro de la
        // lista del combobox. Es el patrón recomendado por Radix cuando un
        // Popover vive dentro de un Dialog.
        <Popover open={open} onOpenChange={setOpen} modal>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    aria-invalid={ariaInvalid}
                    aria-describedby={ariaDescribedBy}
                    id={id}
                    disabled={disabled || loading}
                    className={cn(
                        'group w-full cursor-pointer justify-between font-normal',
                        !selected && 'text-muted-foreground',
                        className,
                    )}
                >
                    <span className="truncate">
                        {selected ? selected.label : placeholder}
                    </span>
                    <div className="flex items-center gap-1">
                        {clearable && selected && !disabled && !loading && (
                            <span
                                role="button"
                                aria-label="Limpiar selección"
                                tabIndex={-1}
                                onClick={handleClear}
                                onPointerDown={(e) => e.stopPropagation()}
                                className="hover:bg-muted rounded p-0.5 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100"
                            >
                                <X className="size-3.5" strokeWidth={2.5} />
                            </span>
                        )}
                        {loading ? (
                            <Loader2 className="size-4 shrink-0 animate-spin opacity-50" />
                        ) : (
                            <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                        )}
                    </div>
                    {name && (
                        <input
                            type="hidden"
                            name={name}
                            value={value ?? ''}
                        />
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent
                // Ancho: por defecto sigue al trigger, pero garantizamos un
                // mínimo legible (12rem ~ 192px) para casos donde el trigger
                // está dentro de un grid estrecho (ej. cascada geo en mobile
                // con 3 columnas). Radix permite que el popover sea más ancho
                // que el trigger sin romper el alineamiento.
                className="w-(--radix-popover-trigger-width) min-w-48 p-0"
                align="start"
                sideOffset={4}
                // Defensa extra: si por algún motivo el dialog padre sigue
                // capturando wheel/touch, freno la propagación aquí para
                // que el scroll interno de CommandList se preserve.
                onWheel={(e) => e.stopPropagation()}
                onTouchMove={(e) => e.stopPropagation()}
            >
                <Command>
                    <CommandInput placeholder={searchPlaceholder} />
                    <CommandList>
                        <CommandEmpty>{emptyMessage}</CommandEmpty>
                        <CommandGroup>
                            {options.map((opt) => (
                                <CommandItem
                                    key={opt.value}
                                    value={opt.label}
                                    onSelect={() => {
                                        onChange(
                                            opt.value === value
                                                ? null
                                                : opt.value,
                                        );
                                        setOpen(false);
                                    }}
                                    className="cursor-pointer"
                                >
                                    <Check
                                        className={cn(
                                            'mr-2 size-4',
                                            opt.value === value
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                    {opt.label}
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
