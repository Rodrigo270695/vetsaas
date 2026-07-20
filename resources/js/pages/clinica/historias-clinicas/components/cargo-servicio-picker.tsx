import { Check, ChevronsUpDown, Loader2, Stethoscope, X } from 'lucide-react';
import * as React from 'react';
import { useTranslation } from 'react-i18next';
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

export type CargoServicioOption = {
    nombre: string;
    precio_lista: string;
    origen: string;
    categoria: string | null;
};

type Props = {
    serviciosBuscarUrl: string;
    valueLabel: string | null;
    onSelect: (row: CargoServicioOption | null) => void;
    disabled?: boolean;
    id?: string;
    'aria-invalid'?: boolean;
};

export function CargoServicioPicker({
    serviciosBuscarUrl,
    valueLabel,
    onSelect,
    disabled = false,
    id,
    'aria-invalid': ariaInvalid,
}: Props) {
    const { t } = useTranslation('consulta-cargos');
    const [open, setOpen] = React.useState(false);
    const [search, setSearch] = React.useState('');
    const [loading, setLoading] = React.useState(false);
    const [options, setOptions] = React.useState<CargoServicioOption[]>([]);

    const fetchUrl = React.useCallback(
        (q: string) => {
            const sep = serviciosBuscarUrl.includes('?') ? '&' : '?';

            return `${serviciosBuscarUrl}${sep}q=${encodeURIComponent(q.trim())}`;
        },
        [serviciosBuscarUrl],
    );

    React.useEffect(() => {
        if (!open) {
            return;
        }

        const q = search;
        const handle = window.setTimeout(() => {
            setLoading(true);
            void fetch(fetchUrl(q), {
                method: 'GET',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then(async (res) => {
                    const body = (await res.json()) as { data?: CargoServicioOption[] };

                    if (!res.ok || !Array.isArray(body.data)) {
                        setOptions([]);

                        return;
                    }

                    setOptions(body.data);
                })
                .catch(() => setOptions([]))
                .finally(() => setLoading(false));
        }, 280);

        return () => window.clearTimeout(handle);
    }, [open, search, fetchUrl]);

    const selectedLabel =
        valueLabel != null && valueLabel.trim() !== '' ? valueLabel.trim() : null;

    return (
        <Popover open={open} onOpenChange={setOpen} modal>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    aria-invalid={ariaInvalid}
                    id={id}
                    disabled={disabled || loading}
                    className={cn(
                        'group h-8 w-full cursor-pointer justify-between font-normal text-sm',
                        !selectedLabel && 'text-muted-foreground',
                    )}
                >
                    <span className="inline-flex min-w-0 flex-1 items-center gap-2 truncate">
                        <Stethoscope className="size-3.5 shrink-0 opacity-60" aria-hidden />
                        <span className="truncate">
                            {selectedLabel ?? t('servicio_picker.placeholder')}
                        </span>
                    </span>
                    <span className="flex shrink-0 items-center gap-1">
                        {selectedLabel && !disabled && (
                            <span
                                role="button"
                                aria-label={t('servicio_picker.clear_aria')}
                                tabIndex={-1}
                                className="inline-flex cursor-pointer rounded-sm p-0.5 opacity-60 hover:opacity-100"
                                onPointerDown={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    onSelect(null);
                                }}
                            >
                                <X className="size-3.5" />
                            </span>
                        )}
                        <ChevronsUpDown className="size-3.5 shrink-0 opacity-50" />
                    </span>
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[var(--radix-popover-trigger-width)] p-0" align="start">
                <Command shouldFilter={false}>
                    <CommandInput
                        placeholder={t('servicio_picker.search_placeholder')}
                        value={search}
                        onValueChange={setSearch}
                    />
                    <CommandList>
                        {loading ? (
                            <div className="flex items-center justify-center gap-2 py-6 text-sm text-muted-foreground">
                                <Loader2 className="size-4 animate-spin" />
                                {t('servicio_picker.loading')}
                            </div>
                        ) : (
                            <>
                                <CommandEmpty>{t('servicio_picker.empty')}</CommandEmpty>
                                <CommandGroup>
                                    {options.map((opt, i) => {
                                        const key = `${opt.origen}:${opt.nombre}:${i}`;

                                        return (
                                            <CommandItem
                                                key={key}
                                                value={key}
                                                onSelect={() => {
                                                    onSelect(opt);
                                                    setOpen(false);
                                                }}
                                            >
                                                <Check
                                                    className={cn(
                                                        'mr-2 size-4 shrink-0',
                                                        selectedLabel === opt.nombre
                                                            ? 'opacity-100'
                                                            : 'opacity-0',
                                                    )}
                                                />
                                                <span className="min-w-0 flex-1 truncate">
                                                    {opt.nombre}
                                                </span>
                                                {opt.categoria ? (
                                                    <span className="ml-2 shrink-0 text-xs text-muted-foreground">
                                                        {opt.categoria}
                                                    </span>
                                                ) : null}
                                                {opt.precio_lista ? (
                                                    <span className="ml-2 shrink-0 text-xs tabular-nums text-muted-foreground">
                                                        {opt.precio_lista}
                                                    </span>
                                                ) : null}
                                            </CommandItem>
                                        );
                                    })}
                                </CommandGroup>
                            </>
                        )}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
