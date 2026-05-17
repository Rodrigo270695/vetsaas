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
import clinica from '@/routes/clinica';

export type CargoProductoOption = {
    id: string;
    nombre: string;
    sku: string | null;
    unidad: string | null;
    /** Precio de venta del catálogo (para prellenar P. unit. en cargos). */
    precio_venta: string | number | null;
};

type Props = {
    consultaId?: string;
    productosBuscarUrl?: string;
    value: string | null;
    labelResolved: string | null;
    onSelect: (row: CargoProductoOption | null) => void;
    disabled?: boolean;
    id?: string;
    'aria-invalid'?: boolean;
};

export function CargoProductoPicker({
    consultaId,
    productosBuscarUrl,
    value,
    labelResolved,
    onSelect,
    disabled = false,
    id,
    'aria-invalid': ariaInvalid,
}: Props) {
    const { t } = useTranslation('consulta-cargos');
    const [open, setOpen] = React.useState(false);
    const [search, setSearch] = React.useState('');
    const [loading, setLoading] = React.useState(false);
    const [options, setOptions] = React.useState<CargoProductoOption[]>([]);

    const fetchUrl = React.useCallback(
        (q: string) => {
            const base =
                productosBuscarUrl ??
                (consultaId
                    ? clinica.historiasClinicas.consultas.cargos.productosBuscar.url(consultaId)
                    : '');
            const sep = base.includes('?') ? '&' : '?';

            return `${base}${sep}q=${encodeURIComponent(q.trim())}`;
        },
        [consultaId, productosBuscarUrl],
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
                    const body = (await res.json()) as { data?: CargoProductoOption[] };

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
                        placeholder={t('producto_picker.search_placeholder')}
                        value={search}
                        onValueChange={setSearch}
                    />
                    <CommandList>
                        {loading ? (
                            <div className="flex items-center justify-center gap-2 py-6 text-sm text-muted-foreground">
                                <Loader2 className="size-4 animate-spin" />
                                {t('producto_picker.loading')}
                            </div>
                        ) : (
                            <>
                                <CommandEmpty>{t('producto_picker.empty')}</CommandEmpty>
                                <CommandGroup>
                                    {options.map((opt) => (
                                        <CommandItem
                                            key={opt.id}
                                            value={opt.id}
                                            onSelect={() => {
                                                onSelect(opt);
                                                setOpen(false);
                                            }}
                                        >
                                            <Check
                                                className={cn(
                                                    'mr-2 size-4 shrink-0',
                                                    value === opt.id ? 'opacity-100' : 'opacity-0',
                                                )}
                                            />
                                            <span className="min-w-0 flex-1 truncate">{opt.nombre}</span>
                                            {opt.sku ? (
                                                <span className="ml-2 shrink-0 text-xs text-muted-foreground">
                                                    {opt.sku}
                                                </span>
                                            ) : null}
                                            {opt.precio_venta != null && opt.precio_venta !== '' ? (
                                                <span className="ml-2 shrink-0 text-xs tabular-nums text-muted-foreground">
                                                    {String(opt.precio_venta)}
                                                </span>
                                            ) : null}
                                        </CommandItem>
                                    ))}
                                </CommandGroup>
                            </>
                        )}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
