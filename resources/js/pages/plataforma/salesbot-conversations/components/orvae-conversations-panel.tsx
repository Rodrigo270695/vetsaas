import {
    Bot,
    Loader2,
    MessageCircle,
    PauseCircle,
    RefreshCw,
    Search,
    User,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';

type OrvaeConversation = {
    id: string;
    phone: string | null;
    nombre: string | null;
    linea: string | null;
    bot_activo: boolean;
    pausa_motivo: string | null;
    last_inbound_at: string | null;
    created_at: string | null;
    ultimo_mensaje: { autor: string; body: string; at: string | null } | null;
};

type OrvaeMessage = {
    id: number;
    direccion: 'in' | 'out';
    autor: 'cliente' | 'bot' | 'humano';
    tipo: string;
    body: string;
    at: string | null;
};

type ListResponse = {
    ok: boolean;
    error?: string;
    data?: OrvaeConversation[];
    meta?: { total: number; page: number; last_page: number };
    stats?: { total: number; activos: number; pausados: number; hoy: number };
};

type Props = {
    configured: boolean;
    url: string;
};

const ESTADOS = [
    { key: 'todos', label: 'Todas' },
    { key: 'activos', label: 'Bot activo' },
    { key: 'pausados', label: 'Pausadas' },
];

const REFRESH_MS = 20_000;

const formatWhen = (iso: string | null): string =>
    iso
        ? new Date(iso).toLocaleString('es-PE', {
              dateStyle: 'short',
              timeStyle: 'short',
          })
        : '—';

/**
 * Conversaciones de ventas sobre VetSaaS que atiende el bot del Centro de Ventas de ORVAE.
 * Solo lectura: el bot y las pausas se manejan desde ORVAE.
 */
export function OrvaeConversationsPanel({ configured, url }: Props) {
    const [search, setSearch] = useState('');
    const [query, setQuery] = useState({
        search: '',
        estado: 'todos',
        page: 1,
    });
    const [tick, setTick] = useState(0);
    const [result, setResult] = useState<{
        key: string;
        list: ListResponse;
    } | null>(null);
    const [openId, setOpenId] = useState<string | null>(null);
    const key = `${query.search}|${query.estado}|${query.page}|${tick}`;
    const loading = result?.key !== key;
    const list = result?.list ?? null;

    useEffect(() => {
        if (!configured) {
            return;
        }

        let cancelled = false;
        const params = new URLSearchParams({
            search: query.search,
            estado: query.estado,
            page: String(query.page),
        });

        fetch(`${url}?${params.toString()}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((response) => response.json() as Promise<ListResponse>)
            .catch(
                (): ListResponse => ({
                    ok: false,
                    error: 'No se pudo conectar con ORVAE.',
                }),
            )
            .then((data) => {
                if (!cancelled) {
                    setResult({ key, list: data });
                }
            });

        return () => {
            cancelled = true;
        };
    }, [configured, url, key, query]);

    useEffect(() => {
        const timer = setInterval(
            () => setTick((current) => current + 1),
            REFRESH_MS,
        );

        return () => clearInterval(timer);
    }, []);

    const refresh = useCallback(() => setTick((current) => current + 1), []);

    if (!configured) {
        return (
            <div className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                Falta conectar con ORVAE: define{' '}
                <span className="font-mono">ORVAE_KNOWLEDGE_REFRESH_URL</span> y{' '}
                <span className="font-mono">ORVAE_PROVISION_HMAC_SECRET</span>{' '}
                en el .env.
            </div>
        );
    }

    const onSearch = (event: FormEvent) => {
        event.preventDefault();
        setQuery((current) => ({ ...current, search, page: 1 }));
    };

    const data = list?.data ?? [];
    const meta = list?.meta;
    const stats = list?.stats;

    return (
        <div className="flex flex-col gap-3">
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                {[
                    ['Total', stats?.total, MessageCircle],
                    ['Bot activo', stats?.activos, Bot],
                    ['Pausadas', stats?.pausados, PauseCircle],
                    ['Hoy', stats?.hoy, MessageCircle],
                ].map(([label, value, Icon]) => {
                    const StatIcon = Icon as typeof Bot;

                    return (
                        <div
                            key={label as string}
                            className="rounded-xl border bg-card p-3"
                        >
                            <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <StatIcon className="size-3.5" />{' '}
                                {label as string}
                            </p>
                            <p className="text-lg font-semibold tabular-nums">
                                {(value as number | undefined) ?? '—'}
                            </p>
                        </div>
                    );
                })}
            </div>

            <div className="rounded-xl border bg-card">
                <div className="flex flex-col gap-2 border-b p-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex gap-1">
                        {ESTADOS.map((estado) => (
                            <Button
                                key={estado.key}
                                type="button"
                                size="sm"
                                variant={
                                    query.estado === estado.key
                                        ? 'default'
                                        : 'ghost'
                                }
                                onClick={() =>
                                    setQuery((current) => ({
                                        ...current,
                                        estado: estado.key,
                                        page: 1,
                                    }))
                                }
                            >
                                {estado.label}
                            </Button>
                        ))}
                    </div>
                    <div className="flex items-center gap-2">
                        <form onSubmit={onSearch} className="relative">
                            <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Nombre o teléfono"
                                className="h-9 w-56 pl-8"
                            />
                        </form>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            onClick={refresh}
                            disabled={loading}
                            title="Actualizar"
                        >
                            <RefreshCw
                                className={cn(
                                    'size-4',
                                    loading && 'animate-spin',
                                )}
                            />
                        </Button>
                    </div>
                </div>

                {list && !list.ok ? (
                    <p className="p-6 text-center text-sm text-destructive">
                        {list.error}
                    </p>
                ) : list === null ? (
                    <p className="flex items-center justify-center gap-2 p-6 text-sm text-muted-foreground">
                        <Loader2 className="size-4 animate-spin" /> Cargando
                        desde ORVAE…
                    </p>
                ) : data.length === 0 ? (
                    <p className="p-6 text-center text-sm text-muted-foreground">
                        Aún no hay conversaciones sobre VetSaaS atendidas por
                        ORVAE.
                    </p>
                ) : (
                    <ul className="divide-y">
                        {data.map((c) => (
                            <li key={c.id}>
                                <button
                                    type="button"
                                    onClick={() => setOpenId(c.id)}
                                    className="flex w-full items-start gap-3 px-3 py-3 text-left hover:bg-muted/50"
                                >
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-medium">
                                            {c.nombre ??
                                                (c.phone
                                                    ? `+${c.phone}`
                                                    : 'Sin nombre')}
                                        </span>
                                        <span className="block truncate text-xs text-muted-foreground">
                                            {c.ultimo_mensaje
                                                ? `${c.ultimo_mensaje.autor === 'bot' ? 'Bot: ' : c.ultimo_mensaje.autor === 'humano' ? 'Asesor: ' : ''}${c.ultimo_mensaje.body}`
                                                : '—'}
                                        </span>
                                    </span>
                                    <span className="shrink-0 text-right text-xs">
                                        <span className="block text-muted-foreground">
                                            {formatWhen(
                                                c.last_inbound_at ??
                                                    c.created_at,
                                            )}
                                        </span>
                                        <span
                                            className={
                                                c.bot_activo
                                                    ? 'text-emerald-600'
                                                    : 'text-amber-600'
                                            }
                                        >
                                            {c.bot_activo
                                                ? 'Bot activo'
                                                : (c.pausa_motivo ?? 'Pausado')}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}

                {meta && meta.last_page > 1 ? (
                    <div className="flex items-center justify-end gap-2 border-t p-3 text-sm">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={meta.page <= 1}
                            onClick={() =>
                                setQuery((current) => ({
                                    ...current,
                                    page: current.page - 1,
                                }))
                            }
                        >
                            Anterior
                        </Button>
                        <span className="text-muted-foreground">
                            {meta.page} / {meta.last_page}
                        </span>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={meta.page >= meta.last_page}
                            onClick={() =>
                                setQuery((current) => ({
                                    ...current,
                                    page: current.page + 1,
                                }))
                            }
                        >
                            Siguiente
                        </Button>
                    </div>
                ) : null}
            </div>

            <OrvaeConversationSheet
                url={openId ? `${url}/${openId}` : null}
                conversation={data.find((c) => c.id === openId) ?? null}
                onClose={() => setOpenId(null)}
            />
        </div>
    );
}

function OrvaeConversationSheet({
    url,
    conversation,
    onClose,
}: {
    url: string | null;
    conversation: OrvaeConversation | null;
    onClose: () => void;
}) {
    const [loaded, setLoaded] = useState<{
        url: string;
        messages: OrvaeMessage[] | null;
        error: string | null;
    } | null>(null);
    const messages = loaded?.url === url ? loaded.messages : null;
    const error = loaded?.url === url ? loaded.error : null;

    useEffect(() => {
        if (!url) {
            return;
        }

        let cancelled = false;

        fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(
                (response) =>
                    response.json() as Promise<{
                        ok: boolean;
                        error?: string;
                        messages?: OrvaeMessage[];
                    }>,
            )
            .then((json) => {
                if (!cancelled) {
                    setLoaded({
                        url,
                        messages: json.ok ? (json.messages ?? []) : null,
                        error: json.ok ? null : (json.error ?? 'Error'),
                    });
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setLoaded({
                        url,
                        messages: null,
                        error: 'No se pudo conectar con ORVAE.',
                    });
                }
            });

        return () => {
            cancelled = true;
        };
    }, [url]);

    return (
        <Sheet open={url !== null} onOpenChange={(open) => !open && onClose()}>
            <SheetContent className="flex w-full flex-col gap-0 p-0 sm:max-w-lg">
                <SheetHeader className="border-b p-4">
                    <SheetTitle>
                        {conversation?.nombre ??
                            (conversation?.phone
                                ? `+${conversation.phone}`
                                : 'Conversación')}
                    </SheetTitle>
                    <SheetDescription>
                        Atendida por ORVAE
                        {conversation?.linea ? ` · ${conversation.linea}` : ''}.
                        Para pausar el bot o responder, hazlo desde ORVAE o
                        desde el WhatsApp.
                    </SheetDescription>
                </SheetHeader>
                <div className="flex-1 space-y-2 overflow-y-auto bg-muted/30 p-4">
                    {error ? (
                        <p className="text-center text-sm text-destructive">
                            {error}
                        </p>
                    ) : null}
                    {messages === null && !error ? (
                        <p className="flex items-center justify-center gap-2 text-sm text-muted-foreground">
                            <Loader2 className="size-4 animate-spin" />{' '}
                            Cargando…
                        </p>
                    ) : null}
                    {messages?.map((m) => (
                        <div
                            key={m.id}
                            className={cn(
                                'flex',
                                m.direccion === 'out'
                                    ? 'justify-end'
                                    : 'justify-start',
                            )}
                        >
                            <div
                                className={cn(
                                    'max-w-[85%] rounded-2xl px-3 py-2 text-sm whitespace-pre-wrap shadow-sm',
                                    m.direccion === 'out'
                                        ? m.autor === 'bot'
                                            ? 'bg-primary/10'
                                            : 'bg-emerald-500/15'
                                        : 'bg-background',
                                )}
                            >
                                {m.direccion === 'out' ? (
                                    <span className="mb-0.5 flex items-center gap-1 text-[10px] font-semibold text-muted-foreground uppercase">
                                        {m.autor === 'bot' ? (
                                            <Bot className="size-3" />
                                        ) : (
                                            <User className="size-3" />
                                        )}
                                        {m.autor === 'bot' ? 'Bot' : 'Asesor'}
                                    </span>
                                ) : null}
                                {m.tipo === 'audio' ? '🎤 ' : ''}
                                {m.body}
                                <span className="mt-1 block text-right text-[10px] text-muted-foreground">
                                    {formatWhen(m.at)}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
            </SheetContent>
        </Sheet>
    );
}
