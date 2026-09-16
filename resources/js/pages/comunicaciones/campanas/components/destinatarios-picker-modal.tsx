import { router } from '@inertiajs/react';
import { Loader2, Phone, Search, Users } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { FormModal } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { formatWhatsAppPhone } from '@/lib/format-whatsapp-phone';

const ROUTE_URL = '/comunicaciones/campanas';

type OwnerRow = {
    id: string;
    nombre: string;
    telefono: string | null;
    telefono_alt: string | null;
};

type Props = {
    open: boolean;
    campanaId: string | null;
    campanaNombre?: string;
    onOpenChange: (open: boolean) => void;
};

export function DestinatariosPickerModal({
    open,
    campanaId,
    campanaNombre,
    onOpenChange,
}: Props) {
    const [search, setSearch] = useState('');
    const [debounced, setDebounced] = useState('');
    const [page, setPage] = useState(1);
    const [loading, setLoading] = useState(false);
    const [rows, setRows] = useState<OwnerRow[]>([]);
    const [total, setTotal] = useState(0);
    const [lastPage, setLastPage] = useState(1);
    const [inLote, setInLote] = useState(0);
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        const timer = window.setTimeout(() => setDebounced(search.trim()), 300);

        return () => window.clearTimeout(timer);
    }, [search]);

    const load = useCallback(async () => {
        if (!open || !campanaId) {
            return;
        }

        setLoading(true);
        try {
            const params = new URLSearchParams({
                search: debounced,
                page: String(page),
                per_page: '15',
            });
            const response = await fetch(
                `${ROUTE_URL}/${campanaId}/elegibles?${params.toString()}`,
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                },
            );
            if (!response.ok) {
                return;
            }
            const json = (await response.json()) as {
                data: OwnerRow[];
                current_page: number;
                last_page: number;
                total: number;
                in_lote: number;
            };
            setRows(json.data);
            setLastPage(Math.max(1, json.last_page));
            setTotal(json.total);
            setInLote(json.in_lote);
        } finally {
            setLoading(false);
        }
    }, [campanaId, debounced, open, page]);

    useEffect(() => {
        if (!open) {
            setSearch('');
            setDebounced('');
            setPage(1);
            setSelected(new Set());
            setRows([]);

            return;
        }

        void load();
    }, [open, load]);

    useEffect(() => {
        setPage(1);
    }, [debounced]);

    const toggle = (id: string) => {
        setSelected((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    };

    const togglePage = () => {
        const ids = rows.map((row) => row.id);
        const allOnPage = ids.length > 0 && ids.every((id) => selected.has(id));
        setSelected((prev) => {
            const next = new Set(prev);
            if (allOnPage) {
                ids.forEach((id) => next.delete(id));
            } else {
                ids.forEach((id) => next.add(id));
            }

            return next;
        });
    };

    const submitSelected = () => {
        if (!campanaId || selected.size === 0) {
            return;
        }
        setSaving(true);
        router.post(
            `${ROUTE_URL}/${campanaId}/destinatarios`,
            { propietario_ids: [...selected] },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelected(new Set());
                    void load();
                    router.reload({
                        only: ['items', 'stats', 'lote', 'campana', 'filters'],
                    });
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const submitAll = () => {
        if (!campanaId || total === 0) {
            return;
        }
        if (
            !window.confirm(
                `¿Agregar los ${total} dueños de esta búsqueda al lote?`,
            )
        ) {
            return;
        }
        setSaving(true);
        router.post(
            `${ROUTE_URL}/${campanaId}/destinatarios/todos`,
            { search: debounced },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelected(new Set());
                    void load();
                    router.reload({
                        only: ['items', 'stats', 'lote', 'campana', 'filters'],
                    });
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const allOnPage =
        rows.length > 0 && rows.every((row) => selected.has(row.id));

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title="Elegir destinatarios"
            description={`${campanaNombre ?? 'Campaña'} · ya hay ${inLote} en el lote. Solo celulares Perú (9 dígitos).`}
            size="lg"
            footer={
                <div className="flex w-full flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-sm text-muted-foreground">
                        {selected.size > 0
                            ? `${selected.size} seleccionados`
                            : `${total} disponibles`}
                    </p>
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="cursor-pointer"
                            onClick={() => onOpenChange(false)}
                        >
                            Cerrar
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            className="cursor-pointer"
                            disabled={saving || total === 0}
                            onClick={submitAll}
                        >
                            Agregar todos
                        </Button>
                        <Button
                            type="button"
                            className="cursor-pointer gap-1.5 bg-sky-600 text-white hover:bg-sky-700"
                            disabled={saving || selected.size === 0}
                            onClick={submitSelected}
                        >
                            {saving ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <Users className="size-4" />
                            )}
                            Agregar seleccionados
                        </Button>
                    </div>
                </div>
            }
        >
            <div className="relative mb-3">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Buscar dueño o teléfono…"
                    className="pl-9"
                />
            </div>

            <div className="overflow-hidden rounded-lg border border-border/60">
                <div className="flex items-center gap-3 border-b border-brand-200/60 bg-brand-50/75 px-3 py-2 text-xs font-semibold text-brand-800/90 dark:border-brand-800/40 dark:bg-brand-950/40 dark:text-brand-100/90">
                    <Checkbox
                        checked={allOnPage ? true : rows.some((row) => selected.has(row.id)) ? 'indeterminate' : false}
                        onCheckedChange={togglePage}
                        aria-label="Seleccionar página"
                    />
                    <span className="flex-1">Propietario</span>
                    <span className="w-36">Teléfono</span>
                </div>
                {loading ? (
                    <div className="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground">
                        <Loader2 className="size-4 animate-spin" />
                        Cargando…
                    </div>
                ) : rows.length === 0 ? (
                    <p className="px-3 py-10 text-center text-sm text-muted-foreground">
                        No hay dueños con celular válido fuera del lote.
                    </p>
                ) : (
                    <ul className="divide-y divide-border/60">
                        {rows.map((row) => {
                            const checked = selected.has(row.id);

                            return (
                                <li key={row.id}>
                                    <label className="flex cursor-pointer items-center gap-3 px-3 py-2.5 hover:bg-muted/40">
                                        <Checkbox
                                            checked={checked}
                                            onCheckedChange={() => toggle(row.id)}
                                        />
                                        <span className="min-w-0 flex-1 truncate text-sm font-medium">
                                            {row.nombre}
                                        </span>
                                        <span className="flex w-36 items-center gap-1 font-mono text-xs text-muted-foreground">
                                            <Phone className="size-3 shrink-0" />
                                            {formatWhatsAppPhone(
                                                row.telefono ?? row.telefono_alt ?? '',
                                            )}
                                        </span>
                                    </label>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            {lastPage > 1 ? (
                <div className="mt-3 flex items-center justify-between text-xs text-muted-foreground">
                    <span>
                        Página {page} de {lastPage}
                    </span>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="cursor-pointer"
                            disabled={page <= 1}
                            onClick={() => setPage((p) => Math.max(1, p - 1))}
                        >
                            Anterior
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="cursor-pointer"
                            disabled={page >= lastPage}
                            onClick={() => setPage((p) => p + 1)}
                        >
                            Siguiente
                        </Button>
                    </div>
                </div>
            ) : null}
        </FormModal>
    );
}
