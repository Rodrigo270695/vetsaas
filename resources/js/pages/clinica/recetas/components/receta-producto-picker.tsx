import { Check, ChevronsUpDown, Loader2, Package, X } from 'lucide-react';
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
import { loadClinicaBootstrap, searchCachedMedicamentos } from '@/lib/offline/cache';
import { isOfflineMode } from '@/lib/offline/enqueue-if-offline';
import clinica from '@/routes/clinica';

export type RecetaProductoOption = {
    id: string;
    nombre: string;
    sku: string | null;
    unidad: string | null;
};

type Props = {
    value: string | null;
    labelResolved: string | null;
    onSelect: (row: RecetaProductoOption | null) => void;
    disabled?: boolean;
    id?: string;
    'aria-invalid'?: boolean;
};

export function RecetaProductoPicker({
    value,
    labelResolved,
    onSelect,
    disabled = false,
    id,
    'aria-invalid': ariaInvalid,
}: Props) {
    const { t } = useTranslation('recetas');
    const [open, setOpen] = React.useState(false);
    const [search, setSearch] = React.useState('');
    const [loading, setLoading] = React.useState(false);
    const [options, setOptions] = React.useState<RecetaProductoOption[]>([]);

    const fetchUrl = React.useCallback((q: string) => {
        return clinica.recetas.productosMedicamento.url({
            query: { q: q.trim() },
        });
    }, []);

    React.useEffect(() => {
        if (!open) {
            return;
        }

        const q = search;
        const handle = window.setTimeout(() => {
            if (isOfflineMode()) {
                setLoading(true);
                void loadClinicaBootstrap()
                    .then((cache) => {
                        if (!cache) {
                            setOptions([]);

                            return;
                        }

                        setOptions(searchCachedMedicamentos(cache, q));
                    })
                    .catch(() => setOptions([]))
                    .finally(() => setLoading(false));

                return;
            }

            setLoading(true);
            void fetch(fetchUrl(q), {
                method: 'GET',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then(async (res) => {
                    const body = (await res.json()) as { data?: RecetaProductoOption[] };

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
        value != null && value !== '' && labelResolved != null && labelResolved.trim() !== ''
            ? labelResolved
            : null;

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
                        'group h-9 w-full cursor-pointer justify-between font-normal',
                        !selectedLabel && 'text-muted-foreground',
                    )}
                >
                    <span className="inline-flex min-w-0 flex-1 items-center gap-2 truncate">
                        <Package className="size-3.5 shrink-0 opacity-60" aria-hidden />
                        <span className="truncate">
                            {selectedLabel ?? t('producto_picker.placeholder')}
                        </span>
                    </span>
                    <span className="flex shrink-0 items-center gap-1">
                        {selectedLabel && !disabled && (
                            <span
                                role="button"
                                aria-label={t('producto_picker.clear_aria')}
                                tabIndex={-1}
                                className="hover:bg-muted rounded p-0.5 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    onSelect(null);
                                }}
                                onPointerDown={(e) => e.stopPropagation()}
                            >
                                <X className="size-3.5" strokeWidth={2.5} />
                            </span>
                        )}
                        {loading ? (
                            <Loader2 className="size-4 shrink-0 animate-spin opacity-50" />
                        ) : (
                            <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                        )}
                    </span>
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className="w-(--radix-popover-trigger-width) min-w-56 p-0"
                align="start"
                sideOffset={4}
                onWheel={(e) => e.stopPropagation()}
                onTouchMove={(e) => e.stopPropagation()}
            >
                <Command shouldFilter={false}>
                    <CommandInput
                        placeholder={t('producto_picker.search')}
                        value={search}
                        onValueChange={setSearch}
                    />
                    <CommandList>
                        <CommandEmpty>
                            {loading
                                ? t('producto_picker.loading')
                                : t('producto_picker.empty')}
                        </CommandEmpty>
                        <CommandGroup>
                            {options.map((opt) => (
                                <CommandItem
                                    key={opt.id}
                                    value={`${opt.nombre} ${opt.sku ?? ''}`}
                                    onSelect={() => {
                                        onSelect(opt);
                                        setOpen(false);
                                        setSearch('');
                                    }}
                                    className="cursor-pointer"
                                >
                                    <Check
                                        className={cn(
                                            'mr-2 size-4',
                                            opt.id === value ? 'opacity-100' : 'opacity-0',
                                        )}
                                    />
                                    <span className="flex min-w-0 flex-col gap-0.5">
                                        <span className="truncate font-medium">{opt.nombre}</span>
                                        {opt.sku ? (
                                            <span className="truncate text-xs text-muted-foreground">
                                                SKU {opt.sku}
                                            </span>
                                        ) : null}
                                    </span>
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
